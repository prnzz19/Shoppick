<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('seller_applications')) {
            Schema::table('seller_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('seller_applications', 'seller_type')) $table->string('seller_type', 40)->nullable();
                if (! Schema::hasColumn('seller_applications', 'tin')) $table->string('tin', 15)->nullable();
                if (! Schema::hasColumn('seller_applications', 'dti_certificate_path')) $table->string('dti_certificate_path')->nullable();
                if (! Schema::hasColumn('seller_applications', 'sec_certificate_path')) $table->string('sec_certificate_path')->nullable();
                if (! Schema::hasColumn('seller_applications', 'bir_2303_path')) $table->string('bir_2303_path')->nullable();
                if (! Schema::hasColumn('seller_applications', 'region')) $table->string('region')->nullable();
                if (! Schema::hasColumn('seller_applications', 'region_code')) $table->string('region_code', 20)->nullable();
                if (! Schema::hasColumn('seller_applications', 'province')) $table->string('province')->nullable();
                if (! Schema::hasColumn('seller_applications', 'province_code')) $table->string('province_code', 20)->nullable();
                if (! Schema::hasColumn('seller_applications', 'city')) $table->string('city')->nullable();
                if (! Schema::hasColumn('seller_applications', 'city_code')) $table->string('city_code', 20)->nullable();
                if (! Schema::hasColumn('seller_applications', 'barangay')) $table->string('barangay')->nullable();
                if (! Schema::hasColumn('seller_applications', 'barangay_code')) $table->string('barangay_code', 20)->nullable();
                if (! Schema::hasColumn('seller_applications', 'postal_code')) $table->string('postal_code', 4)->nullable();
            });
            Schema::table('seller_applications', function (Blueprint $table) {
                $table->unique('tin', 'seller_applications_tin_unique');
            });
        }

        if (Schema::hasTable('logistics_providers')) {
            Schema::table('logistics_providers', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_providers', 'sec_registration_number')) $table->string('sec_registration_number', 11)->nullable();
                if (! Schema::hasColumn('logistics_providers', 'tin')) $table->string('tin', 15)->nullable();
                if (! Schema::hasColumn('logistics_providers', 'region')) $table->string('region')->nullable();
                if (! Schema::hasColumn('logistics_providers', 'region_code')) $table->string('region_code', 20)->nullable();
                if (! Schema::hasColumn('logistics_providers', 'province_code')) $table->string('province_code', 20)->nullable();
                if (! Schema::hasColumn('logistics_providers', 'city_code')) $table->string('city_code', 20)->nullable();
                if (! Schema::hasColumn('logistics_providers', 'barangay_code')) $table->string('barangay_code', 20)->nullable();
                if (! Schema::hasColumn('logistics_providers', 'service_regions')) $table->json('service_regions')->nullable();
            });
            Schema::table('logistics_providers', function (Blueprint $table) {
                $table->unique('sec_registration_number', 'logistics_providers_sec_unique');
                $table->unique('tin', 'logistics_providers_tin_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('logistics_providers')) {
            Schema::table('logistics_providers', function (Blueprint $table) {
                $table->dropUnique('logistics_providers_sec_unique');
                $table->dropUnique('logistics_providers_tin_unique');
                $table->dropColumn(['sec_registration_number','tin','region','region_code','province_code','city_code','barangay_code','service_regions']);
            });
        }
        if (Schema::hasTable('seller_applications')) {
            Schema::table('seller_applications', function (Blueprint $table) {
                $table->dropUnique('seller_applications_tin_unique');
                $table->dropColumn(['seller_type','tin','dti_certificate_path','sec_certificate_path','bir_2303_path','region','region_code','province','province_code','city','city_code','barangay','barangay_code','postal_code']);
            });
        }
    }
};
