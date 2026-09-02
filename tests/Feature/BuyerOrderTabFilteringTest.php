<?php

namespace Tests\Feature;

use App\Models\{Category,Order,Product,Role,SellerOrder,SellerProfile,Shipment,Store,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderTabFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_use_seller_and_shipment_source_of_truth_with_matching_counts(): void
    {
        foreach(['buyer','seller'] as $slug)Role::create(['name'=>ucfirst($slug),'slug'=>$slug,'guard_name'=>'web']);
        $buyer=User::factory()->create(['is_active'=>true]);$buyer->assignRole('buyer');
        $seller=User::factory()->create(['is_active'=>true]);$seller->assignRole('seller');
        $profile=SellerProfile::create(['user_id'=>$seller->id,'status'=>'approved']);
        $store=Store::create(['user_id'=>$seller->id,'seller_profile_id'=>$profile->id,'name'=>'Real Seller Shop','slug'=>'real-seller-shop','status'=>'active']);
        $category=Category::create(['name'=>'Seller Products','slug'=>'seller-products']);
        $first=$this->product($store,$category,'First Future Product');
        $second=$this->product($store,$category,'Second Future Product');

        $ready=$this->order($buyer,$store,$first,'SP-TAB-READY','packed','ready_to_ship');
        $processing=$this->order($buyer,$store,$second,'SP-TAB-PROCESSING','processing','processing');
        $codPending=$this->order($buyer,$store,$first,'SP-TAB-COD','pending','pending');
        $toPay=$this->order($buyer,$store,$first,'SP-TAB-PAY','pending','pending','card','unpaid');
        $picked=$this->order($buyer,$store,$first,'SP-TAB-PICKED','shipped','shipped');
        Shipment::create(['shipment_number'=>'SH-TAB-PICKED','seller_order_id'=>$picked->id,'order_id'=>$picked->order_id,'store_id'=>$store->id,'status'=>'picked_up']);
        $this->order($buyer,$store,$first,'SP-TAB-DELIVERED','delivered','delivered');
        $this->order($buyer,$store,$first,'SP-TAB-COMPLETED','completed','completed');
        $this->order($buyer,$store,$first,'SP-TAB-CANCELLED','cancelled','cancelled');

        $this->actingAs($buyer)->get(route('orders.index',['tab'=>'to_ship']))->assertOk()
            ->assertSee('SP-TAB-READY')->assertSee('Ready To Ship')->assertSee('SP-TAB-PROCESSING')->assertSee('SP-TAB-COD')
            ->assertDontSee('SP-TAB-PICKED')->assertDontSee('SP-TAB-PAY');
        $this->get(route('orders.index',['tab'=>'to_receive']))->assertOk()->assertSee('SP-TAB-PICKED')->assertSee('SP-TAB-DELIVERED')->assertDontSee('SP-TAB-READY');
        $this->get(route('orders.index',['tab'=>'to_pay']))->assertOk()->assertSee('SP-TAB-PAY')->assertDontSee('SP-TAB-COD');
        $this->get(route('orders.index',['tab'=>'completed']))->assertOk()->assertSee('SP-TAB-COMPLETED')->assertDontSee('SP-TAB-DELIVERED');
        $this->get(route('orders.index',['tab'=>'cancelled']))->assertOk()->assertSee('SP-TAB-CANCELLED');
        $this->get(route('orders.index',['tab'=>'history']))->assertOk()->assertSee('SP-TAB-COMPLETED')->assertSee('SP-TAB-CANCELLED')->assertDontSee('SP-TAB-READY');

        $response=$this->get(route('orders.index'));
        $response->assertViewHas('tabCounts',fn($counts)=>$counts->toArray()===['all'=>8,'to_pay'=>1,'to_ship'=>3,'to_receive'=>2,'completed'=>1,'cancelled'=>1,'history'=>2]);

        $other=User::factory()->create(['is_active'=>true]);$other->assignRole('buyer');
        $this->actingAs($other)->get(route('orders.index',['tab'=>'to_ship']))->assertOk()->assertDontSee('SP-TAB-READY');
    }

    private function product(Store $store,Category $category,string $name): Product
    {
        return Product::create(['store_id'=>$store->id,'category_id'=>$category->id,'name'=>$name,'slug'=>str($name)->slug().'-'.str()->random(5),'price'=>500,'stock'=>10,'is_active'=>true,'publication_status'=>'published','moderation_status'=>'clean']);
    }

    private function order(User $buyer,Store $store,Product $product,string $number,string $orderStatus,string $sellerStatus,string $paymentMethod='cod',string $paymentStatus='unpaid'): SellerOrder
    {
        $order=Order::create(['user_id'=>$buyer->id,'order_number'=>$number,'status'=>$orderStatus,'payment_method'=>$paymentMethod,'payment_status'=>$paymentStatus,'subtotal'=>500,'shipping_fee'=>50,'total'=>550,'buyer_name'=>$buyer->name,'shipping_address'=>[]]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'store_id'=>$store->id,'seller_order_number'=>$number.'-S1','status'=>$sellerStatus,'subtotal'=>500,'shipping_fee'=>50,'seller_total'=>500]);
        $order->items()->create(['seller_order_id'=>$sellerOrder->id,'product_id'=>$product->id,'product_name'=>$product->name,'price'=>500,'quantity'=>1,'total'=>500]);
        return $sellerOrder;
    }
}
