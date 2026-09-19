<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhilippineLocationCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config(['services.psgc.base_url' => 'https://psgc.test/api']);
    }

    public function test_location_endpoints_proxy_all_provider_records_without_a_hardcoded_region_list(): void
    {
        Http::fake([
            'https://psgc.test/api/regions' => Http::response([
                ['code'=>'1300000000', 'name'=>'National Capital Region'],
                ['code'=>'0300000000', 'name'=>'Region III'],
                ['code'=>'0400000000', 'name'=>'Region IV-A'],
                ['code'=>'0600000000', 'name'=>'Region VI'],
                ['code'=>'1800000000', 'name'=>'Negros Island Region'],
                ['code'=>'1100000000', 'name'=>'Region XI'],
            ]),
            'https://psgc.test/api/regions/0400000000/provinces' => Http::response([['code'=>'0434000000', 'name'=>'Laguna']]),
            'https://psgc.test/api/provinces/0434000000/cities-municipalities' => Http::response([['code'=>'0434060000', 'name'=>'Cavinti']]),
            'https://psgc.test/api/cities-municipalities/0434060000/barangays' => Http::response([['code'=>'0434060150', 'name'=>'Mahipon']]),
            'https://psgc.test/api/regions/1300000000/provinces' => Http::response([]),
            'https://psgc.test/api/regions/1300000000/cities-municipalities' => Http::response([['code'=>'1380600000', 'name'=>'City of Manila']]),
        ]);

        $this->getJson(route('locations.regions'))->assertOk()->assertJsonCount(6, 'data')->assertJsonFragment(['name'=>'Negros Island Region']);
        $this->getJson(route('locations.provinces', '0400000000'))->assertOk()->assertJsonFragment(['name'=>'Laguna']);
        $this->getJson(route('locations.province-cities', '0434000000'))->assertOk()->assertJsonFragment(['name'=>'Cavinti']);
        $this->getJson(route('locations.barangays', '0434060000'))->assertOk()->assertJsonFragment(['name'=>'Mahipon']);
        $this->getJson(route('locations.provinces', '1300000000'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('locations.region-cities', '1300000000'))->assertOk()->assertJsonFragment(['name'=>'City of Manila']);
    }

    public function test_buyer_and_seller_flows_render_the_same_nationwide_component(): void
    {
        $this->get(route('register.buyer'))
            ->assertOk()
            ->assertSee('data-ph-location', false)
            ->assertSee('name="region_code"', false);

        $this->get(route('register.seller'))
            ->assertOk()
            ->assertSee('name="region_code"', false)
            ->assertSee('name="store_region_code"', false);
    }
}
