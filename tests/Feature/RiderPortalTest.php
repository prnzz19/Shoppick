<?php

namespace Tests\Feature;

use App\Models\{NotificationModel, Order, ProofOfDelivery, RiderProfile, Role, SellerOrder, SellerProfile, Shipment, Store, User};
use App\Services\RiderPortalActionService;
use App\Support\RiderPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, Storage};
use Tests\TestCase;

class RiderPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach(['buyer','seller','admin','super_admin','logistics','rider'] as $slug) Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
    }

    private function rider(array $attributes=[]): User
    {
        $user=User::factory()->create($attributes+['is_active'=>true,'registration_type'=>'rider','registration_status'=>'approved']);
        $user->assignRole('rider');
        RiderProfile::create(['user_id'=>$user->id,'account_status'=>'active','availability'=>'available','vehicle_type'=>'motorcycle','plate_number'=>'TEST-123']);
        return $user;
    }

    private function shipment(User $rider,string $status='assigned_to_rider',bool $pickup=false): Shipment
    {
        $seller=User::factory()->create(['is_active'=>true]);$seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $key=str()->random(12);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Portal Shop '.$key,'slug'=>'portal-'.$key,'status'=>'active']);
        $buyer=User::factory()->create();
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>'ORD-'.$key,'status'=>'ready_to_ship','payment_method'=>'card','payment_status'=>'paid','subtotal'=>100,'shipping_fee'=>20,'total'=>120,'buyer_name'=>'Private Recipient','buyer_phone'=>'09170000000','shipping_address'=>['address_line'=>'Private street','city'=>'Calamba','province'=>'Laguna','email'=>'private@example.test']]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>'SELL-'.$key,'status'=>'ready_to_ship','subtotal'=>100,'shipping_fee'=>20,'seller_total'=>100]);
        return Shipment::create(['shipment_number'=>'SHP-'.$key,'parcel_code'=>'PCL-'.$key,'seller_order_id'=>$sellerOrder->id,'order_id'=>$order->id,'store_id'=>$store->id,'pickup_rider_id'=>$pickup?$rider->id:null,'rider_id'=>$pickup?null:$rider->id,'status'=>$status,'assigned_at'=>now(),'pickup_address'=>['address'=>'Seller street'],'delivery_address'=>$order->shipping_address]);
    }

    public function test_dedicated_login_session_remember_and_secure_logout(): void
    {
        $rider=$this->rider();
        $this->get(route('rider.dashboard'))->assertRedirect(route('rider.login'));
        $this->get(route('rider.login'))->assertOk()->assertSee('Rider Portal')->assertSee('data-password-toggle',false)->assertSee('name="_token"',false);
        $this->withSession(['before'=>'value']);$before=session()->getId();
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'password','remember'=>'1'])->assertRedirect(route('rider.dashboard'));
        $this->assertAuthenticatedAs($rider);$this->assertNotSame($before,session()->getId());
        $this->assertNotNull($rider->fresh()->remember_token);
        $token=session()->token();
        $this->post(route('rider.logout'))->assertRedirect(route('rider.login'));
        $this->assertGuest();$this->assertNotSame($token,session()->token());
    }

    public function test_other_roles_and_privileged_multi_role_accounts_are_denied(): void
    {
        foreach(['buyer','seller','admin','super_admin','logistics'] as $role) {
            $user=User::factory()->create(['is_active'=>true]);$user->assignRole($role);
            $this->actingAs($user)->get(route('rider.dashboard'))->assertForbidden();
            Auth::logout();
            $this->post(route('rider.login.submit'),['email'=>$user->email,'password'=>'password'])->assertSessionHasErrors('email');$this->assertGuest();
        }
        $rider=$this->rider();$rider->assignRole('admin');
        $this->actingAs($rider)->get(route('rider.jobs'))->assertForbidden();
    }

    public function test_disabled_suspended_archived_and_pending_profiles_cannot_enter(): void
    {
        foreach(['suspended','disabled','archived'] as $status) {
            $rider=$this->rider();$rider->riderProfile->update(['account_status'=>$status]);
            $this->actingAs($rider)->get(route('rider.dashboard'))->assertForbidden();
            Auth::logout();$this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'password'])->assertSessionHasErrors('email');$this->assertGuest();
        }
        $rider=$this->rider(['is_active'=>false]);$this->actingAs($rider)->get(route('rider.profile'))->assertForbidden();
        $rider=$this->rider(['registration_status'=>'pending']);$this->actingAs($rider)->get(route('rider.dashboard'))->assertRedirect(route('login'));
        $rider=$this->rider();$rider->delete();Auth::logout();
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'password'])->assertSessionHasErrors(['email'=>'Unable to sign in with those credentials.']);
    }

    public function test_generic_invalid_credentials_and_rate_limit(): void
    {
        $rider=$this->rider();
        foreach(range(1,5) as $attempt)$this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'wrong'])->assertSessionHasErrors(['email'=>'Unable to sign in with those credentials.']);
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'password'])->assertSessionHasErrors('email');$this->assertGuest();
        $this->post(route('rider.login.submit'),['email'=>'missing@example.test','password'=>'wrong'])->assertSessionHasErrors(['email'=>'Unable to sign in with those credentials.']);
    }

    public function test_every_shipment_endpoint_rejects_other_riders(): void
    {
        $rider=$this->rider();$other=$this->rider();$shipment=$this->shipment($other,'out_for_delivery');
        $this->actingAs($rider);
        foreach(['rider.shipments.show','rider.shipments.tracking'] as $route)$this->get(route($route,$shipment))->assertForbidden();
        foreach(['parcel-action'=>['action'=>'failed','reason'=>'recipient_unavailable'],'decline'=>['reason'=>'unavailable'],'cod-collection'=>[],'pod'=>['recipient_name'=>'Someone']] as $endpoint=>$data)$this->post('/rider/shipments/'.$shipment->id.'/'.$endpoint,$data)->assertForbidden();
        $pod=ProofOfDelivery::create(['shipment_id'=>$shipment->id,'submitted_by'=>$rider->id,'recipient_name'=>'Other','photo_path'=>'pod/private.jpg','status'=>'pending','submitted_at'=>now()]);
        $this->get(route('rider.pods.file',$pod))->assertForbidden();$this->get(route('rider.pods'))->assertOk()->assertDontSee($shipment->shipment_number);
        $this->assertSame('out_for_delivery',$shipment->fresh()->status);$this->assertSame(0,$shipment->events()->count());
    }

    public function test_lists_search_filters_history_pagination_and_minimal_private_data(): void
    {
        $rider=$this->rider();$other=$this->rider();$mine=$this->shipment($rider);$private=$this->shipment($other);
        $this->actingAs($rider)->get(route('rider.jobs'))->assertOk()->assertSee($mine->shipment_number)->assertDontSee($private->shipment_number)->assertDontSee('Private Recipient')->assertDontSee('Private street')->assertDontSee('private@example.test');
        $this->get(route('rider.jobs',['q'=>$mine->order->order_number,'from'=>today()->toDateString(),'to'=>today()->toDateString()]))->assertSee($mine->shipment_number);
        $this->get(route('rider.jobs',['to'=>today()->toDateString()]))->assertOk()->assertSee($mine->shipment_number);
        $this->get(route('rider.jobs',['status'=>'delivered']))->assertDontSee($mine->shipment_number);
        $this->get(route('rider.history'))->assertDontSee($mine->shipment_number);
        $mine->update(['status'=>'delivery_failed']);$this->get(route('rider.history',['status'=>'failed']))->assertSee($mine->shipment_number);
        $this->get(route('rider.history',['status'=>'assigned']))->assertDontSee($mine->shipment_number);
        foreach(range(1,12) as $i)$this->shipment($rider);
        $this->get(route('rider.jobs'))->assertViewHas('shipments',fn($rows)=>$rows->total()===13&&$rows->count()===12);
        $this->get(route('rider.jobs',['page'=>2]))->assertViewHas('shipments',fn($rows)=>$rows->count()===1);
        $this->get(route('rider.jobs',['status'=>'forged']))->assertSessionHasErrors('status');
        $this->get(route('rider.jobs',['from'=>'2026-10-10','to'=>'2026-10-01']))->assertSessionHasErrors('to');
    }

    public function test_pickup_transitions_code_validation_duplicate_and_no_skipping(): void
    {
        $rider=$this->rider();$shipment=$this->shipment($rider,'pickup_assigned',true);$url=route('rider.shipments.parcel-action',$shipment);
        $this->actingAs($rider)->post($url,['action'=>'delivered'])->assertSessionHasErrors('status');
        $this->post($url,['action'=>'accept_pickup','rider_id'=>999,'status'=>'delivered'])->assertSessionHas('success','Pickup accepted successfully.');
        $this->assertSame('pickup_accepted',$shipment->fresh()->status);
        $this->post($url,['action'=>'accept_pickup'])->assertSessionHasErrors('status');$this->assertSame(1,$shipment->events()->count());
        $this->post($url,['action'=>'confirm_pickup','parcel_code'=>'wrong'])->assertSessionHasErrors('parcel_code');
        $this->post($url,['action'=>'confirm_pickup','parcel_code'=>$shipment->parcel_code])->assertSessionHas('success','Parcel marked as Picked Up.');
        $this->post($url,['action'=>'arrive_sorting'])->assertSessionHas('success');
        $this->post($url,['action'=>'arrive_sorting'])->assertSessionHasErrors('status');$this->assertSame(3,$shipment->events()->count());
        $this->get(route('rider.dashboard'))->assertViewHas('current',null);
        $this->get(route('rider.shipments.show',$shipment))->assertDontSee('Private Recipient')->assertDontSee('private@example.test')->assertDontSee('Private street');
    }

    public function test_delivery_requires_acceptance_proof_and_cod_and_duplicates_do_not_settle_again(): void
    {
        Storage::fake('local');$rider=$this->rider();$shipment=$this->shipment($rider);$shipment->order->update(['payment_method'=>'cod','payment_status'=>'unpaid']);
        $payment=$shipment->order->payments()->create(['method'=>'cod','gateway'=>'cod','status'=>'pending','amount'=>120]);
        $url=route('rider.shipments.parcel-action',$shipment);$this->actingAs($rider);
        $this->post($url,['action'=>'start_delivery'])->assertSessionHasErrors('status');
        $this->post($url,['action'=>'accept_delivery'])->assertSessionHas('success');
        $this->post($url,['action'=>'accept_delivery'])->assertSessionHasErrors('status');
        $this->post($url,['action'=>'start_delivery'])->assertSessionHas('success');
        $this->post($url,['action'=>'delivered'])->assertSessionHasErrors('pod');
        $this->post(route('rider.shipments.pod',$shipment),['recipient_name'=>'Real Recipient','photo'=>UploadedFile::fake()->create('proof.jpg',10,'image/jpeg')])->assertSessionHas('success');
        $this->post(route('rider.shipments.pod',$shipment),['recipient_name'=>'Real Recipient'])->assertSessionHas('success');
        $this->assertSame('Real Recipient',$shipment->fresh()->proofOfDelivery->recipient_name);$this->assertSame(1,ProofOfDelivery::count());
        $oldVersion=RiderPortal::version($shipment->fresh());
        $this->post(route('rider.shipments.pod',$shipment),['recipient_name'=>'Real Recipient','notes'=>'Confirmed recipient','version'=>$oldVersion])->assertSessionHas('success');
        $this->post(route('rider.shipments.pod',$shipment),['recipient_name'=>'Overwrite','version'=>$oldVersion])->assertSessionHasErrors('status');
        $this->assertSame('Confirmed recipient',$shipment->fresh()->proofOfDelivery->notes);
        $this->post($url,['action'=>'delivered'])->assertSessionHasErrors('payment');$this->assertSame('pending',$payment->fresh()->status);
        $this->post(route('rider.shipments.cod-collection',$shipment))->assertSessionHas('success');
        $this->post(route('rider.shipments.cod-collection',$shipment))->assertSessionHas('success');
        $this->get(route('rider.shipments.show',$shipment))->assertSee('data-rider-confirm="delivered"',false);
        $this->post($url,['action'=>'delivered'])->assertSessionHas('success','Delivery completed successfully.');
        $this->post($url,['action'=>'delivered'])->assertSessionHasErrors('status');
        $this->assertSame('paid',$payment->fresh()->status);$this->assertSame(1,$shipment->events()->where('status','delivered')->count());
        $this->post($url,['action'=>'legacy_pickup'])->assertSessionHasErrors('status');
    }

    public function test_failed_delivery_form_other_notes_and_terminal_actions(): void
    {
        $rider=$this->rider();$shipment=$this->shipment($rider,'out_for_delivery');$this->actingAs($rider);
        $this->get(route('rider.shipments.show',$shipment))->assertSee('Report Delivery Problem')->assertSee('data-rider-dirty',false);
        $this->post(route('rider.shipments.parcel-action',$shipment),['action'=>'failed','reason'=>'other'])->assertSessionHasErrors('details');
        $this->post(route('rider.shipments.parcel-action',$shipment),['action'=>'failed','reason'=>'recipient_unavailable','details'=>'No one at the door'])->assertSessionHas('success','Delivery issue was reported.');
        $this->assertSame('delivery_failed',$shipment->fresh()->status);
        $this->get(route('rider.shipments.show',$shipment))->assertViewHas('actions',[])->assertSee('No one at the door');
        $this->post(route('rider.shipments.parcel-action',$shipment),['action'=>'returned'])->assertSessionHasErrors('action');
    }

    public function test_stale_version_assignment_and_disabled_account_are_rechecked_under_lock(): void
    {
        $rider=$this->rider();$shipment=$this->shipment($rider);$version=RiderPortal::version($shipment);
        $shipment->events()->create(['status'=>'updated','note'=>'Changed by Logistics']);
        $this->actingAs($rider)->post(route('rider.shipments.parcel-action',$shipment),['action'=>'accept_delivery','version'=>$version])->assertSessionHasErrors('status');
        $bound=$shipment->fresh();$shipment->update(['rider_id'=>$this->rider()->id]);
        try {app(RiderPortalActionService::class)->perform($bound,$rider,['action'=>'accept_delivery']);$this->fail('Reassigned delivery was accepted.');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame(403,$error->getStatusCode());}
        $shipment->update(['rider_id'=>$rider->id]);$rider->riderProfile->update(['account_status'=>'disabled']);
        try {app(RiderPortalActionService::class)->perform($bound,$rider,['action'=>'accept_delivery']);$this->fail('Disabled rider updated delivery.');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){$this->assertSame(403,$error->getStatusCode());}
        $this->assertNull($shipment->fresh()->delivery_accepted_at);
    }

    public function test_legacy_workflow_existing_assignments_remain_usable(): void
    {
        $rider=$this->rider();$shipment=$this->shipment($rider,'assigned');$this->actingAs($rider);$url=route('rider.shipments.parcel-action',$shipment);
        foreach(['legacy_pickup'=>'picked_up','legacy_transit'=>'in_transit','legacy_out'=>'out_for_delivery'] as $action=>$status){$this->post($url,['action'=>$action])->assertSessionHas('success');$this->assertSame($status,$shipment->fresh()->status);}
        $this->assertSame(3,$shipment->events()->count());
    }

    public function test_notifications_ownership_and_reassigned_links_are_safe(): void
    {
        $rider=$this->rider();$other=$this->rider();$shipment=$this->shipment($other);
        $notification=NotificationModel::create(['user_id'=>$other->id,'type'=>'logistics','title'=>'Private assignment','body'=>'Private','link'=>route('rider.shipments.show',$shipment)]);
        $this->actingAs($rider)->get(route('rider.notifications.open',$notification))->assertNotFound();
        $this->post(route('rider.notifications.read',$notification))->assertNotFound();
        $notification->update(['user_id'=>$rider->id]);$this->get(route('rider.notifications.open',$notification))->assertRedirect(route('rider.notifications'));
        $this->assertNull($notification->fresh()->read_at);
        $this->post(route('rider.notifications.read',$notification))->assertRedirect(route('rider.notifications'));
        $this->assertNotNull($notification->fresh()->read_at);
        $notification->update(['link'=>'https://example.test/phishing']);$this->get(route('rider.notifications.open',$notification))->assertRedirect(route('rider.notifications'));
        $this->post(route('rider.notifications.read-all'))->assertSessionHas('success');
    }

    public function test_decline_is_confirmed_scoped_stale_safe_and_recorded_once(): void
    {
        $rider=$this->rider();$shipment=$this->shipment($rider,'pickup_assigned',true);
        $this->actingAs($rider)->get(route('rider.shipments.show',$shipment))->assertSee('data-rider-confirm="accept_pickup"',false)->assertSee('rider-decline',false);
        $version=RiderPortal::version($shipment);$shipment->events()->create(['status'=>'updated']);
        $this->post(route('rider.shipments.decline',$shipment),['reason'=>'unavailable','version'=>$version])->assertSessionHasErrors('status');
        $this->post(route('rider.shipments.decline',$shipment),['reason'=>'other'])->assertSessionHasErrors('details');
        $this->post(route('rider.shipments.decline',$shipment),['reason'=>'other','details'=>'Vehicle repair'])->assertSessionHas('success');
        $this->assertNull($shipment->fresh()->pickup_rider_id);$this->assertSame('ready_for_pickup',$shipment->fresh()->status);
        $this->post(route('rider.shipments.decline',$shipment),['reason'=>'unavailable'])->assertForbidden();
        $this->assertSame(1,$shipment->events()->where('status','assignment_declined')->count());
    }

    public function test_profile_fields_are_allowlisted_and_server_errors_are_private(): void
    {
        $rider=$this->rider();$this->actingAs($rider)->put(route('rider.profile.update'),['phone'=>'09171234567','user_id'=>999,'is_active'=>false,'account_status'=>'suspended','rider_id'=>999]);
        $this->assertTrue($rider->fresh()->is_active);$this->assertSame('active',$rider->riderProfile->fresh()->account_status);
        $this->assertSame('09171234567',$rider->fresh()->phone);
        $shipment=$this->shipment($rider);
        $this->mock(RiderPortalActionService::class,fn($mock)=>$mock->shouldReceive('perform')->andThrow(new \RuntimeException('SQL confidential backend details')));
        $this->post(route('rider.shipments.parcel-action',$shipment),['action'=>'accept_delivery'])->assertStatus(503)->assertSee('Unable to complete your request.')->assertDontSee('SQL confidential');
    }

    public function test_new_rider_empty_states_profile_and_csrf_protection(): void
    {
        $rider=$this->rider();$this->actingAs($rider);
        $this->get(route('rider.dashboard'))->assertOk()->assertSee('No Active Delivery')->assertViewHas('summary',fn($s)=>array_sum($s)===0);
        foreach(['rider.jobs','rider.history','rider.tracking','rider.active','rider.notifications','rider.profile','rider.settings','rider.pods','rider.cod','rider.messages'] as $route)$this->get(route($route))->assertOk();
        $this->post(route('rider.shipments.parcel-action',123),['action'=>'delivered'])->assertNotFound();
        // Laravel normally bypasses CSRF in tests. Explicitly run the real web middleware check.
        $this->app->instance('env','production');
        $this->post(route('rider.logout'))->assertStatus(419);
        $this->post(route('rider.availability'),['_method'=>'PUT','availability'=>'off_duty'])->assertStatus(419);
        $this->post(route('rider.notifications.read',1))->assertStatus(419);
        $this->post(route('rider.login.submit'),['email'=>$rider->email,'password'=>'password'])->assertStatus(419);
        $this->app->instance('env','testing');
    }

    public function test_opt_in_browser_fixtures_use_only_in_memory_test_records(): void
    {
        if(!getenv('RIDER_PORTAL_FIXTURES'))$this->markTestSkipped('Optional browser fixtures.');
        $dir=storage_path('app/rider-portal-fixtures');if(!is_dir($dir))mkdir($dir,0777,true);
        $rider=$this->rider(['name'=>'Portal Test Rider']);$shipment=$this->shipment($rider,'out_for_delivery');
        $shipment->events()->create(['actor_id'=>$rider->id,'status'=>'out_for_delivery','note'=>'Actual recorded event']);
        $this->actingAs($rider);
        file_put_contents($dir.'/dashboard.html',$this->get(route('rider.dashboard'))->getContent());
        file_put_contents($dir.'/jobs.html',$this->get(route('rider.jobs'))->getContent());
        file_put_contents($dir.'/detail.html',$this->get(route('rider.shipments.show',$shipment))->getContent());
        ProofOfDelivery::create(['shipment_id'=>$shipment->id,'submitted_by'=>$rider->id,'recipient_name'=>'Fixture Recipient','photo_path'=>'pod/fixture.jpg','status'=>'pending','submitted_at'=>now()]);
        file_put_contents($dir.'/ready.html',$this->get(route('rider.shipments.show',$shipment))->getContent());
        $new=$this->rider(['name'=>'New Portal Rider']);$this->actingAs($new);
        file_put_contents($dir.'/empty.html',$this->get(route('rider.dashboard'))->getContent());
        file_put_contents($dir.'/profile.html',$this->get(route('rider.profile'))->getContent());
        Auth::logout();file_put_contents($dir.'/login.html',$this->get(route('rider.login'))->getContent());
        $this->assertFileExists($dir.'/detail.html');
    }
}
