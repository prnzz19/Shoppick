<?php

namespace Tests\Feature;

use App\Models\{LogisticsProvider, Order, Role, SellerOrder, SellerProfile, Store, SystemSetting, User, RiderProfile};
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPickupCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['seller', 'rider', 'logistics'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
    }

    public function test_seller_store_settings_requires_explicit_pin_confirmation(): void
    {
        [$seller, $store] = $this->sellerStore();

        $this->actingAs($seller)->get(route('seller.store.edit'))
            ->assertOk()
            ->assertSee('seller-pickup-map', false)
            ->assertSee('pickup_location_confirmed', false)
            ->assertSee('Region, Province, City/Municipality, Barangay, Street, and Postal Code');

        $this->actingAs($seller)->put(route('seller.store.update'), [
            'name' => $store->name,
            'description' => $store->description,
            'location' => 'Street, Barangay, City, Province, 1000',
            'pickup_latitude' => '14.5995000',
            'pickup_longitude' => '120.9842000',
        ])->assertSessionHasErrors('pickup_location_confirmed');

        $this->assertNull($store->fresh()->pickup_latitude);
        $this->actingAs($seller)->put(route('seller.store.update'), [
            'name' => $store->name,
            'description' => $store->description,
            'location' => 'Street, Barangay, City, Province, 1000',
            'pickup_latitude' => '14.5995000',
            'pickup_longitude' => '120.9842000',
            'pickup_location_confirmed' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($store->fresh()->hasConfirmedPickupLocation());
        $this->assertSame(14.5995, (float) $store->fresh()->pickup_latitude);
    }

    public function test_session_authenticated_rider_location_update_is_separate_from_mobile_token_api(): void
    {
        $rider = User::factory()->create(['is_active' => true, 'registration_type' => 'rider', 'registration_status' => 'approved']);
        $rider->assignRole('rider');
        RiderProfile::create(['user_id' => $rider->id, 'account_status' => 'active', 'availability' => 'available']);

        $this->actingAs($rider)->patch(route('rider.location'), [
            'latitude' => 14.5995,
            'longitude' => 120.9842,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue($rider->riderProfile->fresh()->hasFreshLocation());
    }

    public function test_confirmed_store_coordinates_are_used_for_pickup_only(): void
    {
        [$seller, $store] = $this->sellerStore();
        $store->update([
            'pickup_latitude' => 14.5995,
            'pickup_longitude' => 120.9842,
            'pickup_location_confirmed_at' => now(),
        ]);
        $provider = $this->provider();
        SystemSetting::create(['key' => 'logistics.default_marketplace_provider_id', 'value' => $provider->id, 'type' => 'number', 'group' => 'logistics']);
        $sellerOrder = $this->sellerOrder($store, ['address_line' => 'Buyer address', 'city' => 'Cebu']);

        $shipment = app(ShipmentService::class)->createForSellerOrder($sellerOrder, $seller);
        $pickup = $shipment->fresh()->pickup_address;

        $this->assertSame('Street, Barangay, City, Province, 1000', $pickup['address']);
        $this->assertSame(14.5995, (float) $pickup['latitude']);
        $this->assertSame(120.9842, (float) $pickup['longitude']);
        $this->assertSame('Cebu', $shipment->fresh()->delivery_address['city']);
    }

    public function test_unconfirmed_store_coordinates_are_not_invented_into_shipments(): void
    {
        [$seller, $store] = $this->sellerStore();
        $provider = $this->provider();
        SystemSetting::create(['key' => 'logistics.default_marketplace_provider_id', 'value' => $provider->id, 'type' => 'number', 'group' => 'logistics']);
        $sellerOrder = $this->sellerOrder($store, ['address_line' => 'Buyer address', 'city' => 'Davao']);

        $shipment = app(ShipmentService::class)->createForSellerOrder($sellerOrder, $seller);
        $pickup = $shipment->fresh()->pickup_address;

        $this->assertArrayNotHasKey('latitude', $pickup);
        $this->assertArrayNotHasKey('longitude', $pickup);
        $this->assertSame('Davao', $shipment->fresh()->delivery_address['city']);
    }

    private function sellerStore(): array
    {
        $seller = User::factory()->create(['is_active' => true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create([
            'user_id' => $seller->id,
            'seller_profile_id' => $profile->id,
            'name' => 'Coordinate Shop',
            'slug' => 'coordinate-shop-'.str()->random(6),
            'description' => 'Coordinate test shop',
            'location' => 'Street, Barangay, City, Province, 1000',
            'status' => 'active',
        ]);

        return [$seller, $store];
    }

    private function provider(): LogisticsProvider
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('logistics');
        $provider = LogisticsProvider::create(['owner_user_id' => $owner->id, 'company_name' => 'SHOPPICK Logistics', 'status' => 'approved', 'is_active' => true]);
        $provider->users()->attach($owner->id, ['membership_role' => 'owner', 'is_active' => true]);

        return $provider;
    }

    private function sellerOrder(Store $store, array $delivery): SellerOrder
    {
        $buyer = User::factory()->create(['is_active' => true]);
        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'COORD-'.str()->random(8),
            'status' => 'ready_to_ship',
            'payment_method' => 'cod',
            'payment_status' => 'cod',
            'subtotal' => 100,
            'shipping_fee' => 20,
            'total' => 120,
            'buyer_name' => $buyer->name,
            'buyer_phone' => '09170000000',
            'shipping_address' => $delivery,
        ]);
        return SellerOrder::create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'seller_order_number' => 'COORD-SO-'.str()->random(8),
            'status' => 'ready_to_ship',
            'subtotal' => 100,
            'shipping_fee' => 20,
            'seller_total' => 100,
        ]);
    }
}