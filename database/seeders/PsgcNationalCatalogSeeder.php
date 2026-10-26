<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds only an operator-reviewed PSA export. It never calls the network and
 * therefore cannot silently replace the catalog with unverified data.
 */
class PsgcNationalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $file = (string) config('services.psgc.catalog_file');
        if ($file === '' || ! is_file($file)) {
            throw new RuntimeException('PSGC_CATALOG_FILE must point to a reviewed PSA export before seeding.');
        }

        $this->command?->call('psgc:import', [
            'file' => $file,
            '--release' => config('services.psgc.source_version', 'Q3_2026'),
        ]);
    }
}
