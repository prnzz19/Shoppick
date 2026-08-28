<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $vouchers = [
            ['code' => 'WELCOME10', 'title' => 'Welcome 10% Off', 'type' => 'percent', 'value' => 10, 'min_purchase' => 500, 'max_discount' => 300, 'usage_limit' => 1000, 'per_user_limit' => 1, 'status' => 'active'],
            ['code' => 'SHOPPICK50', 'title' => 'Flat ₱50 Off', 'type' => 'fixed', 'value' => 50, 'min_purchase' => 0, 'status' => 'active'],
            ['code' => 'FREESHIP1', 'title' => 'Free Shipping', 'type' => 'percent', 'value' => 100, 'min_purchase' => 500, 'max_discount' => 100, 'usage_limit' => 500, 'per_user_limit' => 1, 'status' => 'active'],
            ['code' => 'MEGA20', 'title' => 'Mega Sale 20%', 'type' => 'percent', 'value' => 20, 'min_purchase' => 1500, 'max_discount' => 1000, 'usage_limit' => 200, 'per_user_limit' => 2, 'starts_at' => now(), 'ends_at' => now()->addDays(7), 'status' => 'active'],
            ['code' => 'EXPIRED15', 'title' => 'Expired 15%', 'type' => 'percent', 'value' => 15, 'min_purchase' => 300, 'max_discount' => 200, 'starts_at' => now()->subDays(20), 'ends_at' => now()->subDays(10), 'status' => 'expired'],
        ];

        foreach ($vouchers as $voucher) {
            Voucher::firstOrCreate(['code' => $voucher['code']], $voucher);
        }
    }
}
