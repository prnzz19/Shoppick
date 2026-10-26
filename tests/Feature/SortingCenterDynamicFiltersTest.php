<?php

namespace Tests\Feature;

use App\Models\{DeliveryArea, LogisticsHub, LogisticsProvider, Permission, PsgcBarangay, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortingCenterDynamicFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $logistics = Role::create(['name' => 'Logistics', 'slug' => 'logistics', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'View Shipments', 'slug' => 'view_shipments', 'guard_name' => 'web', 'group' => 'Logistics']);
        $logistics->permissions()->attach($permission);
    }

    private function provider(): array
    {
        $owner = User::factory()->create(['is_active' => true, 'registration_status' => 'approved']);
        $owner->assignRole('logistics');
        $provider = LogisticsProvider::create([
            'owner_user_id' => $owner->id, 'company_name' => 'Sorting API Provider',
            'status' => 'approved', 'is_active' => true,
        ]);
        $provider->users()->attach($owner->id, ['membership_role' => 'owner', 'is_active' => true]);
        return [$owner, $provider];
    }

    public function test_manager_can_select_provider_centers_and_covered_areas(): void
    {
        [$owner, $provider] = $this->provider();
        $origin = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'API-ORIGIN', 'name' => 'Origin Hub', 'address' => 'Origin address', 'status' => 'active']);
        $destination = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'API-DEST', 'name' => 'Destination Hub', 'address' => 'Destination address', 'status' => 'active']);
        $area = DeliveryArea::create(['logistics_provider_id' => $provider->id, 'code' => 'API-AREA', 'name' => 'Destination Area', 'province' => 'Laguna', 'municipality' => 'Cavinti', 'barangays' => ['Poblacion']]);
        $area->sortingCenters()->attach($destination->id);

        $this->actingAs($owner)->getJson(route('logistics.api.sorting-centers'))->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($owner)->getJson(route('logistics.api.sorting-delivery-areas', ['hub_id' => $destination->id]))
            ->assertOk()->assertJsonPath('data.0.id', $area->id);
        $this->actingAs($owner)->getJson(route('logistics.api.sorting-summary', ['hub_id' => $origin->id]))
            ->assertOk()->assertJsonPath('summary.total', 0);
    }

    public function test_restricted_staff_cannot_view_another_center(): void
    {
        [, $provider] = $this->provider();
        $assigned = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'API-ASSIGNED', 'name' => 'Assigned Hub', 'address' => 'Assigned address', 'status' => 'active']);
        $other = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'code' => 'API-OTHER', 'name' => 'Other Hub', 'address' => 'Other address', 'status' => 'active']);
        $staff = User::factory()->create(['is_active' => true, 'registration_status' => 'approved', 'sorting_center_id' => $assigned->id]);
        $staff->assignRole('logistics');
        $provider->users()->attach($staff->id, ['membership_role' => 'scanner', 'is_active' => true]);

        $this->actingAs($staff)->getJson(route('logistics.api.sorting-centers'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
        $this->actingAs($staff)->getJson(route('logistics.api.sorting-summary', ['hub_id' => $other->id]))->assertForbidden();
    }

    public function test_sorting_barangays_returns_only_the_configured_municipality_catalog(): void
    {
        [$owner, $provider] = $this->provider();
        $hub = LogisticsHub::create([
            'logistics_provider_id' => $provider->id, 'code' => 'API-CAV', 'name' => 'Cavinti Hub',
            'address' => 'Cavinti address', 'municipality' => 'Cavinti', 'province' => 'Laguna', 'status' => 'active',
        ]);
        PsgcBarangay::create(['code' => '0403407001', 'correspondence_code' => '043407001', 'province_code' => '04', 'province' => 'Laguna', 'municipality_code' => '0403407000', 'municipality' => 'Cavinti', 'name' => 'Anglas']);
        PsgcBarangay::create(['code' => '0403407002', 'correspondence_code' => '043407002', 'province_code' => '04', 'province' => 'Laguna', 'municipality_code' => '0403407000', 'municipality' => 'Cavinti', 'name' => 'Bangco']);
        PsgcBarangay::create(['code' => '0405601001', 'correspondence_code' => '045601001', 'province_code' => '04', 'province' => 'Laguna', 'municipality_code' => '0405601000', 'municipality' => 'Santa Cruz', 'name' => 'Alingaro']);

        $this->actingAs($owner)->getJson(route('logistics.api.sorting-barangays', ['hub_id' => $hub->id]))
            ->assertOk()->assertJsonPath('selected_municipality_code', '0403407000')
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.municipality', 'Cavinti');
    }
}
