<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerShoppingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller;
    protected Store $store;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['buyer' => 'Buyer', 'seller' => 'Seller'] as $slug => $name) {
            Role::create(['name' => $name, 'slug' => $slug, 'guard_name' => 'web']);
        }

        $this->buyer = User::factory()->create(['is_active' => true, 'phone' => '+639171234567']);
        $this->buyer->assignRole('buyer');
        $this->seller = User::factory()->create(['is_active' => true]);
        $this->seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $this->seller->id, 'status' => 'approved']);
        $this->store = Store::create([
            'user_id' => $this->seller->id,
            'seller_profile_id' => $profile->id,
            'name' => 'Tech Store',
            'slug' => 'tech-store',
            'status' => 'active',
        ]);
        $this->category = Category::create(['name' => 'Peripherals', 'slug' => 'peripherals']);
    }

    public function test_exact_and_partial_search_rank_available_products(): void
    {
        $exact = $this->product('Wireless Gaming Mouse', 10);
        $this->product('Wireless Gaming Mouse Pad', 10);
        $this->product('Mechanical Keyboard', 10);

        $response = $this->get(route('products.index', ['q' => '   Wireless Gaming Mouse   ']));
        $response->assertOk()->assertSeeInOrder(['Wireless Gaming Mouse', 'Wireless Gaming Mouse Pad']);

        $this->get(route('products.index', ['q' => 'Mouse']))
            ->assertOk()
            ->assertSee($exact->name);
    }

    public function test_variant_is_required_and_same_variant_quantity_is_merged(): void
    {
        $product = $this->product('Gaming Headset', 8);
        $variant = $product->variants()->create([
            'type' => 'Color', 'value' => 'Black', 'sku' => 'HEADSET-BLK', 'price' => 850, 'stock' => 5,
        ]);

        $this->actingAs($this->buyer)->post(route('cart.add'), [
            'product_id' => $product->id, 'quantity' => 1,
        ])->assertSessionHas('error', 'Please select a product option.');

        foreach ([1, 2] as $quantity) {
            $this->actingAs($this->buyer)->post(route('cart.add'), [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
            ])->assertRedirect(route('cart.index'));
        }

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $itemId = $this->buyer->cart->items()->value('id');
        $this->post(route('cart.update', $itemId), ['quantity' => 4])
            ->assertSessionHas('success', 'Cart updated');
        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 4]);

        $this->post(route('cart.remove', $itemId))
            ->assertSessionHas('success', 'Product removed from cart.');
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    public function test_buy_now_orders_only_requested_item_and_preserves_existing_cart(): void
    {
        $cartProduct = $this->product('Mechanical Keyboard', 10, 500);
        $buyNowProduct = $this->product('Wireless Gaming Mouse', 7, 900);
        $buyNowVariant = $buyNowProduct->variants()->create([
            'type' => 'Color', 'value' => 'Black', 'sku' => 'MOUSE-BLK', 'price' => 900, 'stock' => 7,
        ]);
        $address = Address::create([
            'user_id' => $this->buyer->id, 'full_name' => 'Buyer Test', 'phone' => '09171234567',
            'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Test',
            'postal_code' => '1000', 'address_line' => '123 Test Street', 'is_default' => true,
        ]);

        $this->actingAs($this->buyer)->post(route('cart.add'), [
            'product_id' => $cartProduct->id, 'quantity' => 1,
        ])->assertRedirect(route('cart.index'));

        $this->post(route('buy-now'), [
            'product_id' => $buyNowProduct->id, 'product_variant_id' => $buyNowVariant->id, 'quantity' => 2,
        ])->assertRedirect(route('checkout', ['mode' => 'buy_now']));

        $this->get(route('checkout', ['mode' => 'buy_now']))
            ->assertOk()
            ->assertSee('Wireless Gaming Mouse')
            ->assertDontSee('Mechanical Keyboard');

        $this->post(route('checkout.store'), [
            'address_id' => $address->id,
            'payment_method' => 'cod',
            'checkout_mode' => 'buy_now',
        ])->assertSessionMissing('error')->assertRedirect();

        $order = \App\Models\Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();
        $this->get(route('orders.show', $order->order_number))
            ->assertOk()
            ->assertSee('Wireless Gaming Mouse')
            ->assertSee('Color: Black');

        $this->assertDatabaseHas('order_items', [
            'product_id' => $buyNowProduct->id, 'product_variant_id' => $buyNowVariant->id,
            'variant_label' => 'Color: Black', 'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('order_items', ['product_id' => $cartProduct->id]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $cartProduct->id, 'quantity' => 1]);
        $this->assertDatabaseHas('seller_orders', ['store_id' => $this->store->id]);
        $sellerOrder = SellerOrder::where('store_id', $this->store->id)->firstOrFail();
        $this->assertDatabaseHas('notifications_custom', [
            'user_id' => $this->seller->id,
            'title' => 'New order received!',
            'link' => route('seller.orders.show', $sellerOrder),
        ]);
        $this->assertSame(5, $buyNowProduct->fresh()->stock);

        $this->actingAs($this->seller)->get(route('seller.notifications.index'))
            ->assertOk()->assertSee('New order received!')->assertSee('View Order');
        $this->get(route('seller.orders.index', ['status' => 'to_ship']))
            ->assertOk()->assertSee($sellerOrder->seller_order_number)->assertSee('To Receive');
        $this->get(route('seller.orders.show', $sellerOrder))
            ->assertOk()->assertSee('Confirm Order')->assertSee('Color: Black');

        foreach (['confirmed', 'processing', 'packed', 'ready_to_ship'] as $status) {
            $this->post(route('seller.orders.status', $sellerOrder), ['status' => $status])
                ->assertSessionHasNoErrors();
            $sellerOrder->refresh();
            $this->assertSame($status, $sellerOrder->status);
        }
        $this->post(route('seller.orders.status', $sellerOrder), ['status' => 'shipped'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('order_status_history', [
            'seller_order_id' => $sellerOrder->id, 'changed_by' => $this->seller->id, 'status' => 'ready_to_ship',
        ]);
    }

    public function test_wishlist_toggle_persists_selected_state_and_is_isolated_per_buyer(): void
    {
        $product = $this->product('Wishlist Mouse', 5, 750);

        $this->actingAs($this->buyer)->postJson(route('wishlist.toggle'), ['product_id'=>$product->id])
            ->assertOk()->assertJson(['success'=>true,'added'=>true,'count'=>1]);
        $this->assertDatabaseHas('wishlist_items',['wishlist_id'=>$this->buyer->wishlist->id,'product_id'=>$product->id]);
        $this->get(route('home'))->assertOk()->assertSee('aria-pressed="true"',false)->assertSee('fill="currentColor"',false);
        $this->get(route('wishlist.index'))->assertOk()->assertSee('Wishlist Mouse')->assertSee('Tech Store')->assertSee('Add to Cart')->assertSee('Buy Now');

        $otherBuyer = User::factory()->create(['is_active'=>true]); $otherBuyer->assignRole('buyer');
        $this->actingAs($otherBuyer)->postJson(route('wishlist.toggle'),['product_id'=>$product->id])
            ->assertOk()->assertJson(['added'=>true,'count'=>1]);
        $this->assertDatabaseCount('wishlist_items',2);
        $this->postJson(route('wishlist.toggle'),['product_id'=>$product->id])->assertJson(['added'=>false,'count'=>0]);
        $this->assertDatabaseHas('wishlist_items',['wishlist_id'=>$this->buyer->wishlist->id,'product_id'=>$product->id]);

        $this->actingAs($this->buyer)->postJson(route('wishlist.toggle'),['product_id'=>$product->id])
            ->assertOk()->assertJson(['added'=>false,'count'=>0]);
        $this->assertDatabaseCount('wishlist_items',0);
    }

    public function test_unavailable_product_remains_visible_in_wishlist_but_cannot_be_purchased(): void
    {
        $product = $this->product('Unavailable Favorite', 5, 300);
        $this->actingAs($this->buyer)->postJson(route('wishlist.toggle'),['product_id'=>$product->id])->assertOk();
        $product->update(['is_active'=>false]);

        $this->get(route('wishlist.index'))->assertOk()
            ->assertSee('Unavailable Favorite')->assertSee('Unavailable')->assertDontSee('Buy Now')->assertDontSee('Add to Cart');
    }

    public function test_multi_seller_checkout_partitions_items_and_progresses_each_seller_order_independently(): void
    {
        $secondSeller = User::factory()->create(['is_active' => true]);
        $secondSeller->assignRole('seller');
        $secondProfile = SellerProfile::create(['user_id' => $secondSeller->id, 'status' => 'approved']);
        $secondStore = Store::create([
            'user_id' => $secondSeller->id, 'seller_profile_id' => $secondProfile->id,
            'name' => 'Second Store', 'slug' => 'second-store', 'status' => 'active',
        ]);

        $firstProduct = $this->product('First Seller Product', 5, 300);
        $secondProduct = Product::create([
            'store_id' => $secondStore->id, 'category_id' => $this->category->id,
            'name' => 'Second Seller Product', 'slug' => 'second-seller-product',
            'price' => 400, 'stock' => 5, 'is_active' => true,
            'publication_status' => 'published', 'moderation_status' => 'clean',
        ]);
        $address = Address::create([
            'user_id' => $this->buyer->id, 'full_name' => 'Multi Buyer', 'phone' => '09171234567',
            'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Test',
            'postal_code' => '1000', 'address_line' => '123 Test Street', 'is_default' => true,
        ]);

        $this->actingAs($this->buyer);
        foreach ([$firstProduct, $secondProduct] as $product) {
            $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect(route('cart.index'));
        }
        $this->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $order = \App\Models\Order::where('user_id', $this->buyer->id)->firstOrFail();
        $this->assertSame(2, $order->sellerOrders()->count());
        $this->assertSame(2, $order->items()->count());
        $this->assertSame([$this->store->id, $secondStore->id], $order->sellerOrders()->orderBy('store_id')->pluck('store_id')->all());

        $firstSellerOrder = $order->sellerOrders()->where('store_id', $this->store->id)->firstOrFail();
        $secondSellerOrder = $order->sellerOrders()->where('store_id', $secondStore->id)->firstOrFail();
        $this->actingAs($this->seller)->get(route('seller.orders.index'))->assertSee($firstProduct->name)->assertDontSee($secondProduct->name);
        $this->actingAs($secondSeller)->get(route('seller.orders.index'))->assertSee($secondProduct->name)->assertDontSee($firstProduct->name);

        $statusService = app(\App\Services\SellerOrderStatusService::class);
        $statusService->transition($firstSellerOrder, $this->seller, 'confirmed');
        $this->assertSame('pending', $order->fresh()->status);
        $statusService->transition($secondSellerOrder, $secondSeller, 'confirmed');
        $this->assertSame('confirmed', $order->fresh()->status);
        $statusService->transition($firstSellerOrder->fresh(), $this->seller, 'processing');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_cod_stays_pending_until_rider_collection_while_online_payment_settles_at_checkout(): void
    {
        $product=$this->product('Seller Created COD Product',10,700);
        $address=Address::create(['user_id'=>$this->buyer->id,'full_name'=>'COD Buyer','phone'=>'09171234567','province'=>'Metro Manila','city'=>'Manila','barangay'=>'Test','postal_code'=>'1000','address_line'=>'123 COD Street','is_default'=>true]);
        $this->actingAs($this->buyer)->post(route('cart.add'),['product_id'=>$product->id,'quantity'=>1]);
        $this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'cod'])->assertSessionHasNoErrors();
        $order=\App\Models\Order::where('user_id',$this->buyer->id)->latest('id')->firstOrFail();
        $payment=$order->payments()->latest('id')->firstOrFail();
        $this->assertSame('cod',$order->payment_status);$this->assertNull($order->paid_at);$this->assertSame('pending',$payment->status);$this->assertNull($payment->paid_at);

        $sellerOrder=$order->sellerOrders()->firstOrFail();
        foreach(['confirmed','processing','packed','ready_to_ship'] as $status)app(\App\Services\SellerOrderStatusService::class)->transition($sellerOrder->fresh(),$this->seller,$status);
        $this->assertSame('pending',$payment->fresh()->status);
        $shipment=$sellerOrder->fresh()->shipment;$shipment->update(['status'=>'assigned']);
        Role::create(['name'=>'Logistics','slug'=>'logistics','guard_name'=>'web']);$logistics=User::factory()->create(['is_active'=>true]);$logistics->assignRole('logistics');
        app(\App\Services\ShipmentService::class)->transition($shipment->fresh(),$logistics,'picked_up');
        app(\App\Services\ShipmentService::class)->transition($shipment->fresh(),$logistics,'in_transit');
        $this->assertSame('pending',$payment->fresh()->status);
        Role::create(['name'=>'Rider','slug'=>'rider','guard_name'=>'web']);$rider=User::factory()->create(['is_active'=>true]);$rider->assignRole('rider');\App\Models\RiderProfile::create(['user_id'=>$rider->id]);
        $shipment->update(['rider_id'=>$rider->id]);app(\App\Services\ShipmentService::class)->transition($shipment->fresh(),$rider,'out_for_delivery');
        try{app(\App\Services\ShipmentService::class)->transition($shipment->fresh(),$rider,'delivered');$this->fail('Uncollected COD delivery was accepted.');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('payment',$e->errors());}
        app(\App\Services\CodCollectionService::class)->collect($shipment->fresh(),$rider);app(\App\Services\CodCollectionService::class)->collect($shipment->fresh(),$rider);
        $this->assertSame('cod_collected',$payment->fresh()->status);$this->assertSame($rider->id,$payment->fresh()->collected_by);$this->assertNotNull($payment->fresh()->collected_at);
        app(\App\Services\ShipmentService::class)->transition($shipment->fresh(),$rider,'delivered');
        $this->assertSame('paid',$payment->fresh()->status);$this->assertSame('paid',$order->fresh()->payment_status);$this->assertNotNull($order->fresh()->paid_at);

        $online=$this->product('Seller Created Card Product',5,400);$this->actingAs($this->buyer)->post(route('cart.add'),['product_id'=>$online->id,'quantity'=>1]);
        $this->post(route('checkout.store'),['address_id'=>$address->id,'payment_method'=>'card'])->assertSessionHasNoErrors();
        $cardOrder=\App\Models\Order::where('user_id',$this->buyer->id)->latest('id')->firstOrFail();
        $this->assertSame('paid',$cardOrder->payment_status);$this->assertNotNull($cardOrder->paid_at);$this->assertSame('paid',$cardOrder->payments()->latest('id')->value('status'));
    }

    protected function product(string $name, int $stock, int $price = 1000): Product
    {
        return Product::create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(5),
            'description' => "Description for {$name}",
            'brand' => 'SHOPPICK Test',
            'price' => $price,
            'stock' => $stock,
            'is_active' => true,
            'moderation_status' => 'clean',
        ]);
    }
}
