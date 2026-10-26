<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('rider_profiles')) {
            return;
        }

        Schema::table('rider_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('rider_profiles', 'last_latitude')) {
                $table->decimal('last_latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('rider_profiles', 'last_longitude')) {
                $table->decimal('last_longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('rider_profiles', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable();
            }
        });

        if (! Schema::hasIndex('rider_profiles', 'rider_profiles_location_freshness')) {
            Schema::table('rider_profiles', function (Blueprint $table): void {
                $table->index(['last_location_at', 'availability'], 'rider_profiles_location_freshness');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rider_profiles')) {
            return;
        }

        Schema::table('rider_profiles', function (Blueprint $table): void {
            if (Schema::hasIndex('rider_profiles', 'rider_profiles_location_freshness')) {
                $table->dropIndex('rider_profiles_location_freshness');
            }
            foreach (['last_latitude', 'last_longitude', 'last_location_at'] as $column) {
                if (Schema::hasColumn('rider_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
