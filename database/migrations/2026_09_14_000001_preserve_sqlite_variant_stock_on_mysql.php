<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Legacy SQLite INTEGER affinity allowed fractional stock values.
        // DOUBLE preserves those existing values without silently rounding inventory.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('product_variants', fn (Blueprint $table) => $table->double('stock')->default(0)->change());
        }
    }

    public function down(): void
    {
        // No automatic narrowing: converting back to INTEGER could lose inventory data.
    }
};
