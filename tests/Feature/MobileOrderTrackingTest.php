<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileOrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private User $rider;

    private Store $store;

    private Order $order;

    private SellerOrder $sellerOrder;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'rider'] as $role) {
            Role::create(['name' => ucfirst($role), 'slug' => $role, 'guard_name' => 'web']);
        }
        $this->buyer = $this->actor('buyer');
        $this->seller = $this->actor('seller');
        $this->rider = $this->actor('rider');
        $profile = SellerProfile::create(['user_id' => $this->seller->id, 'status' => 'approved']);
        $this->store = Store::create(['user_id' => $this->seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Tracking Shop', 'slug' => 'tracking-shop', 'status' => 'active']);
        $this->order = Order::create(['user_id' => $this->buyer->id, 'order_number' => 'SP-TRACK-1', 'status' => 'shipped', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'subtotal' => 100, 'shipping_fee' => 0, 'total' => 100]);
        $this->sellerOrder = SellerOrder::create(['order_id' => $this->order->id, 'store_id' => $this->store->id, 'seller_order_number' => 'SO-TRACK-1', 'status' => 'shipped', 'subtotal' => 100, 'shipping_fee' => 0, 'seller_total' => 100]);
        $this->shipment = Shipment::create(['order_id' => $this->order->id, 'seller_order_id' => $this->sellerOrder->id, 'store_id' => $this->store->id, 'shipment_number' => 'SH-TRACK-1', 'status' => 'out_for_delivery', 'rider_id' => $this->rider->id, 'internal_notes' => 'PRIVATE logistics instructions', 'pickup_address' => ['address_line' => 'Pickup Street'], 'delivery_address' => ['address_line' => 'Destination Street', 'latitude' => 14.5, 'longitude' => 121]]);
        $this->shipment->events()->create(['status' => 'out_for_delivery', 'note' => 'PRIVATE event note', 'metadata' => ['private_data' => 'PRIVATE metadata'], 'created_at' => now()->subMinute()]);
        $this->point(now()->subMinutes(5), 14.4);
        $this->point(now(), 14.5);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function signIn(User $user): void
    {
        $token = 'tracking-token-'.$user->id;
        DB::table('mobile_api_tokens')->insertOrIgnore(['user_id' => $user->id, 'name' => 'mobile', 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);
        $this->withToken($token);
    }

    private function point($time, float $latitude): void
    {
        DB::table('shipment_tracking_points')->insert(['shipment_id' => $this->shipment->id, 'rider_id' => $this->rider->id, 'latitude' => $latitude, 'longitude' => 121, 'source' => 'device', 'recorded_at' => $time, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_buyer_tracks_own_order_and_receives_only_safe_delivery_phase_coordinates(): void
    {
        $this->signIn($this->buyer);
        $response = $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonPath('shipments.0.current_rider_location.latitude', 14.5)
            ->assertJsonCount(1, 'shipments.0.points')->assertJsonPath('shipments.0.origin', null)->assertJsonPath('shipments.0.destination.latitude', 14.5)->assertJsonPath('poll', true);
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
        $this->assertStringNotContainsString($this->rider->email, $response->getContent());
        $this->assertArrayNotHasKey('rider_id', $response->json('shipments.0.points.0'));
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.shipments.0.tracking_number', 'SH-TRACK-1');
        $this->getJson('/api/v1/orders/SP-TRACK-1')->assertOk()->assertJsonPath('shipments.0.events.0.status', 'out_for_delivery');
    }

    public function test_buyer_cannot_access_foreign_or_nonexistent_orders_and_anonymous_is_rejected(): void
    {
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertUnauthorized();
        $this->signIn($this->actor('buyer'));
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertNotFound();
        $this->getJson('/api/v1/orders/MISSING/tracking')->assertNotFound();
        $this->getJson('/api/v1/seller/orders')->assertForbidden();
        $this->signIn($this->rider);
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertForbidden();
    }

    public function test_seller_only_sees_own_shop_orders_and_requires_approved_active_access(): void
    {
        $this->signIn($this->seller);
        $url = '/api/v1/seller/orders/'.$this->sellerOrder->id.'/tracking';
        $this->getJson($url)->assertOk()->assertJsonPath('order_number', 'SO-TRACK-1')->assertJsonPath('shipments.0.current_rider_location.latitude', 14.5);
        $this->getJson('/api/v1/seller/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.shipments.0.tracking_number', 'SH-TRACK-1');
        $other = $this->actor('seller');
        $profile = SellerProfile::create(['user_id' => $other->id, 'status' => 'approved']);
        Store::create(['user_id' => $other->id, 'seller_profile_id' => $profile->id, 'name' => 'Other shop', 'slug' => 'other', 'status' => 'active']);
        $this->signIn($other);
        $this->getJson($url)->assertNotFound();
        $this->getJson('/api/v1/seller/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->signIn($this->seller);
        $this->store->update(['status' => 'suspended']);
        $this->getJson($url)->assertForbidden();
        $this->getJson('/api/v1/seller/orders')->assertForbidden();
    }

    public function test_pre_delivery_and_failed_states_do_not_expose_rider_gps_even_in_history(): void
    {
        $this->signIn($this->buyer);
        foreach (['pickup_accepted', 'picked_up', 'in_transit', 'assigned_to_rider', 'delivery_failed', 'returned'] as $status) {
            $this->shipment->update(['status' => $status]);
            $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonPath('shipments.0.current_rider_location', null)->assertJsonCount(0, 'shipments.0.points');
        }
    }

    public function test_delivered_returns_history_without_live_location_or_polling(): void
    {
        $this->signIn($this->buyer);
        $this->shipment->update(['status' => 'delivered', 'delivered_at' => now()]);
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonPath('poll', false)->assertJsonPath('shipments.0.live', false)
            ->assertJsonPath('shipments.0.current_rider_location', null)->assertJsonCount(1, 'shipments.0.points');
        $this->signIn($this->seller);
        $this->getJson('/api/v1/seller/orders/'.$this->sellerOrder->id.'/tracking')->assertOk()->assertJsonPath('poll', false);
    }

    public function test_absent_phase_boundary_and_cancelled_parent_never_expose_gps(): void
    {
        $this->signIn($this->buyer);
        $this->shipment->events()->delete();
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonPath('shipments.0.current_rider_location', null)->assertJsonCount(0, 'shipments.0.points');
        $this->shipment->events()->create(['status' => 'out_for_delivery', 'created_at' => now()->subMinute()]);
        $this->order->update(['status' => 'cancelled']);
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonPath('shipments.0.current_rider_location', null)->assertJsonPath('poll', false);
    }

    public function test_order_without_shipment_returns_an_explicit_empty_tracking_state(): void
    {
        $this->shipment->delete();
        $this->signIn($this->buyer);
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonCount(0, 'shipments')->assertJsonPath('poll', false);
    }

    public function test_multi_shop_order_is_visible_to_buyer_but_seller_receives_only_own_shipment(): void
    {
        $other = $this->actor('seller');
        $profile = SellerProfile::create(['user_id' => $other->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $other->id, 'seller_profile_id' => $profile->id, 'name' => 'Other tracking shop', 'slug' => 'other-tracking', 'status' => 'active']);
        $so = SellerOrder::create(['order_id' => $this->order->id, 'store_id' => $store->id, 'seller_order_number' => 'SO-OTHER', 'status' => 'shipped', 'subtotal' => 100, 'shipping_fee' => 0, 'seller_total' => 100]);
        Shipment::create(['order_id' => $this->order->id, 'seller_order_id' => $so->id, 'store_id' => $store->id, 'shipment_number' => 'SH-OTHER', 'status' => 'in_transit']);
        $this->signIn($this->buyer);
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonCount(2, 'shipments');
        $this->signIn($this->seller);
        $this->getJson('/api/v1/seller/orders/'.$this->sellerOrder->id.'/tracking')->assertOk()->assertJsonCount(1, 'shipments')->assertJsonPath('shipments.0.tracking_number', 'SH-TRACK-1');
        $this->getJson('/api/v1/seller/orders/'.$so->id.'/tracking')->assertNotFound();
    }

    public function test_device_event_coordinates_and_stale_live_position_are_not_exposed(): void
    {
        $this->signIn($this->buyer);
        $this->shipment->events()->create(['status' => 'picked_up', 'metadata' => ['source' => 'device', 'latitude' => 14.4, 'longitude' => 121]]);
        DB::table('shipment_tracking_points')->where('shipment_id', $this->shipment->id)->update(['recorded_at' => now()->subMinutes(3)]);
        $this->getJson('/api/v1/orders/SP-TRACK-1/tracking')->assertOk()->assertJsonPath('shipments.0.events.1.coordinates', null)->assertJsonPath('shipments.0.current_rider_location', null);
    }
}
