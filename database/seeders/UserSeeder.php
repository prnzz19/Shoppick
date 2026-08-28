<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\SellerProfile;
use App\Models\Store;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@shoppick.test'],
            [
                'name' => 'SHOPPICK Super Admin',
                'phone' => '09170000001',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles('super_admin');

        $admin = User::firstOrCreate(
            ['email' => 'admin@shoppick.test'],
            [
                'name' => 'SHOPPICK Admin',
                'phone' => '09170000002',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles('admin');

        $buyer = User::firstOrCreate(
            ['email' => 'buyer@shoppick.test'],
            [
                'name' => 'Maria Santos',
                'phone' => '09170000003',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $buyer->syncRoles('buyer');

        $seller = User::firstOrCreate(['email' => 'seller@shoppick.test'], [
            'name' => 'SHOPPICK Demo Seller', 'phone' => '09170000007', 'password' => Hash::make('password'),
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $seller->syncRoles('seller');
        $profile = SellerProfile::firstOrCreate(['user_id' => $seller->id], [
            'phone' => $seller->phone, 'address' => 'Quezon City, Metro Manila',
            'business_information' => 'Development demo seller', 'status' => 'approved', 'approved_at' => now(),
        ]);
        Store::firstOrCreate(['user_id' => $seller->id], [
            'seller_profile_id' => $profile->id, 'name' => 'Panda Picks', 'slug' => 'panda-picks',
            'description' => 'Official SHOPPICK development seller store.', 'location' => 'Quezon City', 'status' => 'active',
        ]);

        // A few sample buyers
        foreach ([
            ['Juan Dela Cruz', 'juandc@example.com', '09170000004'],
            ['Ana Reyes', 'anareyes@example.com', '09170000005'],
            ['Carlos Garcia', 'carlosg@example.com', '09170000006'],
        ] as $i => [$name, $email, $phone]) {
            $u = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $u->syncRoles('buyer');
        }
    }
}
