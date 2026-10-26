<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table): void {
            $table->decimal('pickup_latitude', 10, 7)->nullable()->after('location');
            $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            $table->timestamp('pickup_location_confirmed_at')->nullable()->after('pickup_longitude');
            $table->index(['pickup_latitude', 'pickup_longitude'], 'stores_pickup_coordinates_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropIndex('stores_pickup_coordinates_index');
            $table->dropColumn(['pickup_latitude', 'pickup_longitude', 'pickup_location_confirmed_at']);
        });
    }
};