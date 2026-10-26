<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('logistics_hubs')) {
            Schema::table('logistics_hubs', function (Blueprint $table): void {
                if (! Schema::hasColumn('logistics_hubs', 'street')) $table->string('street')->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'barangay')) $table->string('barangay')->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'municipality')) $table->string('municipality')->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'province')) $table->string('province')->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'postal_code')) $table->string('postal_code', 12)->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'latitude')) $table->decimal('latitude', 10, 7)->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'longitude')) $table->decimal('longitude', 10, 7)->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'contact_phone')) $table->string('contact_phone', 30)->nullable();
                if (! Schema::hasColumn('logistics_hubs', 'operating_hours')) $table->string('operating_hours', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'sorting_center_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('sorting_center_id')->nullable()->after('is_active')->constrained('logistics_hubs')->nullOnDelete();
                $table->index(['sorting_center_id', 'is_active']);
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table): void {
                if (! Schema::hasColumn('shipments', 'origin_hub_id')) $table->foreignId('origin_hub_id')->nullable()->after('current_hub_id')->constrained('logistics_hubs')->nullOnDelete();
                if (! Schema::hasColumn('shipments', 'next_hub_id')) $table->foreignId('next_hub_id')->nullable()->after('origin_hub_id')->constrained('logistics_hubs')->nullOnDelete();
                if (! Schema::hasColumn('shipments', 'destination_hub_id')) $table->foreignId('destination_hub_id')->nullable()->after('next_hub_id')->constrained('logistics_hubs')->nullOnDelete();
                $table->index(['logistics_provider_id', 'next_hub_id', 'status'], 'shipments_provider_next_hub_status_index');
            });
        }

        if (Schema::hasTable('shipment_events')) {
            Schema::table('shipment_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('shipment_events', 'sorting_center_id')) $table->foreignId('sorting_center_id')->nullable()->after('actor_id')->constrained('logistics_hubs')->nullOnDelete();
                if (! Schema::hasColumn('shipment_events', 'event_type')) $table->string('event_type')->nullable()->after('status');
                if (! Schema::hasColumn('shipment_events', 'event_key')) $table->string('event_key', 191)->nullable()->unique('shipment_events_event_key_unique');
            });
        }

        if (! Schema::hasTable('delivery_area_hub')) {
            Schema::create('delivery_area_hub', function (Blueprint $table): void {
                $table->foreignId('delivery_area_id')->constrained()->cascadeOnDelete();
                $table->foreignId('logistics_hub_id')->constrained('logistics_hubs')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['delivery_area_id', 'logistics_hub_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_area_hub');
        if (Schema::hasTable('shipment_events')) Schema::table('shipment_events', function (Blueprint $table): void {
            if (Schema::hasColumn('shipment_events', 'event_key')) $table->dropUnique('shipment_events_event_key_unique');
            foreach (['sorting_center_id', 'event_type', 'event_key'] as $column) if (Schema::hasColumn('shipment_events', $column)) $table->dropColumn($column);
        });
        if (Schema::hasTable('shipments')) Schema::table('shipments', function (Blueprint $table): void {
            if (Schema::hasIndex('shipments', 'shipments_provider_next_hub_status_index')) $table->dropIndex('shipments_provider_next_hub_status_index');
            foreach (['origin_hub_id', 'next_hub_id', 'destination_hub_id'] as $column) if (Schema::hasColumn('shipments', $column)) $table->dropConstrainedForeignId($column);
        });
        if (Schema::hasColumn('users', 'sorting_center_id')) Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('sorting_center_id'));
        if (Schema::hasTable('logistics_hubs')) Schema::table('logistics_hubs', function (Blueprint $table): void {
            foreach (['street', 'barangay', 'municipality', 'province', 'postal_code', 'latitude', 'longitude', 'contact_phone', 'operating_hours'] as $column) if (Schema::hasColumn('logistics_hubs', $column)) $table->dropColumn($column);
        });
    }
};
