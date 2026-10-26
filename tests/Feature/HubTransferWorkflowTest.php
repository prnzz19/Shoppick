<?php

namespace Tests\Feature;

use App\Models\{Category, LogisticsHub, LogisticsProvider, Order, OrderItem, Permission, ProofOfDelivery, RiderProfile, Role, SellerOrder, SellerProfile, Shipment, Store, User, Vehicle};
use App\Services\ParcelWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['buyer', 'seller', 'logistics', 'rider'] as $slug) Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'Manage Hubs', 'slug' => 'manage_hubs', 'group' => 'Logistics', 'guard_name' => 'web']);
        Role::where('slug', 'logistics')->firstOrFail()->permissions()->attach($permission);
    }

    private function fixture(): array
    {
        $logistics = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $logistics->assignRole('logistics');
        $provider = LogisticsProvider::create(['owner_user_id' => $logistics->id, 'company_name' => 'Transfer Test Logistics', 'status' => 'approved', 'is_active' => true]);
        $provider->users()->attach($logistics->id, ['membership_role' => 'owner', 'is_active' => true]);
        $origin = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'ORIGIN-TEST', 'name' => 'Santa Cruz Hub', 'address' => 'Santa Cruz, Laguna', 'status' => 'active']);
        $destination = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'DEST-TEST', 'name' => 'Cavinti Hub', 'address' => 'Cavinti, Laguna', 'status' => 'active']);

        $seller = User::factory()->create(['is_active' => true]);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create(['user_id' => $seller->id, 'seller_profile_id' => $profile->id, 'name' => 'Santa Cruz Shop', 'slug' => 'santa-cruz-shop', 'status' => 'active']);
        $buyer = User::factory()->create(['is_active' => true]);
        $buyer->assignRole('buyer');
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'TRANSFER-ORDER-1', 'status' => 'shipped', 'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 100, 'total' => 100, 'buyer_name' => $buyer->name, 'buyer_phone' => '09170000000', 'shipping_address' => ['province' => 'Laguna', 'municipality' => 'Cavinti']]);
        $order->payments()->create(['method' => 'cod', 'status' => 'pending', 'gateway' => 'cod', 'amount' => 100]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'store_id' => $store->id, 'seller_order_number' => 'TRANSFER-SELLER-1', 'status' => 'shipped', 'subtotal' => 100, 'seller_total' => 100]);
        $category = Category::create(['name' => 'Transfer Test Category', 'slug' => 'transfer-test-category']);
        $product = \App\Models\Product::create(['store_id' => $store->id, 'category_id' => $category->id, 'name' => 'Transfer Test Product', 'slug' => 'transfer-test-product', 'price' => 100, 'stock' => 1, 'is_active' => true]);
        OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'product_id' => $product->id, 'product_name' => $product->name, 'price' => 100, 'quantity' => 1, 'subtotal' => 100, 'total' => 100]);
        $area = \App\Models\DeliveryArea::create(['logistics_provider_id' => $provider->id, 'code' => 'CAVINTI-TEST', 'name' => 'Cavinti Area', 'province' => 'Laguna', 'municipality' => 'Cavinti', 'barangays' => []]);
        $area->sortingCenters()->attach($destination->id);
        $shipment = Shipment::create(['logistics_provider_id' => $provider->id, 'shipment_number' => 'TRANSFER-SHIPMENT-1', 'parcel_code' => 'TRANSFER-PARCEL-1', 'seller_order_id' => $sellerOrder->id, 'order_id' => $order->id, 'store_id' => $store->id, 'current_hub_id' => $origin->id, 'delivery_area_id' => $area->id, 'status' => 'sorted', 'pickup_address' => ['province' => 'Laguna', 'municipality' => 'Santa Cruz'], 'delivery_address' => ['province' => 'Laguna', 'municipality' => 'Cavinti'], 'received_at' => now(), 'parcel_scanned_at' => now(), 'sorted_at' => now()]);
        $driver = User::factory()->create(['is_active' => true]);
        $driver->assignRole('rider');
        RiderProfile::create(['user_id' => $driver->id, 'logistics_provider_id' => $provider->id, 'account_status' => 'active', 'availability' => 'available', 'rider_type' => 'both', 'driver_license_number' => 'TEST-TRANSFER', 'driver_license_front_path' => 'test/front', 'driver_license_back_path' => 'test/back', 'driver_license_status' => 'verified', 'driver_license_expires_at' => now()->addYear()]);
        $vehicle = Vehicle::create(['logistics_provider_id' => $provider->id, 'code' => 'TRANSFER-VAN', 'type' => 'van', 'status' => 'available']);

        return compact('logistics', 'provider', 'origin', 'destination', 'shipment', 'driver', 'vehicle');
    }

    public function test_transfer_requires_the_full_ordered_lifecycle_and_destination_receipt_scan(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.store'), ['shipment_id' => $fixture['shipment']->id, 'destination_hub_id' => $fixture['destination']->id])->assertSessionHasNoErrors();
        $transfer = \App\Models\HubTransfer::firstOrFail();
        $this->assertSame('hub_transfer', $fixture['shipment']->fresh()->status);
        $this->assertSame($fixture['destination']->id, $fixture['shipment']->fresh()->destination_hub_id);
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.store'), ['shipment_id' => $fixture['shipment']->id, 'destination_hub_id' => $fixture['destination']->id])->assertSessionHasErrors('shipment_id');
        $this->actingAs($fixture['logistics'])->put(route('logistics.hub-transfers.assign', $transfer), ['transfer_driver_id' => $fixture['driver']->id, 'vehicle_id' => $fixture['vehicle']->id])->assertSessionHasNoErrors();
        $this->assertSame('assigned', $transfer->fresh()->status);
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.dispatch', $transfer))->assertSessionHasNoErrors();
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.dispatch', $transfer))->assertSessionHasErrors('status');
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.arrive', $transfer))->assertSessionHasNoErrors();
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.receive', $transfer), ['parcel_code' => 'WRONG'])->assertSessionHasErrors('parcel_code');
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.receive', $transfer), ['parcel_code' => $fixture['shipment']->parcel_code])->assertSessionHasNoErrors();
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.receive', $transfer), ['parcel_code' => $fixture['shipment']->parcel_code])->assertSessionHasErrors('status');
        $this->assertSame('completed', $transfer->fresh()->status);
        $this->assertSame('at_sorting_center', $fixture['shipment']->fresh()->status);
        $this->assertSame($fixture['destination']->id, $fixture['shipment']->fresh()->current_hub_id);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $fixture['shipment']->id, 'event_type' => 'destination_receipt_verified']);
        $this->assertDatabaseHas('shipment_assignments', ['shipment_id' => $fixture['shipment']->id, 'assignment_type' => 'transfer', 'status' => 'ended']);
    }

    public function test_transfer_routes_are_provider_scoped_and_invalid_transitions_are_rejected(): void
    {
        $fixture = $this->fixture();
        $other = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $other->assignRole('logistics');
        $otherProvider = LogisticsProvider::create(['owner_user_id' => $other->id, 'company_name' => 'Other Logistics', 'status' => 'approved', 'is_active' => true]);
        $otherProvider->users()->attach($other->id, ['membership_role' => 'owner', 'is_active' => true]);
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.store'), ['shipment_id' => $fixture['shipment']->id, 'destination_hub_id' => $fixture['destination']->id])->assertSessionHasNoErrors();
        $transfer = \App\Models\HubTransfer::firstOrFail();
        $this->actingAs($other)->post(route('logistics.hub-transfers.dispatch', $transfer))->assertNotFound();
        $this->actingAs($fixture['logistics'])->post(route('logistics.hub-transfers.dispatch', $transfer))->assertSessionHasErrors('status');
        $this->assertSame($fixture['provider']->id, $transfer->fresh()->logistics_provider_id);
        $this->assertTrue($otherProvider->isOperational());
    }

    public function test_transfer_requires_destination_coverage_for_the_parcel_delivery_area(): void
    {
        $fixture = $this->fixture();
        $fixture['shipment']->deliveryArea->sortingCenters()->detach($fixture['destination']->id);

        $this->actingAs($fixture['logistics'])
            ->post(route('logistics.hub-transfers.store'), [
                'shipment_id' => $fixture['shipment']->id,
                'destination_hub_id' => $fixture['destination']->id,
            ])
            ->assertSessionHasErrors('destination_hub_id');

        $this->assertDatabaseCount('hub_transfers', 0);
        $this->assertSame('sorted', $fixture['shipment']->fresh()->status);
    }

    public function test_transfer_driver_with_an_active_non_terminal_parcel_cannot_be_reused(): void
    {
        $fixture = $this->fixture();
        $this->actingAs($fixture['logistics'])
            ->post(route('logistics.hub-transfers.store'), [
                'shipment_id' => $fixture['shipment']->id,
                'destination_hub_id' => $fixture['destination']->id,
            ])
            ->assertSessionHasNoErrors();
        $transfer = \App\Models\HubTransfer::firstOrFail();
        $fixture['shipment']->update(['rider_id' => $fixture['driver']->id]);

        $this->actingAs($fixture['logistics'])
            ->put(route('logistics.hub-transfers.assign', $transfer), [
                'transfer_driver_id' => $fixture['driver']->id,
            ])
            ->assertSessionHasErrors('transfer_driver_id');

        $this->assertSame('pending', $transfer->fresh()->status);
    }

    public function test_transfer_exception_preserves_history_and_releases_the_driver(): void
    {
        $fixture = $this->fixture();
        $service = app(\App\Services\HubTransferService::class);
        $transfer = $service->create($fixture['shipment'], $fixture['logistics'], $fixture['destination']->id);
        $service->assignDriver($transfer, $fixture['logistics'], $fixture['driver']->id);
        $service->exception($transfer->fresh(), $fixture['logistics'], 'Vehicle inspection failed before dispatch.');

        $this->assertSame('exception', $transfer->fresh()->status);
        $this->assertSame('exception', $fixture['shipment']->fresh()->status);
        $this->assertSame('available', $fixture['driver']->riderProfile->fresh()->availability);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $fixture['shipment']->id, 'event_type' => 'hub_transfer_exception']);
        $this->assertDatabaseHas('shipment_assignments', ['shipment_id' => $fixture['shipment']->id, 'assignment_type' => 'transfer', 'status' => 'ended']);
    }

    public function test_seller_pickup_to_destination_delivery_runs_in_order_without_fake_gps(): void
    {
        $fixture = $this->fixture();
        $shipment = $fixture['shipment'];
        $shipment->update([
            'status' => 'ready_for_pickup',
            'received_at' => null,
            'parcel_scanned_at' => null,
            'sorted_at' => null,
        ]);

        $pickup = User::factory()->create(['is_active' => true]);
        $pickup->assignRole('rider');
        $pickupProfile = RiderProfile::create([
            'user_id' => $pickup->id,
            'logistics_user_id' => $fixture['logistics']->id,
            'logistics_provider_id' => $fixture['provider']->id,
            'hub_id' => $fixture['origin']->id,
            'account_status' => 'active',
            'availability' => 'available',
            'approved_service_area' => 'Santa Cruz',
            'rider_type' => 'pickup',
            'driver_license_number' => 'TEST-PICKUP',
            'driver_license_front_path' => 'test/pickup-front',
            'driver_license_back_path' => 'test/pickup-back',
            'driver_license_status' => 'verified',
            'driver_license_expires_at' => now()->addYear(),
        ]);

        $workflow = app(ParcelWorkflowService::class);
        $workflow->assignPickup($shipment->fresh(), $fixture['logistics'], $pickup);
        $workflow->acceptPickup($shipment->fresh(), $pickup);
        $workflow->confirmPickup($shipment->fresh(), $pickup, $shipment->parcel_code);
        $workflow->arriveSortingCenter($shipment->fresh(), $pickup);
        $workflow->receive($shipment->fresh(), $fixture['logistics']);
        $workflow->confirmScan($shipment->fresh(), $fixture['logistics'], $shipment->parcel_code);
        $workflow->sort($shipment->fresh(), $fixture['logistics'], $fixture['shipment']->deliveryArea);
        $this->assertSame('sorted', $shipment->fresh()->status);
        $this->assertNull($shipment->fresh()->rider_id);

        $transferService = app(\App\Services\HubTransferService::class);
        $transfer = $transferService->create($shipment->fresh(), $fixture['logistics'], $fixture['destination']->id);
        $transferService->assignDriver($transfer, $fixture['logistics'], $fixture['driver']->id);
        $transferService->dispatch($transfer->fresh(), $fixture['logistics']);
        $transferService->arrive($transfer->fresh(), $fixture['logistics']);

        $fixture['driver']->riderProfile->update([
            'hub_id' => $fixture['destination']->id,
            'rider_type' => 'delivery',
            'availability' => 'assigned',
        ]);
        $fixture['driver']->riderProfile->deliveryAreas()->attach($fixture['shipment']->delivery_area_id);
        $transferService->receive($transfer->fresh(), $fixture['logistics'], $shipment->parcel_code);
        $workflow->confirmScan($shipment->fresh(), $fixture['logistics'], $shipment->parcel_code);
        $workflow->sort($shipment->fresh(), $fixture['logistics'], $fixture['shipment']->deliveryArea);

        $afterSort = $shipment->fresh();
        $this->assertSame('assigned_to_rider', $afterSort->status);
        $this->assertSame($fixture['driver']->id, $afterSort->rider_id);
        $this->assertDatabaseHas('shipment_assignments', ['shipment_id' => $shipment->id, 'assignment_type' => 'delivery', 'rider_id' => $fixture['driver']->id, 'status' => 'assigned']);

        ProofOfDelivery::create(['shipment_id' => $shipment->id, 'submitted_by' => $fixture['driver']->id, 'recipient_name' => 'Test Buyer', 'photo_path' => 'pod/transfer-test.jpg', 'status' => 'approved', 'submitted_at' => now()]);
        $workflow->acceptDelivery($afterSort, $fixture['driver']);
        $workflow->startDelivery($shipment->fresh(), $fixture['driver']);
        app(\App\Services\CodCollectionService::class)->collect($shipment->fresh(), $fixture['driver']);
        $workflow->deliver($shipment->fresh(), $fixture['driver']);

        $this->assertSame('delivered', $shipment->fresh()->status);
        $statuses = $shipment->events()->orderBy('id')->pluck('status')->all();
        foreach (['pickup_assigned', 'pickup_accepted', 'picked_up', 'pickup_arrived', 'at_sorting_center', 'parcel_confirmed', 'sorted', 'assigned_to_rider', 'delivery_accepted', 'out_for_delivery', 'delivered'] as $status) {
            $this->assertContains($status, $statuses);
        }
        $transferEvents = $shipment->events()->whereIn('event_type', [
            'hub_transfer_scheduled', 'hub_transfer_driver_assigned', 'hub_transfer_dispatched', 'hub_transfer_arrived', 'destination_receipt_verified',
        ])->orderBy('id')->pluck('event_type')->all();
        $this->assertSame([
            'hub_transfer_scheduled', 'hub_transfer_driver_assigned', 'hub_transfer_dispatched', 'hub_transfer_arrived', 'destination_receipt_verified',
        ], $transferEvents);
    }
}
