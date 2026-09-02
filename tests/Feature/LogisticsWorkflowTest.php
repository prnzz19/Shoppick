<?php
namespace Tests\Feature;

use App\Models\{Category,Order,OrderItem,Permission,Product,RiderProfile,Role,SellerOrder,SellerProfile,Shipment,ShipmentTrackingPoint,Store,User,Vehicle};
use App\Services\SellerOrderStatusService;
use App\Services\ShipmentService;
use App\Services\OrderProgressService;
use Database\Seeders\LogisticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
        foreach(['buyer','seller','admin','super_admin','logistics','rider'] as $slug) Role::create(['name'=>ucfirst(str_replace('_',' ',$slug)),'slug'=>$slug,'guard_name'=>'web']);
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
        $this->assertSame('packed',$order->fresh()->status);
        $this->assertSame('packed',app(OrderProgressService::class)->tracker($order->fresh())['status']);
        app(ShipmentService::class)->createForSellerOrder($sellerOrder->fresh(),$seller);
        $this->assertSame(1,Shipment::where('seller_order_id',$sellerOrder->id)->count());

        $logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        $rider=User::factory()->create(['is_active'=>true]);$rider->assignRole('rider');RiderProfile::create(['user_id'=>$rider->id]);
        $vehicle=Vehicle::create(['code'=>'MOTO-001','type'=>'motorcycle','status'=>'available']);
        app(ShipmentService::class)->assign($shipment,$logistics,$rider,$vehicle);
        $assignment=$shipment->fresh()->assignments()->firstOrFail();
        $this->assertSame($rider->id,$shipment->fresh()->rider_id);$this->assertSame('in_use',$vehicle->fresh()->status);
        $this->assertSame('packed',$order->fresh()->status,'Assignment must not appear shipped to the Buyer.');

        app(ShipmentService::class)->transition($shipment->fresh(),$logistics,'picked_up');
        $this->assertSame('shipped',$order->fresh()->status);
        $this->actingAs($buyer)->get(route('orders.show',$order->order_number))->assertOk()->assertSee('aria-current="step"',false)->assertSee('Shipped');
        app(ShipmentService::class)->transition($shipment->fresh(),$logistics,'in_transit');
        app(ShipmentService::class)->transition($shipment->fresh(),$rider,'out_for_delivery');
        app(\App\Services\CodCollectionService::class)->collect($shipment->fresh(),$rider);
        $this->actingAs($rider)->postJson(route('rider.shipments.location',$shipment),['latitude'=>14.5995,'longitude'=>120.9842,'accuracy'=>8])->assertOk()->assertJsonPath('saved',true);
        $this->assertDatabaseHas('shipment_tracking_points',['shipment_id'=>$shipment->id,'rider_id'=>$rider->id]);
        $this->actingAs($buyer)->getJson(route('orders.tracking',[$order->order_number,$shipment]))->assertOk()->assertJsonMissingPath('location.accuracy')->assertJsonPath('location.latitude',14.5995);
        $otherBuyer=User::factory()->create(['is_active'=>true]);$otherBuyer->assignRole('buyer');
        $this->actingAs($otherBuyer)->getJson(route('orders.tracking',[$order->order_number,$shipment]))->assertNotFound();
        $this->actingAs($logistics)->getJson(route('logistics.tracking.feed',$shipment))->assertOk()->assertJsonPath('location.accuracy',8);
        app(ShipmentService::class)->transition($shipment->fresh(),$rider,'delivered');
        $this->assertSame('delivered',$order->fresh()->status);
        $this->actingAs($rider)->postJson(route('rider.shipments.location',$shipment),['latitude'=>14.6,'longitude'=>121])->assertUnprocessable();
        $this->actingAs($rider)->post(route('rider.shipments.pod',$shipment),['recipient_name'=>'Maria Buyer'])->assertSessionHasNoErrors();
        $pod=$shipment->fresh()->proofOfDelivery()->firstOrFail();$invoice=$shipment->fresh()->invoice()->firstOrFail();
        $this->assertEquals(60,$invoice->total);$this->assertNotEquals($order->total,$invoice->total);

        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('admin');
        $this->actingAs($admin)->get(route('logistics.dispatch'))->assertForbidden();
        $this->actingAs($buyer)->get(route('orders.show',$order->order_number))->assertOk()->assertSee($shipment->shipment_number)->assertSee('Delivered');
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

    public function test_development_logistics_demo_is_connected_and_idempotent():void
    {
        $this->seed(LogisticsDemoSeeder::class);
        $this->seed(LogisticsDemoSeeder::class);

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
        $this->assertSame(0,ShipmentTrackingPoint::whereIn('shipment_id',$loads->pluck('id'))->count(),'Demo seeding must never masquerade simulated coordinates as real GPS.');

        $logistics=User::where('email','logistics@shoppick.test')->firstOrFail();
        $this->actingAs($logistics)->get(route('logistics.shipments.index',['q'=>'LOAD-1001']))->assertOk()->assertSee('Orders / Loads')->assertSee('LOAD-1001')->assertDontSee('LOAD-1002');
        $this->get(route('logistics.shipments.index',['status'=>'in_transit']))->assertOk()->assertSee('LOAD-1002')->assertDontSee('LOAD-1003');
        $this->get(route('logistics.shipments.index',['view'=>$loads['LOAD-1003']->id]))->assertOk()->assertSee('Load Details')->assertSee('Recipient unavailable')->assertSee('Tracking')->assertSee('Documents');
        $this->get(route('logistics.dispatch'))->assertOk()->assertSee('LOAD-1001')->assertSee('LOAD-1002')->assertSee('LOAD-1003');
        $this->get(route('logistics.pod'))->assertOk()->assertSee('Pending Upload');
        $this->get(route('logistics.pod',['view'=>$loads['LOAD-1003']->proofOfDelivery->id]))->assertOk()->assertSee('Photo evidence unclear');
        $this->get(route('logistics.tracking'))->assertOk()->assertSee('LOAD-1001')->assertSee('LOAD-1002')->assertSee('LOAD-1003');

        $this->get(route('logistics.dashboard'))->assertOk()->assertSee('Create Load')->assertSee('Assign Rider')->assertSee('Generate Invoice')->assertSee('Plan Route')->assertSee('Routing provider is not configured.');
        $source=$loads['LOAD-1001'];$sellerOrder=$source->sellerOrder;$source->delete();$sellerOrder->update(['status'=>'ready_to_ship']);
        $this->post(route('logistics.quick.create-load'),['seller_order_id'=>$sellerOrder->id])->assertRedirect();
        $created=Shipment::where('seller_order_id',$sellerOrder->id)->firstOrFail();
        $this->post(route('logistics.quick.create-load'),['seller_order_id'=>$sellerOrder->id])->assertRedirect();
        $this->assertSame(1,Shipment::where('seller_order_id',$sellerOrder->id)->count());
        $juan=User::where('email','rider.juan@shoppick.test')->firstOrFail();$vehicle=Vehicle::where('code','MOTO-001')->firstOrFail();
        $this->post(route('logistics.shipments.assign',$created),['rider_id'=>$juan->id,'vehicle_id'=>$vehicle->id])->assertRedirect();
        $this->assertSame($juan->id,$created->fresh()->rider_id);
        $this->post(route('logistics.quick.invoice'),['shipment_id'=>$created->id])->assertRedirect();
        $this->post(route('logistics.quick.invoice'),['shipment_id'=>$created->id])->assertRedirect()->assertSessionHas('success','An invoice already exists for this Load.');
        $this->assertSame(1,\App\Models\LogisticsInvoice::where('shipment_id',$created->id)->count());
        $this->get(route('logistics.tracking',['shipment'=>$loads['LOAD-1002']->id]))->assertOk()->assertSee('LOAD-1002');
    }
}
