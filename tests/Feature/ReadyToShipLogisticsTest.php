<?php

namespace Tests\Feature;

use App\Models\{Category,NotificationModel,Order,Permission,Product,RiderProfile,Role,SellerOrder,SellerProfile,Shipment,Store,User};
use App\Services\{SellerOrderStatusService,ShipmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyToShipLogisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_real_seller_products_create_visible_loads_and_deduplicated_notifications(): void
    {
        [$seller,$logistics,$store,$category]=$this->actors();
        $first=$this->product($store,$category,'Future Test Product');
        $second=$this->product($store,$category,'Second Future Product');
        $firstSellerOrder=$this->sellerOrder($seller,$store,$first,'SP-FUTURE-ONE');
        $secondSellerOrder=$this->sellerOrder($seller,$store,$second,'SP-FUTURE-TWO');

        foreach([$firstSellerOrder,$secondSellerOrder] as $sellerOrder){
            app(SellerOrderStatusService::class)->transition($sellerOrder,$seller,'ready_to_ship');
            app(ShipmentService::class)->createForSellerOrder($sellerOrder->fresh(),$seller);
        }

        $this->assertSame(2,Shipment::count());
        $this->assertSame(2,NotificationModel::where('user_id',$logistics->id)->where('type','logistics_ready_for_pickup')->count());
        $this->assertSame(2,Shipment::whereNull('rider_id')->where('status','ready_for_pickup')->count());

        $this->actingAs($logistics)->get(route('logistics.dashboard'))->assertOk()->assertSee('2');
        $this->get(route('logistics.shipments.index'))->assertOk()->assertSee('SP-FUTURE-ONE')->assertSee('SP-FUTURE-TWO');
        $this->get(route('logistics.shipments.show',Shipment::where('seller_order_id',$firstSellerOrder->id)->first()))->assertOk()->assertSee($first->name);
        $this->get(route('logistics.shipments.show',Shipment::where('seller_order_id',$secondSellerOrder->id)->first()))->assertOk()->assertSee($second->name);
        $this->get(route('logistics.dispatch'))->assertOk()->assertSee($store->name);
        $this->get(route('logistics.notifications'))->assertOk()->assertSee('New Order / Load Ready for Pickup')->assertSee('SP-FUTURE-ONE')->assertSee('SP-FUTURE-TWO');

        $notification=NotificationModel::where('user_id',$logistics->id)->firstOrFail();
        $this->get(route('logistics.notifications.open',$notification))->assertRedirect($notification->link);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_reconciliation_repairs_missing_load_only_once_and_multi_seller_is_partitioned(): void
    {
        [$seller,$logistics,$store,$category]=$this->actors();
        $ready=$this->sellerOrder($seller,$store,$this->product($store,$category,'Repair Product'),'SP-REPAIR');
        $ready->update(['status'=>'ready_to_ship']);

        $otherSeller=User::factory()->create(['is_active'=>true]);$otherSeller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$otherSeller->id,'status'=>'approved']);
        $otherStore=Store::create(['user_id'=>$otherSeller->id,'seller_profile_id'=>$profile->id,'name'=>'Other Shop','slug'=>'other-shop','status'=>'active']);
        $processing=$this->sellerOrder($otherSeller,$otherStore,$this->product($otherStore,$category,'Other Shop Product'),'SP-OTHER');
        $processing->update(['status'=>'processing']);

        $this->artisan('logistics:reconcile-ready-orders')->assertSuccessful();
        $this->artisan('logistics:reconcile-ready-orders')->assertSuccessful();

        $this->assertSame(1,Shipment::where('seller_order_id',$ready->id)->count());
        $this->assertFalse(Shipment::where('seller_order_id',$processing->id)->exists());
        $this->assertSame(1,NotificationModel::where('user_id',$logistics->id)->where('type','logistics_ready_for_pickup')->count());
        $this->assertDatabaseHas('order_status_history',['seller_order_id'=>$ready->id,'status'=>'ready_to_ship']);
    }

    public function test_buyer_receives_each_public_progress_stage_once_and_can_open_it_securely(): void
    {
        [$seller,$logistics,$store,$category]=$this->actors();
        $sellerOrder=$this->sellerOrder($seller,$store,$this->product($store,$category,'Buyer Progress Product'),'SP-BUYER-PROGRESS');
        $sellerOrder->update(['status'=>'pending']);
        $buyer=$sellerOrder->order->user;

        foreach(['confirmed','processing','packed','ready_to_ship'] as $status)app(SellerOrderStatusService::class)->transition($sellerOrder->fresh(),$seller,$status);
        app(ShipmentService::class)->createForSellerOrder($sellerOrder->fresh(),$seller);
        $this->assertSame(4,NotificationModel::where('user_id',$buyer->id)->where('type','buyer_order_progress')->count());

        $shipment=$sellerOrder->fresh()->shipment;
        $shipment->update(['status'=>'assigned']);
        app(ShipmentService::class)->transition($shipment->fresh(),$logistics,'picked_up');
        app(ShipmentService::class)->transition($shipment->fresh(),$logistics,'in_transit');
        $this->assertSame(1,NotificationModel::where('user_id',$buyer->id)->where('data->status','shipped')->count());

        Role::create(['name'=>'Rider','slug'=>'rider','guard_name'=>'web']);
        $rider=User::factory()->create(['is_active'=>true]);$rider->assignRole('rider');RiderProfile::create(['user_id'=>$rider->id]);
        $shipment->update(['rider_id'=>$rider->id]);
        app(ShipmentService::class)->transition($shipment->fresh(),$rider,'out_for_delivery');
        app(\App\Services\CodCollectionService::class)->collect($shipment->fresh(),$rider);
        app(ShipmentService::class)->transition($shipment->fresh(),$rider,'delivered');
        $this->actingAs($buyer)->post(route('orders.confirm',$sellerOrder->order->order_number))->assertSessionHasNoErrors();
        $this->assertSame(1,NotificationModel::where('user_id',$buyer->id)->where('data->status','completed')->count());

        $this->get(route('orders.index'))->assertOk()->assertSee('New update');
        $this->get(route('notifications.index'))->assertOk()->assertSee('Ready to Ship')->assertSee('Out for Delivery')->assertSee('Order Delivered')->assertSee('Order Completed');
        $ready=NotificationModel::where('user_id',$buyer->id)->where('data->status','ready_to_ship')->firstOrFail();
        $this->get(route('notifications.open',$ready->id))->assertRedirect(route('orders.show',$sellerOrder->order->order_number));
        $this->get(route('orders.show',$sellerOrder->order->order_number))->assertOk()->assertSee('Latest Update')->assertSee('Ready to Ship')->assertSee('Out for Delivery');
        $this->get(route('orders.index'))->assertOk()->assertDontSee('New update');

        $other=User::factory()->create(['is_active'=>true]);$other->assignRole('buyer');
        $this->actingAs($other)->get(route('notifications.open',$ready->id))->assertNotFound();
    }

    private function actors(): array
    {
        foreach(['buyer','seller','logistics'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
        foreach(['view_shipments','view_logistics_dashboard','manage_shipments'] as $slug){
            $permission=Permission::create(['name'=>str($slug)->replace('_',' ')->title(),'slug'=>$slug,'guard_name'=>'web','group'=>'Logistics']);
            Role::where('slug','logistics')->first()->permissions()->attach($permission);
        }
        $seller=User::factory()->create(['is_active'=>true]);$seller->assignRole('seller');
        $logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Manual Product Shop','slug'=>'manual-product-shop','location'=>'Seller Pickup Address','status'=>'active']);
        $category=Category::create(['name'=>'Manual Products','slug'=>'manual-products','is_active'=>true]);
        return [$seller,$logistics,$store,$category];
    }

    private function product(Store $store,Category $category,string $name): Product
    {
        return Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>$name,'slug'=>str($name)->slug().'-'.str()->random(5),'price'=>500,'stock'=>10,'is_active'=>true,'publication_status'=>'published','moderation_status'=>'clean']);
    }

    private function sellerOrder(User $seller,Store $store,Product $product,string $number): SellerOrder
    {
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>$number,'status'=>'packed','payment_method'=>'cod','payment_status'=>'unpaid','subtotal'=>500,'shipping_fee'=>50,'total'=>550,'buyer_name'=>$buyer->name,'shipping_address'=>['address_line'=>'Buyer Delivery Address']]);
        $order->payments()->create(['method'=>'cod','status'=>'pending','gateway'=>'cod','amount'=>550]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>$number.'-S1','status'=>'packed','subtotal'=>500,'shipping_fee'=>50,'seller_total'=>500]);
        $order->items()->create(['seller_order_id'=>$sellerOrder->id,'product_id'=>$product->id,'product_name'=>$product->name,'price'=>500,'quantity'=>1,'total'=>500]);
        return $sellerOrder;
    }
}
