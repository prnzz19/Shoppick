<?php

namespace Tests\Feature;

use App\Models\{LogisticsProvider, Order, Role, SellerOrder, SellerProfile, Shipment, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'logistics', 'buyer', 'seller'] as $role) Role::create(['name' => ucfirst($role), 'slug' => $role, 'guard_name' => 'web']);
    }

    public function test_admin_can_approve_suspend_and_reactivate_a_verified_provider_without_deleting_it(): void
    {
        $admin = User::factory()->create(['is_active' => true]); $admin->assignRole('admin');
        $owner = User::factory()->create(['is_active' => false, 'registration_type' => 'logistics', 'registration_status' => 'pending', 'email_verified_at' => now()]); $owner->assignRole('logistics');
        $provider = LogisticsProvider::create(['owner_user_id' => $owner->id, 'company_name' => 'Lifecycle Provider', 'status' => 'pending', 'is_active' => false]);
        $owner->logisticsProviders()->attach($provider->id, ['membership_role' => 'owner', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.logistics.providers.review', $provider), ['decision' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('logistics_providers', ['id' => $provider->id, 'status' => 'approved', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'registration_status' => 'approved', 'is_active' => true]);
        $activeShipment = $this->shipment($provider);
        $this->actingAs($admin)->post(route('admin.logistics.providers.status', $provider), ['status' => 'suspended'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('logistics_providers', ['id' => $provider->id, 'status' => 'approved', 'is_active' => true]);
        $activeShipment->update(['status' => 'delivered']);
        $this->actingAs($admin)->post(route('admin.logistics.providers.status', $provider), ['status' => 'suspended'])->assertRedirect();
        $this->assertDatabaseHas('logistics_providers', ['id' => $provider->id, 'status' => 'suspended', 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'registration_status' => 'suspended', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.logistics.providers.status', $provider), ['status' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('logistics_providers', ['id' => $provider->id, 'status' => 'approved', 'is_active' => true]);

        $shipment = $this->shipment($provider);
        $this->actingAs($admin)->post(route('admin.logistics.providers.status', $provider), ['status' => 'deactivated'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('logistics_providers', ['id' => $provider->id, 'status' => 'approved', 'is_active' => true]);

        $shipment->update(['status' => 'delivered']);
        $this->actingAs($admin)->post(route('admin.logistics.providers.status', $provider), ['status' => 'deactivated'])->assertRedirect();
        $this->assertDatabaseHas('logistics_providers', ['id' => $provider->id, 'status' => 'deactivated', 'is_active' => false]);
    }

    private function shipment(LogisticsProvider $provider): Shipment
    {
        $buyer = User::factory()->create(['is_active' => true]); $buyer->assignRole('buyer');
        $seller = User::factory()->create(['is_active' => true]); $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Lifecycle Shop', 'slug' => 'lifecycle-shop-'.uniqid(), 'status' => 'active']);
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'LIFE-'.uniqid(), 'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 100, 'shipping_fee' => 20, 'total' => 120, 'buyer_name' => $buyer->name, 'buyer_phone' => '09170000000', 'shipping_address' => ['address_line' => 'Lifecycle Address', 'city' => 'Manila']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => 'LIFE-SO-'.uniqid(), 'status' => 'ready_to_ship', 'subtotal' => 100, 'shipping_fee' => 20, 'seller_total' => 100]);

        return Shipment::create(['logistics_provider_id' => $provider->id, 'shipment_number' => 'LIFE-SHIP-'.uniqid(), 'parcel_code' => 'LIFE-PARCEL-'.uniqid(), 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'status' => 'assigned', 'pickup_address' => ['address' => 'Seller'], 'delivery_address' => ['address' => 'Buyer']]);
    }
}
