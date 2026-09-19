<?php
namespace Tests\Feature;

use App\Models\{Category,Order,OrderItem,Permission,Product,ProofOfDelivery,RiderProfile,Role,SellerOrder,SellerProfile,Shipment,Store,User,Vehicle};
use App\Services\SellerOrderStatusService;
use App\Services\ShipmentService;
use App\Services\OrderProgressService;
use Database\Seeders\LogisticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function licensedProfile(User $user, array $extra=[]): RiderProfile
    {
        return RiderProfile::create($extra+['user_id'=>$user->id,'driver_license_number'=>'TEST-'.$user->id,'driver_license_classification'=>'Test classification','driver_license_expires_at'=>now()->addYear(),'driver_license_front_path'=>'rider-documents/test-front','driver_license_back_path'=>'rider-documents/test-back','driver_license_status'=>'verified']);
    }

    protected function setUp():void
    {
        parent::setUp();
        foreach(['buyer','seller','admin','logistics','rider'] as $slug) Role::create(['name'=>ucfirst(str_replace('_',' ',$slug)),'slug'=>$slug,'guard_name'=>'web']);
        foreach(['view_logistics_dashboard','view_shipments','manage_shipments','assign_shipments','manage_fleet','manage_riders','manage_hubs','review_pod','manage_logistics_billing','view_logistics_reports','manage_logistics_settings','manage_ai_logistics'] as $slug){$p=Permission::create(['name'=>$slug,'slug'=>$slug,'guard_name'=>'web','group'=>'Logistics']);Role::where('slug','logistics')->first()->permissions()->attach($p);}
    }

    public function test_one_fulfillment_chain_is_shared_across_seller_logistics_rider_buyer_and_admin():void
    {
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');
        $seller=User::factory()->create(['is_active'=>true]);$seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Connected Shop','slug'=>'connected-shop','status'=>'active']);
        $category=Category::create(['name'=>'Logistics Test','slug'=>'logistics-test','is_active'=>true]);
        $product=Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Shared Product','slug'=>'shared-product','price'=>500,'stock'=>10,'is_active'=>true]);
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>'SP-LOG-1','status'=>'pending','payment_method'=>'cod','payment_status'=>'cod','subtotal'=>500,'shipping_fee'=>60,'total'=>560,'buyer_name'=>$buyer->name,'buyer_phone'=>'09170000000','shipping_address'=>['address_line'=>'1 Buyer St','city'=>'Manila']]);
        $order->payments()->create(['method'=>'cod','status'=>'pending','gateway'=>'cod','amount'=>560]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>'SP-LOG-1-S1','status'=>'packed','subtotal'=>500,'shipping_fee'=>60,'seller_total'=>500]);
        OrderItem::create(['order_id'=>$order->id,'seller_order_id'=>$sellerOrder->id,'product_id'=>$product->id,'product_name'=>$product->name,'price'=>500,'quantity'=>1,'subtotal'=>500,'total'=>500]);

        app(SellerOrderStatusService::class)->transition($sellerOrder,$seller,'ready_to_ship');
        $shipment=Shipment::where('seller_order_id',$sellerOrder->id)->firstOrFail();
        $this->assertSame($order->id,$shipment->order_id);$this->assertSame('ready_for_pickup',$shipment->status);
        $this->assertSame('ready_to_ship',$order->fresh()->status);
        $this->assertSame('ready_to_ship',app(OrderProgressService::class)->tracker($order->fresh())['status']);
        app(ShipmentService::class)->createForSellerOrder($sellerOrder->fresh(),$seller);
        $this->assertSame(1,Shipment::where('seller_order_id',$sellerOrder->id)->count());

        $logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        $rider=User::factory()->create(['is_active'=>true]);$rider->assignRole('rider');$this->licensedProfile($rider);
        $vehicle=Vehicle::create(['code'=>'MOTO-001','type'=>'motorcycle','status'=>'available']);
        app(ShipmentService::class)->assign($shipment,$logistics,$rider,$vehicle);
        $assignment=$shipment->fresh()->assignments()->firstOrFail();
        $this->assertSame($rider->id,$shipment->fresh()->rider_id);$this->assertSame('in_use',$vehicle->fresh()->status);
        $this->assertSame('ready_to_ship',$order->fresh()->status,'Assignment must not appear shipped to the Buyer.');
        $otherRider=User::factory()->create(['is_active'=>true]);$otherRider->assignRole('rider');$this->licensedProfile($otherRider);
        $this->actingAs($otherRider)->get(route('rider.shipments.show',$shipment))->assertForbidden();
        $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk()->assertSee('CURRENT JOB')->assertSee('rider-bottom-nav',false);

        app(ShipmentService::class)->transition($shipment->fresh(),$logistics,'picked_up');
        $this->assertSame('shipped',$order->fresh()->status);
        $this->actingAs($buyer)->get(route('orders.show',$order->order_number))->assertOk()->assertSee('aria-current="step"',false)->assertSee('Shipped');
        app(ShipmentService::class)->transition($shipment->fresh(),$logistics,'in_transit');
        app(ShipmentService::class)->transition($shipment->fresh(),$rider,'out_for_delivery');
        app(\App\Services\CodCollectionService::class)->collect($shipment->fresh(),$rider);
        $this->assertDatabaseHas('payments',['order_id'=>$order->id,'remittance_status'=>'pending','collected_by'=>$rider->id]);
        $otherBuyer=User::factory()->create(['is_active'=>true]);$otherBuyer->assignRole('buyer');
        $this->actingAs($rider)->postJson('/rider/shipments/'.$shipment->id.'/location',['latitude'=>14.5995,'longitude'=>120.9842])->assertNotFound();
        $this->actingAs($otherBuyer)->getJson('/account/orders/'.$order->order_number.'/shipments/'.$shipment->id.'/tracking')->assertNotFound();
        $this->actingAs($logistics)->getJson('/logistics/tracking/'.$shipment->id.'/feed')->assertNotFound();
        Storage::fake('local');
        $this->actingAs($rider)->post(route('rider.shipments.pod',$shipment),['recipient_name'=>'Maria Buyer','photo'=>UploadedFile::fake()->create('delivery.jpg',10,'image/jpeg')])->assertSessionHasNoErrors();
        app(ShipmentService::class)->transition($shipment->fresh(),$rider,'delivered');
        $this->assertSame('delivered',$order->fresh()->status);
        $pod=$shipment->fresh()->proofOfDelivery()->firstOrFail();$invoice=$shipment->fresh()->invoice()->firstOrFail();
        $this->assertEquals(60,$invoice->total);$this->assertNotEquals($order->total,$invoice->total);
        $payment=$order->payments()->where('method','cod')->firstOrFail();
        $this->actingAs($logistics)->post(route('logistics.cod.remit',$payment))->assertSessionHasNoErrors();
        $this->assertSame('remitted',$payment->fresh()->remittance_status);

        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->actingAs($admin)->get(route('logistics.dispatch'))->assertForbidden();
        $this->actingAs($buyer)->get(route('orders.show',$order->order_number))->assertOk()->assertSee($shipment->shipment_number)->assertSee('Delivered')->assertSee('Status updates are shown')->assertDontSee('Live Rider location')->assertDontSee('GPS');
        $this->actingAs($seller)->get(route('seller.orders.show',$sellerOrder))->assertOk()->assertSee($shipment->shipment_number)->assertSee('Delivered');
        $this->actingAs($logistics)->get(route('logistics.shipments.show',$shipment))->assertOk()->assertSee($order->order_number)->assertSee($rider->name);

        $this->actingAs($buyer)->post(route('orders.confirm',$order->order_number))->assertSessionHasNoErrors();
        $this->assertSame('completed',$order->fresh()->status);
        $this->assertSame('completed',$sellerOrder->fresh()->status);
        $this->get(route('orders.show',$order->order_number))->assertOk()->assertSee('Completed')->assertSee('aria-current="step"',false);

        $this->assertSame($shipment->id,$assignment->shipment_id);$this->assertSame($shipment->id,$pod->shipment_id);$this->assertSame($shipment->id,$invoice->shipment_id);
    }

    public function test_role_redirects_and_logistics_pages_are_protected():void
    {
        $logistics=User::factory()->create(['email'=>'logistics@shoppick.test','password'=>'password','is_active'=>true]);$logistics->assignRole('logistics');
        $this->post(route('login'),['email'=>$logistics->email,'password'=>'password'])->assertRedirect(route('logistics.dashboard'));
        foreach(['logistics.dashboard','logistics.shipments.index','logistics.dispatch','logistics.fleet','logistics.riders','logistics.tracking','logistics.hubs','logistics.pod','logistics.billing','logistics.reports','logistics.ai','logistics.notifications','logistics.settings'] as $route)$this->get(route($route))->assertOk();
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');$this->actingAs($buyer)->get(route('logistics.dashboard'))->assertForbidden();
    }

    public function test_logistics_creates_a_structured_active_rider_without_public_registration():void
    {
        Storage::fake('local');
        $logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        $payload=['first_name'=>'Juan','middle_initial'=>'D','last_name'=>'Rider','sex'=>'male','birthday'=>'1995-06-15','email'=>'juan.rider@example.test','phone'=>'09171234567','password'=>'SecurePass123!','password_confirmation'=>'SecurePass123!','address_line'=>'12 Rider Street','region'=>'Region IV-A (CALABARZON)','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0434000000','city'=>'Cavinti','city_code'=>'0434060000','barangay'=>'Mahipon','barangay_code'=>'0434060150','postal_code'=>'4013','driver_license_number'=>'N01-23-456789','driver_license_classification'=>'Verified test classification','driver_license_expires_at'=>now()->addYear()->format('Y-m-d'),'driver_license_front'=>UploadedFile::fake()->create('license-front.pdf',20,'application/pdf'),'driver_license_back'=>UploadedFile::fake()->create('license-back.pdf',20,'application/pdf'),'age'=>'99'];
        $this->actingAs($logistics)->post(route('logistics.riders.store'),$payload)->assertSessionHasNoErrors();
        $rider=User::where('email',$payload['email'])->firstOrFail();
        $this->assertTrue($rider->hasRole('rider'));$this->assertSame(31,$rider->age);$this->assertSame('active',$rider->riderProfile->account_status);$this->assertSame('unavailable',$rider->riderProfile->availability);$this->assertSame('pending_review',$rider->riderProfile->driver_license_status);$this->assertSame('Mahipon',$rider->defaultAddress()->barangay);
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login'),['email'=>$payload['email'],'password'=>$payload['password']])->assertRedirect(route('rider.dashboard'));
        $this->get(route('logistics.dashboard'))->assertForbidden();$this->get(route('admin.dashboard'))->assertForbidden();$this->get(route('seller.dashboard'))->assertForbidden();
        $this->post(route('logout'))->assertRedirect();$this->get(route('register'))->assertOk()->assertDontSee('Register as Rider');
    }

    public function test_sorting_center_parcel_flow_separates_pickup_and_area_delivery_riders():void
    {
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');
        $seller=User::factory()->create(['is_active'=>true]);$seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'ERP Shop','slug'=>'erp-shop','status'=>'active']);
        $category=Category::create(['name'=>'ERP Parcel','slug'=>'erp-parcel','is_active'=>true]);
        $product=Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>'Parcel Item','slug'=>'parcel-item','price'=>100,'stock'=>2,'is_active'=>true]);
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>'ERP-ORDER-1','status'=>'packed','payment_method'=>'card','payment_status'=>'paid','subtotal'=>100,'shipping_fee'=>20,'total'=>120,'buyer_name'=>$buyer->name,'buyer_phone'=>'09170000000','shipping_address'=>['province'=>'Laguna','city'=>'Calamba','barangay'=>'Real','address_line'=>'1 Main St']]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>'ERP-SELLER-1','status'=>'ready_to_ship','subtotal'=>100,'shipping_fee'=>20,'seller_total'=>100]);
        OrderItem::create(['order_id'=>$order->id,'seller_order_id'=>$sellerOrder->id,'product_id'=>$product->id,'product_name'=>$product->name,'price'=>100,'quantity'=>1,'subtotal'=>100,'total'=>100]);
        $shipment=app(ShipmentService::class)->createForSellerOrder($sellerOrder,$seller);
        $logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        $pickup=User::factory()->create(['is_active'=>true]);$pickup->assignRole('rider');$this->licensedProfile($pickup);
        $delivery=User::factory()->create(['is_active'=>true]);$delivery->assignRole('rider');$deliveryProfile=$this->licensedProfile($delivery);
        $area=\App\Models\DeliveryArea::create(['code'=>'CAL-REAL','name'=>'Calamba - Real','province'=>'Laguna','municipality'=>'Calamba','barangays'=>['Real']]);$deliveryProfile->deliveryAreas()->attach($area);
        $flow=app(\App\Services\ParcelWorkflowService::class);
        $sidebar=app(\App\Services\LogisticsSidebarCounts::class);
        $this->assertSame(1,$sidebar->for($logistics)['pickup_requests']);
        $flow->assignPickup($shipment,$logistics,$pickup);
        $this->assertSame(0,$sidebar->for($logistics)['pickup_requests']);$this->assertSame(1,$sidebar->for($logistics)['incoming_parcels']);
        try{$flow->confirmPickup($shipment->fresh(),$pickup,$shipment->parcel_code);$this->fail('Pickup was allowed before acceptance.');}catch(\Illuminate\Validation\ValidationException $exception){$this->assertArrayHasKey('status',$exception->errors());}
        try{$flow->receive($shipment->fresh(),$logistics);$this->fail('Sorting Center receipt was allowed before pickup.');}catch(\Illuminate\Validation\ValidationException $exception){$this->assertArrayHasKey('status',$exception->errors());}
        $flow->acceptPickup($shipment->fresh(),$pickup);$this->assertNotNull($shipment->fresh()->pickup_accepted_at);$this->assertDatabaseHas('shipment_events',['shipment_id'=>$shipment->id,'status'=>'pickup_accepted','actor_id'=>$pickup->id]);
        $flow->confirmPickup($shipment->fresh(),$pickup,$shipment->parcel_code);$flow->arriveSortingCenter($shipment->fresh(),$pickup);
        $this->assertSame(1,$sidebar->for($logistics)['incoming_parcels']);
        $flow->receive($shipment->fresh(),$logistics);
        $this->assertSame(0,$sidebar->for($logistics)['incoming_parcels']);$this->assertSame(1,$sidebar->for($logistics)['sorting']);
        $flow->confirmScan($shipment->fresh(),$logistics,$shipment->parcel_code);$flow->sort($shipment->fresh(),$logistics,$area);
        $this->assertSame(0,$sidebar->for($logistics)['sorting']);$this->assertSame(1,$sidebar->for($logistics)['delivery_assignment']);
        $flow->assignDelivery($shipment->fresh(),$logistics,$delivery);
        $this->assertSame(0,$sidebar->for($logistics)['delivery_assignment']);$this->assertSame(1,$sidebar->for($logistics)['delivery_monitoring']);
        $flow->acceptDelivery($shipment->fresh(),$delivery);$flow->startDelivery($shipment->fresh(),$delivery);
        $this->assertSame(1,$sidebar->for($logistics)['delivery_monitoring']);
        ProofOfDelivery::create(['shipment_id'=>$shipment->id,'submitted_by'=>$delivery->id,'recipient_name'=>'Test Buyer','photo_path'=>'pod/test.jpg','status'=>'pending','submitted_at'=>now()]);
        $flow->deliver($shipment->fresh(),$delivery);
        $this->assertSame(0,$sidebar->for($logistics)['delivery_monitoring']);
        $pendingRider=User::factory()->create(['is_active'=>false,'registration_type'=>'rider','registration_status'=>'pending']);$pendingRider->assignRole('rider');RiderProfile::create(['user_id'=>$pendingRider->id,'account_status'=>'inactive','availability'=>'unavailable']);
        \App\Services\NotificationService::send($logistics->id,'Unread logistics message','A new operational message is waiting.');
        $this->assertSame(1,$sidebar->for($logistics)['pending_riders']);$this->assertSame(1,$sidebar->for($logistics)['unread_messages']);
        $shipment->refresh();$this->assertSame('delivered',$shipment->status);$this->assertSame($pickup->id,$shipment->pickup_rider_id);$this->assertSame($delivery->id,$shipment->rider_id);$this->assertSame($area->id,$shipment->delivery_area_id);$this->assertNotNull($shipment->received_at);$this->assertNotNull($shipment->sorted_at);$this->assertSame('delivered',$order->fresh()->status);
        $this->actingAs($logistics)->get(route('logistics.tracking',['shipment'=>$shipment->id]))->assertOk()->assertSee('✓ Pickup Accepted')->assertSee($pickup->name)->assertSee($delivery->name)->assertSee('View Parcel')->assertSee('Completed')->assertDontSee('Current Hub')->assertDontSee('POD Status');
        $this->actingAs($buyer)->post(route('orders.confirm',$order->order_number))->assertSessionHasNoErrors();
        $this->assertSame('completed',$shipment->fresh()->status);$this->assertDatabaseHas('shipment_events',['shipment_id'=>$shipment->id,'status'=>'completed','actor_id'=>$buyer->id]);
        $this->actingAs($logistics)->get(route('logistics.tracking',['shipment'=>$shipment->id]))->assertOk()->assertSee('● Completed · Current')->assertSee('Buyer confirmed receipt of the Parcel.');
        $this->actingAs($logistics)->get(route('logistics.pickup-requests'))->assertOk()->assertSee('Pickup Requests')->assertSee('logistics-count',false)->assertSee('Chat / Messaging');
        $this->get(route('logistics.sorting'))->assertOk()->assertSee('Parcel Sorting');$this->get(route('logistics.delivery-assignment'))->assertOk()->assertSee('Delivery Assignment');$this->get(route('logistics.sorting-reports'))->assertOk()->assertSee('Delivery Reports');
    }

    public function test_development_logistics_demo_is_connected_and_idempotent():void
    {
        $this->seed(LogisticsDemoSeeder::class);
        $this->seed(LogisticsDemoSeeder::class);

        $this->assertSame(8,Shipment::where('parcel_code','like','PARCEL-20%')->count());
        $this->assertSame(3,\App\Models\DeliveryArea::where('code','like','DEMO-AREA-%')->count());
        $this->assertDatabaseMissing('categories',['slug'=>'logistics-demo','is_active'=>true]);
        $this->assertSame(0,Product::where('slug','like','logistics-demo-item-%')->where('is_active',true)->count());
        foreach(['ready_for_pickup','picked_up','at_sorting_center','sorted','assigned_to_rider','out_for_delivery','delivery_failed','returned'] as $status)$this->assertDatabaseHas('shipments',['status'=>$status]);
        $sortedDemo=Shipment::where('parcel_code','PARCEL-2004')->firstOrFail();
        $this->assertNotNull($sortedDemo->pickup_accepted_at);
        $this->assertSame(['ready_for_pickup','pickup_assigned','pickup_accepted','picked_up','at_sorting_center','sorted'],$sortedDemo->events()->orderBy('created_at')->pluck('status')->all());

        $loads=Shipment::whereIn('shipment_number',['LOAD-1001','LOAD-1002','LOAD-1003'])->with(['order','store','rider','vehicle','proofOfDelivery','invoice'])->get()->keyBy('shipment_number');
        $this->assertCount(3,$loads);
        $this->assertSame('delivered',$loads['LOAD-1001']->status);
        $this->assertSame('in_transit',$loads['LOAD-1002']->status);
        $this->assertSame('exception',$loads['LOAD-1003']->status);
        $this->assertSame('Panda Picks',$loads['LOAD-1001']->store->name);
        $this->assertSame('Rider Maria',$loads['LOAD-1002']->rider->name);
        $this->assertSame('VAN-001',$loads['LOAD-1002']->vehicle->code);
        $this->assertSame('approved',$loads['LOAD-1001']->proofOfDelivery->status);
        $this->assertSame('rejected',$loads['LOAD-1003']->proofOfDelivery->status);
        $this->assertEquals(70,$loads['LOAD-1003']->invoice->total);
        $this->assertSame(3,Vehicle::whereIn('code',['MOTO-001','VAN-001','MOTO-002'])->count());
        $this->assertSame(3,\App\Models\LogisticsHub::where('code','like','DEMO-%')->count());
        $this->assertSame(12,\App\Models\LogisticsInsight::where('explanation','like','DEMO ONLY%')->count());
        $this->assertSame(3,\App\Models\NotificationModel::where('type','like','demo-%')->count());
        $this->assertSame(0,\Illuminate\Support\Facades\DB::table('shipment_tracking_points')->whereIn('shipment_id',$loads->pluck('id'))->count(),'Deprecated tracking storage must remain unused.');

        $logistics=User::where('email','logistics@shoppick.test')->firstOrFail();
        $this->actingAs($logistics)->get(route('logistics.shipments.index',['q'=>'LOAD-1001']))->assertOk()->assertSee('Orders / Loads')->assertSee('LOAD-1001')->assertDontSee('LOAD-1002');
        $this->get(route('logistics.shipments.index',['status'=>'in_transit']))->assertOk()->assertSee('LOAD-1002')->assertDontSee('LOAD-1003');
        $this->get(route('logistics.shipments.index',['view'=>$loads['LOAD-1003']->id]))->assertOk()->assertSee('Load Details')->assertSee('Recipient unavailable')->assertSee('Tracking')->assertSee('Documents');
        $this->get(route('logistics.dispatch'))->assertOk()->assertSee('LOAD-1001')->assertSee('LOAD-1002')->assertSee('LOAD-1003');
        $this->get(route('logistics.pod'))->assertOk()->assertSee('Pending Upload');
        $this->get(route('logistics.pod',['view'=>$loads['LOAD-1003']->proofOfDelivery->id]))->assertOk()->assertSee('Photo evidence unclear');
        $this->get(route('logistics.tracking'))->assertOk()->assertSee('Delivery Monitoring')->assertSee('LOAD-1001')->assertSee('LOAD-1002')->assertSee('LOAD-1003')->assertDontSee('GPS')->assertDontSee('Map:');
        $this->get(route('logistics.tracking',['shipment'=>$loads['LOAD-1002']->id]))->assertOk()->assertSee('Delivery Progress')->assertSee('Progress Timeline')->assertSee('Current Facility')->assertSee('Pickup Rider')->assertSee('Delivery Rider')->assertDontSee('Hub History')->assertDontSee('POD Status');
        $this->get(route('logistics.settings'))->assertOk()->assertSee('Maximum Pickup Wait')->assertSee('Maximum Hub Dwell')->assertSee('Delivery Delay Threshold')->assertDontSee('Live Tracking')->assertDontSee('Maps / GPS');

        $this->get(route('logistics.dashboard'))->assertOk()->assertSee('Sorting Center Dashboard')->assertSee('Pickup Requests')->assertSee('Incoming Parcels')->assertSee('Awaiting Sorting')->assertSee('Delivery Rider')->assertDontSee('Live Operations')->assertDontSee('Leaflet');
        $source=$loads['LOAD-1001'];$sellerOrder=$source->sellerOrder;$source->delete();$sellerOrder->update(['status'=>'ready_to_ship']);
        $this->post(route('logistics.quick.create-load'),['seller_order_id'=>$sellerOrder->id])->assertRedirect();
        $created=Shipment::where('seller_order_id',$sellerOrder->id)->firstOrFail();
        $this->post(route('logistics.quick.create-load'),['seller_order_id'=>$sellerOrder->id])->assertRedirect();
        $this->assertSame(1,Shipment::where('seller_order_id',$sellerOrder->id)->count());
        $juan=User::where('email','rider.juan@shoppick.test')->firstOrFail();$vehicle=Vehicle::where('code','MOTO-001')->firstOrFail();
        $this->post(route('logistics.shipments.assign',$created),['rider_id'=>$juan->id,'vehicle_id'=>$vehicle->id])->assertSessionHasErrors('rider_id');
        $backup=User::factory()->create(['is_active'=>true]);$backup->assignRole('rider');$this->licensedProfile($backup);
        $this->post(route('logistics.shipments.assign',$created),['rider_id'=>$backup->id])->assertSessionHasNoErrors();
        $this->assertSame($backup->id,$created->fresh()->rider_id);
        $this->post(route('logistics.quick.invoice'),['shipment_id'=>$created->id])->assertRedirect();
        $this->post(route('logistics.quick.invoice'),['shipment_id'=>$created->id])->assertRedirect()->assertSessionHas('success','An invoice already exists for this Load.');
        $this->assertSame(1,\App\Models\LogisticsInvoice::where('shipment_id',$created->id)->count());
        $this->get(route('logistics.tracking',['shipment'=>$loads['LOAD-1002']->id]))->assertOk()->assertSee('LOAD-1002');
    }
}
