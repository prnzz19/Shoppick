<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, LogisticsProvider, Order, Role, SellerOrder, SellerProfile, Shipment, Store, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SortingCenterScannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'buyer', 'seller', 'logistics', 'rider'] as $slug) {
            Role::create(['name' => ucfirst($slug), 'slug' => $slug, 'guard_name' => 'web']);
        }
    }

    private function provider(string $name): array
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $owner->assignRole('logistics');
        $provider = LogisticsProvider::create([
            'owner_user_id' => $owner->id,
            'company_name' => $name,
            'status' => 'approved',
            'is_active' => true,
        ]);
        $provider->users()->attach($owner->id, ['membership_role' => 'owner', 'is_active' => true]);

        return [$owner, $provider];
    }

    private function scanner(LogisticsProvider $provider, LogisticsHub $center): User
    {
        $scanner = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $scanner->assignRole('logistics');
        $provider->users()->attach($scanner->id, ['membership_role' => 'scanner', 'is_active' => true]);
        $scanner->update(['sorting_center_id' => $center->id]);

        return $scanner->fresh();
    }

    private function token(User $user): string
    {
        $plain = Str::random(64);
        DB::table('mobile_api_tokens')->insert([
            'user_id' => $user->id,
            'name' => 'phase-two-test',
            'token_hash' => hash('sha256', $plain),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plain;
    }

    private function shipment(LogisticsProvider $provider, LogisticsHub $origin, LogisticsHub $destination, string $prefix = 'SCAN'): Shipment
    {
        $seller = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $seller->assignRole('seller');
        $profile = SellerProfile::create(['user_id' => $seller->id, 'status' => 'approved']);
        $store = Store::create([
            'user_id' => $seller->id,
            'seller_profile_id' => $profile->id,
            'name' => $prefix.' Shop',
            'slug' => strtolower($prefix).'-shop-'.$seller->id,
            'status' => 'active',
        ]);
        $buyer = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $buyer->assignRole('buyer');
        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => $prefix.'-ORDER-'.$buyer->id,
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'cod',
            'subtotal' => 100,
            'total' => 100,
            'buyer_name' => $buyer->name,
            'buyer_phone' => '09170000000',
            'shipping_address' => ['address_line' => '1 Test Street'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'seller_order_number' => $order->order_number.'-S1',
            'status' => 'ready_to_ship',
            'subtotal' => 100,
            'seller_total' => 100,
        ]);

        return Shipment::create([
            'logistics_provider_id' => $provider->id,
            'shipment_number' => $prefix.'-SHIP-'.$seller->id,
            'parcel_code' => $prefix.'-WAYBILL-'.$seller->id,
            'seller_order_id' => $sellerOrder->id,
            'order_id' => $order->id,
            'store_id' => $store->id,
            'origin_hub_id' => $origin->id,
            'destination_hub_id' => $destination->id,
            'status' => 'picked_up',
        ]);
    }

    public function test_assigned_scanner_records_one_idempotent_physical_arrival(): void
    {
        [, $provider] = $this->provider('Provider A');
        $origin = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'A-ORIGIN', 'name' => 'Origin A', 'address' => 'Manila', 'status' => 'active']);
        $destination = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'A-DEST', 'name' => 'Destination A', 'address' => 'Laguna', 'status' => 'active']);
        $scanner = $this->scanner($provider, $origin);
        $shipment = $this->shipment($provider, $origin, $destination);
        $headers = ['Authorization' => 'Bearer '.$this->token($scanner)];

        $this->withHeaders($headers)->getJson('/api/v1/logistics/sorting-center/context')
            ->assertOk()->assertJsonPath('sorting_center.id', $origin->id)->assertJsonPath('scanner_ready', true);
        $first = $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code]);
        $first->assertCreated()->assertJsonPath('duplicate', false)->assertJsonPath('shipment_current_status', 'at_sorting_center');
        $second = $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code]);
        $second->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'current_hub_id' => $origin->id, 'status' => 'at_sorting_center']);
        $this->assertSame(1, DB::table('shipment_events')->where('event_key', 'physical-arrival:'.$shipment->id.':'.$origin->id)->count());
    }

    public function test_destination_scan_records_one_idempotent_arrival_without_assigning_a_rider(): void
    {
        [, $provider] = $this->provider('Provider Destination');
        $origin = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'D-ORIGIN', 'name' => 'Origin D', 'address' => 'Manila', 'status' => 'active']);
        $destination = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'D-DEST', 'name' => 'Destination D', 'address' => 'Calamba', 'province' => 'Laguna', 'municipality' => 'Calamba', 'status' => 'active']);
        $scanner = $this->scanner($provider, $destination);
        $shipment = $this->shipment($provider, $origin, $destination, 'DEST');
        $shipment->update(['current_hub_id' => $origin->id, 'next_hub_id' => $destination->id, 'status' => 'in_transit']);
        $headers = ['Authorization' => 'Bearer '.$this->token($scanner)];

        $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code])->assertCreated()->assertJsonPath('shipment_current_status', 'at_sorting_center');
        $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code])->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'current_hub_id' => $destination->id, 'status' => 'at_sorting_center']);
        $this->assertDatabaseCount('shipment_assignments', 0);
        $this->assertSame(1, DB::table('shipment_events')->where('event_key', 'physical-arrival:'.$shipment->id.':'.$destination->id)->count());
    }

    public function test_scanner_enforces_center_and_provider_isolation(): void
    {
        [, $providerA] = $this->provider('Provider A');
        $originA = LogisticsHub::create(['logistics_provider_id' => $providerA->id, 'code' => 'A-ORIGIN', 'name' => 'Origin A', 'address' => 'Manila', 'status' => 'active']);
        $destinationA = LogisticsHub::create(['logistics_provider_id' => $providerA->id, 'code' => 'A-DEST', 'name' => 'Destination A', 'address' => 'Laguna', 'status' => 'active']);
        $scannerA = $this->scanner($providerA, $originA);
        $shipmentA = $this->shipment($providerA, $originA, $destinationA, 'A');

        [, $providerB] = $this->provider('Provider B');
        $centerB = LogisticsHub::create(['logistics_provider_id' => $providerB->id, 'code' => 'B-CENTER', 'name' => 'Center B', 'address' => 'Cebu', 'status' => 'active']);
        $scannerB = $this->scanner($providerB, $centerB);
        $headersB = ['Authorization' => 'Bearer '.$this->token($scannerB)];

        $this->withHeaders($headersB)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipmentA->parcel_code])->assertNotFound();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token($scannerA)])->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipmentA->parcel_code.'-wrong-center'])->assertNotFound();
        $wrong = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $wrong->assignRole('logistics');
        $providerA->users()->attach($wrong->id, ['membership_role' => 'scanner', 'is_active' => true]);
        $wrong->update(['sorting_center_id' => $destinationA->id]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token($wrong)])->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipmentA->parcel_code])->assertStatus(409);
    }

    public function test_non_logistics_or_unassigned_users_cannot_use_scanner_api(): void
    {
        [, $provider] = $this->provider('Provider A');
        $center = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'CENTER', 'name' => 'Center', 'address' => 'Manila', 'status' => 'active']);
        foreach (['admin', 'buyer', 'seller', 'rider'] as $role) {
            $user = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
            $user->assignRole($role);
            $this->withHeaders(['Authorization' => 'Bearer '.$this->token($user)])->getJson('/api/v1/logistics/sorting-center/context')->assertForbidden();
        }

        $unassigned = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $unassigned->assignRole('logistics');
        $provider->users()->attach($unassigned->id, ['membership_role' => 'manager', 'is_active' => true]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token($unassigned)])->getJson('/api/v1/logistics/sorting-center/context')->assertForbidden();

        $center->update(['status' => 'inactive']);
    }

    public function test_terminal_and_unrouted_shipments_are_rejected(): void
    {
        [, $provider] = $this->provider('Provider A');
        $center = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'CENTER', 'name' => 'Center', 'address' => 'Manila', 'status' => 'active']);
        $scanner = $this->scanner($provider, $center);
        $shipment = $this->shipment($provider, $center, $center, 'TERM');
        $shipment->update(['status' => 'delivered']);
        $headers = ['Authorization' => 'Bearer '.$this->token($scanner)];
        $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code])->assertUnprocessable();

        $unrouted = $this->shipment($provider, $center, $center, 'NOROUTE');
        $unrouted->update(['origin_hub_id' => null, 'destination_hub_id' => null]);
        $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $unrouted->parcel_code])->assertUnprocessable();
    }

    public function test_center_to_center_departure_does_not_fake_the_next_arrival(): void
    {
        [, $provider] = $this->provider('Provider A');
        $origin = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'A-ORIGIN', 'name' => 'Origin A', 'address' => 'Manila', 'status' => 'active']);
        $next = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'A-NEXT', 'name' => 'Next A', 'address' => 'Laguna', 'status' => 'active']);
        $scannerOrigin = $this->scanner($provider, $origin);
        $scannerNext = $this->scanner($provider, $next);
        $shipment = $this->shipment($provider, $origin, $next, 'LANE');
        $originHeaders = ['Authorization' => 'Bearer '.$this->token($scannerOrigin)];
        $nextHeaders = ['Authorization' => 'Bearer '.$this->token($scannerNext)];

        $this->withHeaders($originHeaders)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code])->assertCreated();
        $shipment->update(['next_hub_id' => $next->id, 'status' => 'in_transit']);
        $shipment->events()->create(['actor_id' => $scannerOrigin->id, 'status' => 'in_transit', 'event_type' => 'center_departure', 'location' => $origin->name, 'note' => 'Departed Origin A for Next A.']);

        $this->assertDatabaseMissing('shipment_events', ['shipment_id' => $shipment->id, 'event_type' => 'physical_arrival', 'sorting_center_id' => $next->id]);
        $this->withHeaders($nextHeaders)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipment->parcel_code])->assertCreated()->assertJsonPath('sorting_center.id', $next->id);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'event_type' => 'center_departure', 'sorting_center_id' => null]);
        $this->assertDatabaseHas('shipment_events', ['shipment_id' => $shipment->id, 'event_type' => 'physical_arrival', 'sorting_center_id' => $next->id]);
        $this->assertSame($next->id, $shipment->fresh()->current_hub_id);
    }

    public function test_multi_seller_shipments_remain_independent_when_one_waybill_is_scanned(): void
    {
        [, $provider] = $this->provider('Provider A');
        $centerA = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'A-CENTER', 'name' => 'Center A', 'address' => 'Manila', 'status' => 'active']);
        $centerB = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'B-CENTER', 'name' => 'Center B', 'address' => 'Cebu', 'status' => 'active']);
        $scanner = $this->scanner($provider, $centerA);
        $shipmentA = $this->shipment($provider, $centerA, $centerB, 'SELLER-A');
        $shipmentB = $this->shipment($provider, $centerB, $centerA, 'SELLER-B');
        $headers = ['Authorization' => 'Bearer '.$this->token($scanner)];

        $this->withHeaders($headers)->postJson('/api/v1/logistics/sorting-center/scan', ['waybill' => $shipmentA->parcel_code])->assertCreated();

        $this->assertSame('at_sorting_center', $shipmentA->fresh()->status);
        $this->assertSame('picked_up', $shipmentB->fresh()->status);
        $this->assertSame($centerB->id, $shipmentB->fresh()->current_hub_id ?: $shipmentB->fresh()->origin_hub_id);
        $this->assertDatabaseMissing('shipment_events', ['shipment_id' => $shipmentB->id, 'event_type' => 'physical_arrival']);
    }
}
