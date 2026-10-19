<?php

namespace Tests\Feature;

use App\Models\{DeliveryArea, LogisticsHub, LogisticsProvider, Order, Role, SellerOrder, SellerProfile, Shipment, Store, SystemSetting, User, RiderProfile};
use App\Services\{AutomaticRiderAssignmentService, ParcelWorkflowService, ShipmentService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticRiderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'logistics', 'rider'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
    }

    public function test_pickup_selects_provider_local_approved_rider_and_ignores_wrong_type_or_center(): void
    {
        [$provider, $owner] = $this->provider('Primary');
        $hub = $this->hub($provider, 'NORTH');
        $shipment = $this->shipment($provider, $hub, $hub, ['address' => 'North service area']);
        $wrongCenter = $this->hub($provider, 'SOUTH');
        $wrong = $this->rider($provider, $wrongCenter, 'pickup', 'North service area');
        $wrong->riderProfile->update(['rider_type' => 'delivery']);
        $right = $this->rider($provider, $hub, 'pickup', 'North service area');

        app(AutomaticRiderAssignmentService::class)->assignPickup($shipment);

        $this->assertSame($right->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame('pickup_assigned', $shipment->fresh()->status);
        $this->assertDatabaseHas('shipment_assignments', ['shipment_id' => $shipment->id, 'rider_id' => $right->id, 'assignment_type' => 'pickup', 'status' => 'assigned']);
        $this->assertDatabaseMissing('shipment_assignments', ['shipment_id' => $shipment->id, 'rider_id' => $wrong->id]);
    }

    public function test_workload_and_stable_id_break_ties_and_second_trigger_is_idempotent(): void
    {
        [$provider] = $this->provider('Workload');
        $hub = $this->hub($provider, 'WORK');
        $busy = $this->rider($provider, $hub, 'pickup', 'Work service area');
        $idle = $this->rider($provider, $hub, 'pickup', 'Work service area');
        foreach (range(1, 2) as $index) {
            $active = $this->shipment($provider, $hub, $hub, ['address' => 'Work service area'], 'pickup_assigned');
            $active->update(['pickup_rider_id' => $busy->id]);
        }
        $shipment = $this->shipment($provider, $hub, $hub, ['address' => 'Work service area']);
        $service = app(AutomaticRiderAssignmentService::class);
        $service->assignPickup($shipment);
        $service->assignPickup($shipment->fresh());

        $this->assertSame($idle->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame(1, $shipment->fresh()->assignments()->where('assignment_type', 'pickup')->count());
    }

    public function test_no_local_rider_keeps_ready_state_and_delivery_starts_only_at_destination_scan_state(): void
    {
        [$provider] = $this->provider('No Local');
        $origin = $this->hub($provider, 'ORIGIN');
        $destination = $this->hub($provider, 'DEST');
        $shipment = $this->shipment($provider, $origin, $destination, ['address' => 'Remote service area']);
        app(AutomaticRiderAssignmentService::class)->assignPickup($shipment);
        $this->assertSame('ready_for_pickup', $shipment->fresh()->status);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'status' => 'awaiting_rider_assignment']);

        $area = DeliveryArea::create(['logistics_provider_id' => $provider->id, 'code' => 'REMOTE', 'name' => 'Remote', 'province' => 'Remote', 'is_active' => true]);
        $area->sortingCenters()->attach($destination->id);
        $shipment->update(['delivery_area_id' => $area->id, 'current_hub_id' => $destination->id, 'status' => 'at_sorting_center']);
        app(AutomaticRiderAssignmentService::class)->assignDelivery($shipment);
        $this->assertSame('at_sorting_center', $shipment->fresh()->status);
        $this->assertNull($shipment->fresh()->rider_id);
    }

    public function test_destination_assignment_requires_center_area_and_delivery_rider_type(): void
    {
        [$provider] = $this->provider('Delivery');
        $origin = $this->hub($provider, 'DORIGIN');
        $destination = $this->hub($provider, 'DDEST');
        $area = DeliveryArea::create(['logistics_provider_id' => $provider->id, 'code' => 'D-AREA', 'name' => 'Delivery Area', 'province' => 'Laguna', 'municipality' => 'Calamba', 'is_active' => true]);
        $area->sortingCenters()->attach($destination->id);
        $pickupOnly = $this->rider($provider, $destination, 'pickup', 'Laguna');
        $delivery = $this->rider($provider, $destination, 'delivery', 'Laguna');
        $delivery->riderProfile->deliveryAreas()->attach($area->id);
        $shipment = $this->shipment($provider, $origin, $destination, ['province' => 'Laguna', 'city' => 'Calamba'], 'at_sorting_center');
        $shipment->update(['delivery_area_id' => $area->id, 'current_hub_id' => $destination->id]);

        app(AutomaticRiderAssignmentService::class)->assignDelivery($shipment);

        $this->assertSame($delivery->id, $shipment->fresh()->rider_id);
        $this->assertNotSame($pickupOnly->id, $shipment->fresh()->rider_id);
        $this->assertSame('assigned_to_rider', $shipment->fresh()->status);
    }

    public function test_declined_rider_is_excluded_and_next_candidate_is_assigned(): void
    {
        [$provider] = $this->provider('Declines');
        $hub = $this->hub($provider, 'DECLINE');
        $first = $this->rider($provider, $hub, 'pickup', 'Decline service area');
        $second = $this->rider($provider, $hub, 'pickup', 'Decline service area');
        $shipment = $this->shipment($provider, $hub, $hub, ['address' => 'Decline service area']);
        $service = app(AutomaticRiderAssignmentService::class);
        $service->assignPickup($shipment, null, [$second->id]);
        $assignment = $shipment->fresh()->assignments()->where('assignment_type', 'pickup')->firstOrFail();
        $assignment->update(['status' => 'declined', 'declined_at' => now(), 'ended_at' => now(), 'decline_reason' => 'Unavailable']);
        $shipment->fresh()->update(['pickup_rider_id' => null, 'pickup_assigned_by' => null, 'assigned_at' => null, 'status' => 'ready_for_pickup']);
        $result = $service->assignPickup($shipment->fresh(), null, [$first->id]);

        $this->assertSame($second->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame(2, $shipment->fresh()->assignments()->where('assignment_type', 'pickup')->count());
    }

    public function test_decline_endpoint_records_cycle_and_reassigns_locally(): void
    {
        [$provider] = $this->provider('Decline Endpoint');
        $hub = $this->hub($provider, 'DECLINE-HTTP');
        $first = $this->rider($provider, $hub, 'pickup', 'Decline HTTP area');
        $second = $this->rider($provider, $hub, 'pickup', 'Decline HTTP area');
        $shipment = $this->shipment($provider, $hub, $hub, ['address' => 'Decline HTTP area']);
        app(AutomaticRiderAssignmentService::class)->assignPickup($shipment, null, [$second->id]);

        $this->actingAs($first)->post(route('rider.shipments.decline', $shipment), ['reason' => 'unavailable'])->assertSessionHas('success');

        $this->assertSame($second->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame(1, $shipment->fresh()->assignments()->where('rider_id', $first->id)->where('status', 'declined')->count());
        $this->assertSame(2, $shipment->fresh()->assignments()->where('assignment_type', 'pickup')->count());
    }

    public function test_manual_assignment_rejects_wrong_provider_center_and_type(): void
    {
        [$provider, $owner] = $this->provider('Manual');
        $hub = $this->hub($provider, 'MANUAL');
        $shipment = $this->shipment($provider, $hub, $hub, ['address' => 'Manual service area']);
        $wrong = $this->rider($provider, $hub, 'delivery', 'Manual service area');

        $this->expectException(ValidationException::class);
        app(ParcelWorkflowService::class)->assignPickup($shipment, $owner, $wrong);
    }

    public function test_ready_to_ship_workflow_attempts_automatic_pickup_assignment(): void
    {
        [$provider] = $this->provider('Ready Pickup');
        $hub = $this->hub($provider, 'READY');
        $rider = $this->rider($provider, $hub, 'pickup', 'Ready service area');
        $shipment = $this->shipment($provider, $hub, $hub, ['address' => 'Ready service area']);
        SystemSetting::create(['key' => 'logistics.default_marketplace_provider_id', 'value' => $provider->id, 'type' => 'number', 'group' => 'logistics']);

        app(ShipmentService::class)->createForSellerOrder($shipment->sellerOrder);

        $this->assertSame($rider->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame('pickup_assigned', $shipment->fresh()->status);
    }

    public function test_sorting_workflow_triggers_delivery_assignment_only_after_arrival_and_scan(): void
    {
        [$provider, $owner] = $this->provider('Sorted Delivery');
        $origin = $this->hub($provider, 'SORT-ORIGIN');
        $destination = $this->hub($provider, 'SORT-DEST');
        $area = DeliveryArea::create(['logistics_provider_id' => $provider->id, 'code' => 'SORT-AREA', 'name' => 'Sorted Area', 'province' => 'Laguna', 'municipality' => 'Calamba', 'is_active' => true]);
        $area->sortingCenters()->attach($destination->id);
        $rider = $this->rider($provider, $destination, 'delivery', 'Laguna');
        $rider->riderProfile->deliveryAreas()->attach($area->id);
        $shipment = $this->shipment($provider, $origin, $destination, ['province' => 'Laguna', 'city' => 'Calamba'], 'at_sorting_center');
        $shipment->update(['current_hub_id' => $destination->id, 'received_at' => now(), 'parcel_scanned_at' => now()]);
        $shipment->events()->create(['actor_id' => $owner->id, 'sorting_center_id' => $destination->id, 'status' => 'at_sorting_center', 'event_type' => 'physical_arrival', 'event_key' => 'physical-arrival:'.$shipment->id.':'.$destination->id]);

        $result = app(ParcelWorkflowService::class)->sort($shipment, $owner, $area);

        $this->assertSame($rider->id, $result->rider_id);
        $this->assertSame('assigned_to_rider', $result->status);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'event_type' => 'automatic_delivery_assignment']);
    }

    public function test_sorting_without_verified_arrival_or_scan_does_not_assign_delivery(): void
    {
        [$provider, $owner] = $this->provider('Blocked Delivery');
        $origin = $this->hub($provider, 'BLOCK-ORIGIN');
        $destination = $this->hub($provider, 'BLOCK-DEST');
        $area = DeliveryArea::create(['logistics_provider_id' => $provider->id, 'code' => 'BLOCK-AREA', 'name' => 'Blocked Area', 'province' => 'Laguna', 'municipality' => 'Calamba', 'is_active' => true]);
        $area->sortingCenters()->attach($destination->id);
        $rider = $this->rider($provider, $destination, 'delivery', 'Laguna');
        $rider->riderProfile->deliveryAreas()->attach($area->id);
        $shipment = $this->shipment($provider, $origin, $destination, ['province' => 'Laguna', 'city' => 'Calamba'], 'at_sorting_center');

        $this->expectException(ValidationException::class);
        app(ParcelWorkflowService::class)->sort($shipment, $owner, $area);
    }

    private function provider(string $name): array
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $owner->assignRole('logistics');
        $provider = LogisticsProvider::create(['owner_user_id' => $owner->id, 'company_name' => $name, 'status' => 'approved', 'is_active' => true]);
        $provider->users()->attach($owner->id, ['membership_role' => 'owner', 'is_active' => true]);
        return [$provider, $owner];
    }

    private function hub(LogisticsProvider $provider, string $code): LogisticsHub
    {
        return LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => $code, 'name' => $code.' Center', 'address' => $code.' service area', 'province' => 'Laguna', 'municipality' => 'Calamba', 'status' => 'active']);
    }

    private function rider(LogisticsProvider $provider, LogisticsHub $hub, string $type, string $serviceArea): User
    {
        $rider = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $rider->assignRole('rider');
        RiderProfile::create(['user_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'logistics_user_id' => $provider->owner_user_id, 'hub_id' => $hub->id, 'account_status' => 'active', 'availability' => 'available', 'rider_type' => $type, 'approved_service_area' => $serviceArea, 'driver_license_number' => 'LIC-'.$rider->id, 'driver_license_expires_at' => now()->addYear(), 'driver_license_front_path' => 'front', 'driver_license_back_path' => 'back', 'driver_license_status' => 'verified']);
        return $rider->fresh();
    }

    private function shipment(LogisticsProvider $provider, LogisticsHub $origin, LogisticsHub $destination, array $pickup, string $status = 'ready_for_pickup'): Shipment
    {
        $buyer = User::factory()->create(['is_active' => true]);
        $buyer->assignRole('buyer');
        $seller = User::factory()->create(['is_active' => true]);
        $seller->assignRole('seller');
        $sellerProfile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $sellerProfile->id, 'name' => 'Test Shop '.uniqid(), 'slug' => 'test-shop-'.uniqid(), 'status' => 'active', 'location' => 'Laguna Calamba']);
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'ORD-'.uniqid(), 'status' => 'packed', 'payment_method' => 'card', 'payment_status' => 'paid', 'subtotal' => 100, 'shipping_fee' => 20, 'total' => 120, 'buyer_name' => $buyer->name, 'buyer_phone' => '09170000000', 'shipping_address' => ['province' => 'Laguna', 'city' => 'Calamba', 'barangay' => 'Central']]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => 'SO-'.uniqid(), 'status' => 'ready_to_ship', 'subtotal' => 100, 'shipping_fee' => 20, 'seller_total' => 100]);
        return Shipment::create(['logistics_provider_id' => $provider->id, 'shipment_number' => 'SHP-'.uniqid(), 'parcel_code' => 'PCL-'.uniqid(), 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'origin_hub_id' => $origin->id, 'destination_hub_id' => $destination->id, 'current_hub_id' => $status === 'at_sorting_center' ? $destination->id : null, 'status' => $status, 'pickup_address' => $pickup, 'delivery_address' => ['province' => 'Laguna', 'city' => 'Calamba'], 'ready_at' => now()]);
    }
}
