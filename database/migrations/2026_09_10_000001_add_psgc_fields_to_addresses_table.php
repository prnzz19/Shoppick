<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('region')->nullable()->after('phone');
            $table->string('region_code', 20)->nullable()->after('region');
            $table->string('province_code', 20)->nullable()->after('province');
            $table->string('city_code', 20)->nullable()->after('city');
            $table->string('barangay_code', 20)->nullable()->after('barangay');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['region', 'region_code', 'province_code', 'city_code', 'barangay_code']);
        });
    }
};
