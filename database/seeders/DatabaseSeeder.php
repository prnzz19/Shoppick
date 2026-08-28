<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            VoucherSeeder::class,
            OrderSeeder::class,
        ]);

        \App\Models\CommissionSetting::firstOrCreate([], ['is_enabled' => false, 'default_rate' => 0]);
    }
}
