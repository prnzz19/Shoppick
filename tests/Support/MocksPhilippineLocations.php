<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Isolate existing web registration/account tests from the external location provider. */
trait MocksPhilippineLocations
{
    protected function setUpMocksPhilippineLocations(): void
    {
        config(['services.psgc.base_url' => 'https://psgc.test/api', 'services.psgc.cache_store' => 'array']);
        Cache::store('array')->flush();
        Http::fake([
            'https://psgc.test/api/regions' => Http::response([['code' => '0400000000', 'name' => 'Region IV-A (CALABARZON)']]),
            'https://psgc.test/api/regions/0400000000/provinces' => Http::response([['code' => '0403400000', 'name' => 'Laguna']]),
            'https://psgc.test/api/provinces/0403400000/cities-municipalities' => Http::response([
                ['code' => '0403407000', 'name' => 'Cavinti'], ['code' => '0403426000', 'name' => 'Santa Cruz'],
            ]),
            'https://psgc.test/api/cities-municipalities/0403407000/barangays' => Http::response([['code' => '0403407012', 'name' => 'Mahipon']]),
            'https://psgc.test/api/cities-municipalities/0403426000/barangays' => Http::response([['code' => '0403426003', 'name' => 'Bubukal']]),
        ]);
    }
}
