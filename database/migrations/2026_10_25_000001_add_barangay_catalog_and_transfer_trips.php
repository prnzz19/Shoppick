<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psgc_barangays', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('correspondence_code', 20)->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('province');
            $table->string('municipality_code', 20);
            $table->string('municipality');
            $table->string('name');
            $table->boolean('is_urban')->default(false);
            $table->unsignedInteger('population')->nullable();
            $table->timestamps();
            $table->unique(['municipality_code', 'name']);
            $table->index(['province', 'municipality']);
        });

        Schema::create('delivery_area_barangay', function (Blueprint $table): void {
            $table->foreignId('delivery_area_id')->constrained('delivery_areas')->cascadeOnDelete();
            $table->foreignId('psgc_barangay_id')->constrained('psgc_barangays')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['delivery_area_id', 'psgc_barangay_id']);
        });

        Schema::create('hub_transfer_trips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_provider_id')->constrained('logistics_providers')->cascadeOnDelete();
            $table->foreignId('origin_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->foreignId('destination_hub_id')->constrained('logistics_hubs')->restrictOnDelete();
            $table->string('trip_reference', 80)->unique();
            $table->foreignId('transport_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->text('exception_reason')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamps();
            $table->index(['logistics_provider_id', 'status']);
        });

        Schema::create('hub_transfer_trip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained('hub_transfer_trips')->cascadeOnDelete();
            $table->foreignId('hub_transfer_id')->unique()->constrained('hub_transfers')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status', 30)->default('manifested');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('exception_reason')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'shipment_id']);
            $table->index(['trip_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_transfer_trip_items');
        Schema::dropIfExists('hub_transfer_trips');
        Schema::dropIfExists('delivery_area_barangay');
        Schema::dropIfExists('psgc_barangays');
    }
};
