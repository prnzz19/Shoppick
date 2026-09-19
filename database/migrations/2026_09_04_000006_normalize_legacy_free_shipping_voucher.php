<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vouchers')
            ->where('code', 'FREESHIP1')
            ->where('title', 'Free Shipping')
            ->where('type', 'percent')
            ->where('value', 100)
            ->update(['type' => 'free_shipping', 'value' => 0]);
    }

    public function down(): void
    {
        DB::table('vouchers')
            ->where('code', 'FREESHIP1')
            ->where('title', 'Free Shipping')
            ->where('type', 'free_shipping')
            ->where('value', 0)
            ->update(['type' => 'percent', 'value' => 100]);
    }
};
