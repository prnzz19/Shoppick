<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, LogisticsInvoice, LogisticsProvider, Order, Permission, ProofOfDelivery, RiderProfile, Role, SellerOrder, SellerProfile, Shipment, Store, User, Vehicle};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsProviderOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin','buyer','seller','logistics','rider'] as $slug) Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
        foreach (['manage_riders','view_shipments','manage_fleet','review_pod','manage_logistics_billing'] as $slug) {
            $permission=Permission::create(['name'=>$slug,'slug'=>$slug,'guard_name'=>'web','group'=>'Logistics']);
            Role::where('slug','logistics')->first()->permissions()->attach($permission);
        }
        Storage::fake('local');
    }

    private function provider(string $name): array
    {
        $user=User::factory()->create(['is_active'=>true,'registration_type'=>'logistics','registration_status'=>'approved']);
        $user->assignRole('logistics');
        $provider=LogisticsProvider::create(['owner_user_id'=>$user->id,'company_name'=>$name,'status'=>'approved','is_active'=>true]);
        $provider->users()->attach($user->id,['membership_role'=>'owner','is_active'=>true]);
        return [$user,$provider];
    }

    private function application(LogisticsProvider $provider, string $email): RiderProfile
    {
        $user=User::factory()->create(['email'=>$email,'is_active'=>false,'registration_type'=>'rider','registration_status'=>'pending','valid_id_path'=>'rider-documents/valid-ids/id.pdf']);
        $user->assignRole('rider');
        foreach (['valid-ids/id.pdf','licenses/front.pdf','licenses/back.pdf','proof/address.pdf','vehicles/orcr.pdf'] as $path) Storage::disk('local')->put('rider-documents/'.$path,'private');
        return RiderProfile::create([
            'user_id'=>$user->id,'logistics_provider_id'=>$provider->id,'logistics_user_id'=>$provider->owner_user_id,
            'account_status'=>'inactive','availability'=>'unavailable','requested_service_area'=>'Manila','requested_rider_type'=>'both',
            'driver_license_number'=>'DL-'.$user->id,'driver_license_expires_at'=>now()->addYear(),'driver_license_front_path'=>'rider-documents/licenses/front.pdf','driver_license_back_path'=>'rider-documents/licenses/back.pdf','driver_license_status'=>'pending_review',
            'proof_of_address_path'=>'rider-documents/proof/address.pdf','or_cr_path'=>'rider-documents/vehicles/orcr.pdf',
        ]);
    }

    private function shipment(LogisticsProvider $provider): Shipment
    {
        $seller=User::factory()->create();$seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Provider Isolation Shop','slug'=>'provider-isolation-'.$seller->id,'status'=>'active']);
        $buyer=User::factory()->create();$buyer->assignRole('buyer');
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>'ISO-'.$buyer->id,'status'=>'processing','payment_method'=>'cod','payment_status'=>'cod','subtotal'=>100,'total'=>100,'buyer_name'=>$buyer->name,'buyer_phone'=>'09170000000','shipping_address'=>[]]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>$order->order_number.'-S1','status'=>'ready_to_ship','subtotal'=>100,'seller_total'=>100]);
        return Shipment::create(['logistics_provider_id'=>$provider->id,'shipment_number'=>'ISO-SHIP-'.$seller->id,'parcel_code'=>'ISO-PARCEL-'.$seller->id,'seller_order_id'=>$sellerOrder->id,'order_id'=>$order->id,'store_id'=>$store->id,'status'=>'ready_for_pickup']);
    }

    public function test_provider_cannot_read_or_review_another_providers_application_or_documents(): void
    {
        [$managerA,$providerA]=$this->provider('Provider A');
        [, $providerB]=$this->provider('Provider B');
        $applicationB=$this->application($providerB,'rider-b@example.test');

        $this->actingAs($managerA)->get(route('logistics.rider-applications',['view'=>$applicationB->id]))->assertNotFound();
        $this->get(route('logistics.riders',['view'=>$applicationB->id]))->assertNotFound();
        $this->get(route('logistics.riders.valid-id',$applicationB))->assertNotFound();
        $this->post(route('logistics.riders.application.review',$applicationB),['decision'=>'rejected','reason'=>'Cross-provider attempt'])->assertNotFound();
        $this->assertSame('pending',$applicationB->user->fresh()->registration_status);
        $this->assertNotSame($providerA->id,$applicationB->logistics_provider_id);
    }

    public function test_selected_provider_can_approve_and_confirm_operational_fields(): void
    {
        [$manager,$provider]=$this->provider('Provider A');
        $hub=LogisticsHub::create(['logistics_provider_id'=>$provider->id,'code'=>'A-HUB','name'=>'Provider A Hub','address'=>'Manila','status'=>'active']);
        $application=$this->application($provider,'rider-a@example.test');

        $this->actingAs($manager)->post(route('logistics.riders.application.review',$application),[
            'decision'=>'approved','approved_service_area'=>'Manila North','hub_id'=>$hub->id,'rider_type'=>'both',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rider_profiles',['id'=>$application->id,'logistics_provider_id'=>$provider->id,'approved_service_area'=>'Manila North','hub_id'=>$hub->id,'rider_type'=>'both','account_status'=>'active']);
        $this->assertDatabaseHas('users',['id'=>$application->user_id,'registration_status'=>'approved','is_active'=>true]);
    }

    public function test_selected_provider_can_reject_but_admin_cannot_review_a_rider_application(): void
    {
        [$manager,$provider]=$this->provider('Provider A');
        $application=$this->application($provider,'rider-reject@example.test');
        $admin=User::factory()->create(['is_active'=>true]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('logistics.riders.application.review',$application),[
            'decision'=>'rejected','reason'=>'Admin must remain read-only.',
        ])->assertForbidden();
        $this->actingAs($manager)->post(route('logistics.riders.application.review',$application),[
            'decision'=>'rejected','reason'=>'Documents could not be verified.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users',['id'=>$application->user_id,'registration_status'=>'rejected','is_active'=>false]);
        $this->assertDatabaseHas('rider_profiles',['id'=>$application->id,'logistics_provider_id'=>$provider->id,'account_status'=>'inactive','driver_license_status'=>'rejected']);
    }

    public function test_provider_cannot_open_another_providers_shipment_and_admin_has_provider_review_route(): void
    {
        [$managerA]=$this->provider('Provider A');
        [, $providerB]=$this->provider('Provider B');
        $shipment=$this->shipment($providerB);

        $this->actingAs($managerA)->get(route('logistics.shipments.show',$shipment))->assertNotFound();
        $this->assertTrue(Route::has('admin.logistics.providers.review'));
        $route=app('router')->getRoutes()->getByName('admin.logistics.providers.document');
        $this->assertSame(['GET','HEAD'],$route->methods());
    }

    public function test_provider_cannot_open_another_providers_fleet_pod_or_billing_records(): void
    {
        [$managerA]=$this->provider('Provider A');
        [, $providerB]=$this->provider('Provider B');
        $shipment=$this->shipment($providerB);
        $vehicle=Vehicle::create(['logistics_provider_id'=>$providerB->id,'code'=>'B-VEHICLE','type'=>'van','status'=>'available']);
        Storage::disk('local')->put('pod/provider-b.jpg','private pod');
        $pod=ProofOfDelivery::create(['shipment_id'=>$shipment->id,'submitted_by'=>$providerB->owner_user_id,'recipient_name'=>'Provider B Buyer','photo_path'=>'pod/provider-b.jpg','status'=>'pending','submitted_at'=>now()]);
        $invoice=LogisticsInvoice::create(['logistics_provider_id'=>$providerB->id,'invoice_number'=>'B-INVOICE','shipment_id'=>$shipment->id,'delivery_fee'=>100,'total'=>100,'status'=>'issued']);

        $this->actingAs($managerA)->get(route('logistics.fleet',['view'=>$vehicle->id]))->assertNotFound();
        $this->get(route('logistics.pod',['view'=>$pod->id]))->assertNotFound();
        $this->get(route('logistics.pod.file',$pod))->assertNotFound();
        $this->post(route('logistics.pod.review',$pod),['status'=>'approved'])->assertNotFound();
        $this->get(route('logistics.billing',['view'=>$invoice->id]))->assertNotFound();
        $this->put(route('logistics.billing.update',$invoice),['adjustments'=>0,'status'=>'paid'])->assertNotFound();
    }

    public function test_provider_cannot_bind_foreign_hub_or_vehicle_to_its_rider(): void
    {
        [$managerA,$providerA]=$this->provider('Provider A');
        [, $providerB]=$this->provider('Provider B');
        $application=$this->application($providerA,'rider-owned@example.test');
        $application->user->update(['registration_status'=>'approved','is_active'=>true]);
        $application->update(['account_status'=>'active','availability'=>'available']);
        $hubB=LogisticsHub::create(['logistics_provider_id'=>$providerB->id,'code'=>'B-HUB','name'=>'Provider B Hub','address'=>'Manila','status'=>'active']);
        $vehicleB=Vehicle::create(['logistics_provider_id'=>$providerB->id,'code'=>'B-VEHICLE-2','type'=>'van','status'=>'available']);

        $this->actingAs($managerA)->put(route('logistics.riders.update',$application),[
            'account_status'=>'active','availability'=>'available','hub_id'=>$hubB->id,'vehicle_id'=>$vehicleB->id,
        ])->assertSessionHasErrors(['hub_id','vehicle_id']);

        $this->assertDatabaseMissing('rider_profiles',['id'=>$application->id,'hub_id'=>$hubB->id,'vehicle_id'=>$vehicleB->id]);
    }
}
