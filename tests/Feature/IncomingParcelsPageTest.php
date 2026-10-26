<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, LogisticsProvider, Order, Permission, Role, SellerOrder, SellerProfile, Shipment, Store, User};
use App\Services\ParcelWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncomingParcelsPageTest extends TestCase
{
    use RefreshDatabase;

    private function logistics(): array
    {
        $role = Role::firstOrCreate(['slug' => 'logistics'], ['name' => 'Logistics', 'guard_name' => 'web']);
        foreach (['view_shipments', 'manage_shipments'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'guard_name' => 'web', 'group' => 'Logistics']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $user->assignRole($role);
        $provider = LogisticsProvider::create(['owner_user_id' => $user->id, 'company_name' => 'Incoming Test Logistics', 'status' => 'approved', 'is_active' => true]);
        $provider->users()->attach($user->id, ['membership_role' => 'owner', 'is_active' => true]);
        $hub = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'INCOMING-HUB-'.$provider->id, 'name' => 'Incoming Test Hub', 'address' => 'Test address', 'status' => 'active']);
        return [$user, $provider, $hub];
    }

    private function shipment(LogisticsProvider $provider, LogisticsHub $hub, string $code, string $status, ?User $pickup = null, array $extra = []): Shipment
    {
        $seller = User::factory()->create(['is_active' => true]);
        $seller->assignRole(Role::firstOrCreate(['slug' => 'seller'], ['name' => 'Seller', 'guard_name' => 'web']));
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Incoming Test Shop', 'slug' => 'incoming-test-shop-'.$code, 'status' => 'active']);
        $buyer = User::factory()->create(['is_active' => true]);
        $buyer->assignRole(Role::firstOrCreate(['slug' => 'buyer'], ['name' => 'Buyer', 'guard_name' => 'web']));
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'INCOMING-ORDER-'.$code, 'status' => 'shipped', 'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 100, 'total' => 100, 'buyer_name' => $buyer->name, 'buyer_phone' => '09170000000', 'shipping_address' => ['province' => 'Laguna', 'city' => 'Calamba']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => 'INCOMING-SELLER-'.$code, 'status' => 'shipped', 'subtotal' => 100, 'seller_total' => 100]);
        return Shipment::create(array_merge(['logistics_provider_id' => $provider->id, 'shipment_number' => 'INCOMING-TRACK-'.$code, 'parcel_code' => $code, 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'pickup_rider_id' => $pickup?->id, 'current_hub_id' => $hub->id, 'status' => $status, 'picked_up_at' => $status === 'picked_up' ? now() : null, 'delivery_address' => ['province' => 'Laguna', 'city' => 'Calamba']], $extra));
    }

    public function test_queue_is_provider_scoped_and_filters_real_arrival_states(): void
    {
        [$logistics, $provider, $hub] = $this->logistics();
        $rider = User::factory()->create(['is_active' => true]);
        $rider->assignRole(Role::firstOrCreate(['slug' => 'rider'], ['name' => 'Rider', 'guard_name' => 'web']));
        \App\Models\RiderProfile::create(['user_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'account_status' => 'active', 'availability' => 'available']);
        $this->shipment($provider, $hub, 'IN-TRANSIT', 'picked_up', $rider);
        $this->shipment($provider, $hub, 'IN-READY', 'picked_up', $rider, ['pickup_arrived_at' => now()]);
        $this->shipment($provider, $hub, 'IN-RECEIVED', 'at_sorting_center', $rider, ['received_at' => now()]);
        [$otherLogistics, $otherProvider, $otherHub] = $this->logistics();
        $this->shipment($otherProvider, $otherHub, 'OTHER-PROVIDER', 'picked_up');

        $this->actingAs($logistics)->get(route('logistics.incoming'))->assertOk()->assertSee('IN-TRANSIT')->assertSee('IN-READY')->assertSee('IN-RECEIVED')->assertDontSee('OTHER-PROVIDER')->assertViewHas('summary', fn ($summary) => $summary === ['expected' => 3, 'in_transit' => 1, 'ready' => 1, 'received' => 1]);
        $this->get(route('logistics.incoming', ['arrival' => 'ready']))->assertOk()->assertSee('IN-READY')->assertDontSee('IN-TRANSIT')->assertDontSee('IN-RECEIVED');
        $this->get(route('logistics.incoming', ['q' => 'IN-RECEIVED']))->assertOk()->assertSee('IN-RECEIVED')->assertDontSee('IN-READY');
        $this->get(route('logistics.incoming', ['rider_id' => $rider->id, 'hub_id' => $hub->id]))->assertOk()->assertSee('IN-TRANSIT');
    }

    public function test_existing_arrival_rules_are_preserved_and_do_not_assign_delivery_riders(): void
    {
        [$logistics, $provider, $hub] = $this->logistics();
        $shipment = $this->shipment($provider, $hub, 'IN-GUARDED', 'picked_up');
        $workflow = app(ParcelWorkflowService::class);
        try {
            $workflow->receive($shipment, $logistics);
            $this->fail('A Parcel without Rider arrival evidence was received.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $shipment->update(['pickup_arrived_at' => now()]);
        $workflow->receive($shipment->fresh(), $logistics);
        $this->assertSame('at_sorting_center', $shipment->fresh()->status);
        $this->assertNull($shipment->fresh()->rider_id);
        $workflow->confirmScan($shipment->fresh(), $logistics, $shipment->parcel_code);
        $this->assertNotNull($shipment->fresh()->parcel_scanned_at);
        $this->assertNull($shipment->fresh()->rider_id);
    }
}