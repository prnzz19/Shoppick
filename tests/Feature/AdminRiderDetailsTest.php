<?php

namespace Tests\Feature;

use App\Models\{Order,RiderProfile,SellerOrder,SellerProfile,Shipment,Store,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class AdminRiderDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_active'=>true])->assignRole('admin'));
    }

    private function rider(string $name): User
    {
        $rider=User::factory()->create(['name'=>$name,'is_active'=>true])->assignRole('rider');
        RiderProfile::create(['user_id'=>$rider->id,'account_status'=>'active','availability'=>'available','vehicle_type'=>'motorcycle','plate_number'=>'TEST-'.$rider->id]);
        return $rider;
    }

    private function shipment(User $rider, string $status='out_for_delivery', bool $pickupOnly=false): Shipment
    {
        $seller=User::factory()->create()->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Rider Shop '.$seller->id,'slug'=>'rider-shop-'.$seller->id,'status'=>'active']);
        $order=Order::create(['user_id'=>$seller->id,'order_number'=>'RIDER-ORDER-'.$seller->id,'payment_method'=>'cod','payment_status'=>'cod','total'=>100]);
        $vendor=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>'RIDER-VENDOR-'.$seller->id,'status'=>'shipped','seller_total'=>100]);
        return Shipment::create(['shipment_number'=>'RIDER-TRACK-'.$seller->id,'parcel_code'=>'RIDER-PARCEL-'.$seller->id,'order_id'=>$order->id,'seller_order_id'=>$vendor->id,'store_id'=>$store->id,'rider_id'=>$pickupOnly?null:$rider->id,'pickup_rider_id'=>$rider->id,'status'=>$status,'assigned_at'=>now()->subHour()]);
    }

    private function page(User $rider,array $params=[])
    {
        $web=$this->get(route('admin.logistics.riders.show',['rider'=>$rider->id]+$params))->assertOk();
        $json=$this->getJson(route('admin.logistics.riders.show',['rider'=>$rider->id]+$params))->assertOk();
        foreach(['shipments','activity'] as $part) $this->assertSame(view('admin.logistics.rider.'.$part,$web->original->getData())->render(),$json->json($part.'_html'));
        return $web;
    }

    public function test_same_template_renders_three_riders_with_scoped_shipments_and_actor_events(): void
    {
        $riders=[];
        foreach(['Rider Carlo','Rider Juan','Rider Maria'] as $name){$rider=$this->rider($name);$shipment=$this->shipment($rider);$shipment->events()->create(['actor_id'=>$rider->id,'status'=>'out_for_delivery','note'=>'Activity for '.$name]);$riders[]=[$rider,$shipment];}
        foreach($riders as [$rider,$shipment]){
            $page=$this->page($rider)->assertViewIs('admin.logistics.rider')->assertSee($rider->name)->assertSee($rider->email)->assertSee($shipment->shipment_number)->assertSee('Activity for '.$rider->name);
            foreach($riders as [$other,$otherShipment])if($other->id!==$rider->id)$page->assertDontSee($otherShipment->shipment_number)->assertDontSee('Activity for '.$other->name);
            $this->assertSame(1,$page->viewData('totalAssigned'));
            $this->assertSame(1,$page->viewData('rider')->current_assignments);
            $page->assertSee(route('admin.logistics.deliveries.show',$shipment));
            $this->get(route('admin.logistics.deliveries.show',$shipment))->assertOk();
        }
    }

    public function test_new_rider_without_profile_or_records_has_safe_compact_empty_states(): void
    {
        $rider=User::factory()->create(['name'=>'Future Rider','is_active'=>true])->assignRole('rider');
        $page=$this->page($rider)->assertSee('No active delivery')->assertSee('No shipments have been assigned to this rider yet.')->assertSee('No delivery activity has been recorded for this rider yet.')->assertSee('No activity recorded');
        $this->assertSame(0,$page->viewData('totalAssigned'));
        $this->assertSame(0,$page->viewData('rider')->current_assignments);
        $this->assertSame(0,$page->viewData('rider')->completed_deliveries);
        $this->assertNull($page->viewData('currentDelivery'));
    }

    public function test_filters_search_real_tracking_order_and_shop_and_keep_summary_unfiltered(): void
    {
        $rider=$this->rider('Filter Rider');$active=$this->shipment($rider);$done=$this->shipment($rider,'delivered');$pickup=$this->shipment($rider,'pickup_accepted',true);
        foreach([$active->shipment_number,$active->parcel_code,$active->order->order_number,$active->store->name] as $term){$page=$this->page($rider,['q'=>$term]);$this->assertSame([$active->id],$page->viewData('shipments')->pluck('id')->all());$this->assertSame(3,$page->viewData('totalAssigned'));}
        $page=$this->page($rider,['status'=>'delivered']);$this->assertSame([$done->id],$page->viewData('shipments')->pluck('id')->all());$this->assertSame($active->id,$page->viewData('currentDelivery')->id);$this->assertSame(2,$page->viewData('rider')->current_assignments);$this->assertSame(1,$page->viewData('rider')->completed_deliveries);
        $this->assertSame([$pickup->id],$this->page($rider,['status'=>'pickup_accepted'])->viewData('shipments')->pluck('id')->all());
        $this->page($rider,['q'=>'missing'])->assertSee('No assigned shipments match your filters.');
        $this->assertSame(0,$this->page($rider,['q'=>'0'])->viewData('shipments')->total());
        $this->getJson(route('admin.logistics.riders.show',['rider'=>$rider->id,'status'=>'invented']))->assertUnprocessable();
        $this->getJson(route('admin.logistics.riders.show',['rider'=>$rider->id,'q'=>str_repeat('a',101)]))->assertUnprocessable();
    }

    public function test_shipments_and_events_are_limited_to_ten_with_independent_filter_preserving_pagination(): void
    {
        $rider=$this->rider('Busy Rider');
        for($i=0;$i<13;$i++){$shipment=$this->shipment($rider,'delivered');$shipment->events()->create(['actor_id'=>$rider->id,'status'=>'delivered','note'=>'Recorded '.$i]);}
        $page=$this->page($rider,['status'=>'delivered','page'=>2,'activity_page'=>2]);
        foreach(['shipments','events'] as $key){$rows=$page->viewData($key);$this->assertSame(3,$rows->count());$this->assertSame(13,$rows->total());$this->assertSame(10,$rows->perPage());$this->assertStringContainsString('status=delivered',$rows->url(1));}
        $this->assertStringContainsString('activity_page=2',$page->viewData('shipments')->url(1));
        $this->assertStringContainsString('page=2',$page->viewData('events')->url(1));
        $this->assertSame(10,$this->page($rider)->viewData('events')->count());
    }

    public function test_related_rows_are_eager_loaded_and_monitoring_requests_only_read_business_data(): void
    {
        $rider=$this->rider('Read Only Rider');$shipment=$this->shipment($rider);$shipment->events()->create(['actor_id'=>$rider->id,'status'=>'out_for_delivery','note'=>'Read only']);
        DB::enableQueryLog();DB::flushQueryLog();
        $page=$this->page($rider);
        foreach(DB::getQueryLog() as $query)$this->assertMatchesRegularExpression('/^select\b/i',$query['query']);
        DB::disableQueryLog();
        foreach($page->viewData('shipments') as $row)foreach(['order','store','rider','pickupRider'] as $relation)$this->assertTrue($row->relationLoaded($relation));
        foreach($page->viewData('events') as $event)$this->assertTrue($event->relationLoaded('shipment'));
        foreach(['order','store'] as $relation)$this->assertTrue($page->viewData('currentDelivery')->relationLoaded($relation));
    }

    public function test_json_and_html_remain_admin_only_and_non_rider_ids_return_404(): void
    {
        $rider=$this->rider('Protected Rider');$url=route('admin.logistics.riders.show',$rider);
        foreach(['buyer','seller','logistics','rider'] as $role){$this->actingAs(User::factory()->create(['is_active'=>true])->assignRole($role));$this->get($url)->assertForbidden();$this->getJson($url)->assertForbidden();}
        $admin=User::factory()->create(['is_active'=>true])->assignRole('admin');$this->actingAs($admin);$this->getJson(route('admin.logistics.riders.show',$admin))->assertNotFound();
        auth()->logout();$this->getJson($url)->assertUnauthorized();
    }

    public function test_export_isolated_demo_rider_previews_only_when_requested(): void
    {
        if(getenv('SHOPPICK_RIDER_PREVIEWS')!=='1')$this->markTestSkipped('Opt-in isolated preview generation.');
        Storage::fake('local');$this->seed(\Database\Seeders\LogisticsDemoSeeder::class);
        $dir=storage_path('app/rider-detail-fixtures');if(!is_dir($dir))mkdir($dir,0777,true);
        $riders=User::whereIn('name',['Rider Carlo','Rider Juan','Rider Maria'])->get();
        $empty=User::factory()->create(['name'=>'New Rider','is_active'=>true])->assignRole('rider');$riders->push($empty);
        $responses=[];$names=[];
        foreach($riders as $rider){$key=\Illuminate\Support\Str::slug($rider->name);$names[$key]=$rider->id;file_put_contents($dir.'/'.$key.'.html',$this->get(route('admin.logistics.riders.show',$rider))->assertOk()->getContent());}
        $carlo=$riders->firstWhere('name','Rider Carlo');
        $shipment=$this->shipment($carlo);for($i=0;$i<12;$i++){$this->shipment($carlo,'delivered');$shipment->events()->create(['actor_id'=>$carlo->id,'status'=>'delivered','note'=>'Preview event '.$i]);}
        file_put_contents($dir.'/busy.html',$this->get(route('admin.logistics.riders.show',$carlo))->assertOk()->getContent());
        foreach([[],['status'=>'delivered'],['q'=>'RIDER-TRACK'],['q'=>'missing'],['page'=>'2'],['activity_page'=>'2'],['status'=>'delivered','activity_page'=>'2'],['status'=>'delivered','page'=>'2','activity_page'=>'2']] as $query)$responses[http_build_query($query)]=$this->getJson(route('admin.logistics.riders.show',['rider'=>$carlo->id]+$query))->assertOk()->json();
        file_put_contents($dir.'/responses.json',json_encode($responses,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));file_put_contents($dir.'/riders.json',json_encode($names));
    }
}
