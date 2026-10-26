<?php

namespace Tests\Feature;

use App\Models\PsgcLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhilippineLocalCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.psgc.local_catalog' => true, 'services.psgc.cache_store' => 'array']);
        Http::preventStrayRequests();
        PsgcLocation::insert([
            ['code' => '0400000000', 'level' => 'region', 'name' => 'Region IV-A (CALABARZON)', 'parent_code' => null, 'region_code' => '0400000000', 'province_code' => null, 'municipality_code' => null, 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '0403400000', 'level' => 'province', 'name' => 'Laguna', 'parent_code' => '0400000000', 'region_code' => '0400000000', 'province_code' => '0403400000', 'municipality_code' => null, 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '0403407000', 'level' => 'city_municipality', 'name' => 'Cavinti', 'parent_code' => '0403400000', 'region_code' => '0400000000', 'province_code' => '0403400000', 'municipality_code' => '0403407000', 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '0403407012', 'level' => 'barangay', 'name' => 'Mahipon', 'parent_code' => '0403407000', 'region_code' => '0400000000', 'province_code' => '0403400000', 'municipality_code' => '0403407000', 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1300000000', 'level' => 'region', 'name' => 'National Capital Region (NCR)', 'parent_code' => null, 'region_code' => '1300000000', 'province_code' => null, 'municipality_code' => null, 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1380600000', 'level' => 'city_municipality', 'name' => 'City of Manila', 'parent_code' => '1300000000', 'region_code' => '1300000000', 'province_code' => null, 'municipality_code' => '1380600000', 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1380600001', 'level' => 'barangay', 'name' => 'Barangay 1', 'parent_code' => '1380600000', 'region_code' => '1300000000', 'province_code' => null, 'municipality_code' => '1380600000', 'source_version' => 'Q3_2026', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_local_catalog_serves_the_complete_hierarchy_without_network_access(): void
    {
        $this->getJson(route('locations.regions'))->assertOk()->assertJsonFragment(['code' => '1300000000', 'name' => 'National Capital Region (NCR)']);
        $this->getJson(route('locations.provinces', '0400000000'))->assertOk()->assertJsonFragment(['code' => '0403400000', 'name' => 'Laguna']);
        $this->getJson(route('locations.province-cities', '0403400000'))->assertOk()->assertJsonFragment(['code' => '0403407000', 'name' => 'Cavinti']);
        $this->getJson(route('locations.region-cities', '1300000000'))->assertOk()->assertJsonFragment(['code' => '1380600000', 'name' => 'City of Manila']);
        $this->getJson(route('locations.barangays', '0403407000'))->assertOk()->assertJsonFragment(['code' => '0403407012', 'name' => 'Mahipon']);
        Http::assertNothingSent();
    }

    public function test_local_catalog_rejects_a_missing_parent_without_exposing_provider_details(): void
    {
        $this->getJson(route('locations.provinces', '9999999999'))
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid Philippine location code.']);
        Http::assertNothingSent();
    }

}
