<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_history_filters_searches_and_protects_buyer_orders(): void
    {
        [$buyer, $store, $product] = $this->marketplace();
        $completed = $this->order($buyer, $store, $product, 'completed', 'SP-HISTORY-COMPLETE');
        $cancelled = $this->order($buyer, $store, $product, 'cancelled', 'SP-HISTORY-CANCELLED');
        $this->order($buyer, $store, $product, 'pending', 'SP-ACTIVE-ORDER');

        $this->actingAs($buyer)->get(route('orders.index', ['tab' => 'history']))
            ->assertOk()->assertSee('Purchase History')->assertSee($completed->order_number)
            ->assertSee($cancelled->order_number)->assertDontSee('SP-ACTIVE-ORDER')
            ->assertSee('Search past orders')->assertSee('Buy Again')->assertSee('Review Product');

        $this->get(route('orders.index', ['tab' => 'history', 'history_status' => 'cancelled']))
            ->assertSee($cancelled->order_number)->assertDontSee($completed->order_number)->assertDontSee('Track Order');
        $this->get(route('orders.index', ['tab' => 'history', 'q' => 'History Headphones']))
            ->assertSee($completed->order_number)->assertSee($cancelled->order_number);
        $this->get(route('orders.index', ['tab' => 'history', 'q' => 'History Shop']))
            ->assertSee($completed->order_number);

        $other = User::factory()->create(); $other->assignRole('buyer');
        $this->actingAs($other)->get(route('orders.show', $completed->order_number))->assertNotFound();
        $this->post(route('orders.buy-again', [$completed->order_number, $completed->items->first()]))->assertNotFound();
    }

    public function test_buy_again_uses_existing_cart_validation_and_current_product(): void
    {
        [$buyer, $store, $product] = $this->marketplace();
        $order = $this->order($buyer, $store, $product, 'completed', 'SP-BUY-AGAIN');
        $item = $order->items->first();

        $this->actingAs($buyer)->post(route('orders.buy-again', [$order->order_number, $item]))
            ->assertRedirect(route('cart.index'));
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);

        $product->update(['is_active' => false]);
        $this->post(route('orders.buy-again', [$order->order_number, $item]))
            ->assertSessionHas('error', 'This product is currently unavailable.');
    }

    private function marketplace(): array
    {
        Role::create(['name' => 'Buyer', 'slug' => 'buyer', 'guard_name' => 'web']);
        Role::create(['name' => 'Seller', 'slug' => 'seller', 'guard_name' => 'web']);
        $buyer = User::factory()->create(['is_active' => true]); $buyer->assignRole('buyer');
        $seller = User::factory()->create(['is_active' => true]); $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'History Shop', 'slug' => 'history-shop', 'status' => 'active']);
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $product = Product::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'History Headphones', 'slug' => 'history-headphones', 'price' => 850, 'stock' => 5, 'is_active' => true, 'publication_status' => 'published', 'moderation_status' => 'clean']);
        return [$buyer, $store, $product];
    }

    private function order(User $buyer, Store $store, Product $product, string $status, string $number): Order
    {
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => $number, 'status' => $status, 'payment_method' => 'cod', 'payment_status' => $status === 'completed' ? 'paid' : 'unpaid', 'subtotal' => 850, 'discount' => 0, 'shipping_fee' => 50, 'voucher_discount' => 0, 'total' => 900, 'shipping_address' => [], 'buyer_name' => $buyer->name, 'completed_at' => $status === 'completed' ? now() : null, 'cancelled_at' => $status === 'cancelled' ? now() : null]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => $number.'-S1', 'status' => $status, 'subtotal' => 850, 'shipping_fee' => 50, 'discount' => 0, 'commission_rate' => 0, 'commission_amount' => 0, 'seller_total' => 900]);
        $order->items()->create(['seller_order_id' => $sellerOrder->id, 'product_id' => $product->id, 'product_name' => $product->name, 'price' => 850, 'quantity' => 1, 'total' => 850]);
        return $order->fresh(['items']);
    }
}
