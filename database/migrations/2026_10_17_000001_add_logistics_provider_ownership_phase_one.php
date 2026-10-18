<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logistics_provider_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('membership_role')->default('manager');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['logistics_provider_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->string('trade_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('representative_position')->nullable();
            $table->string('representative_email')->nullable();
            $table->string('representative_phone', 30)->nullable();
            $table->string('representative_id_path')->nullable();
            $table->string('street')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('business_registration_path')->nullable();
            $table->text('tax_registration_info')->nullable();
            $table->string('tax_registration_path')->nullable();
            $table->json('areas_served')->nullable();
            $table->json('pickup_coverage_areas')->nullable();
            $table->json('delivery_coverage_areas')->nullable();
            $table->text('operating_hours')->nullable();
            $table->unsignedInteger('number_of_riders')->default(0);
            $table->unsignedInteger('number_of_vehicles')->default(0);
            $table->json('supported_vehicle_types')->nullable();
            $table->json('operations_contact')->nullable();
            $table->json('customer_support_contact')->nullable();
            $table->json('emergency_contact')->nullable();
        });

        foreach (['logistics_hubs', 'vehicles', 'shipments', 'delivery_areas', 'logistics_invoices', 'logistics_settings', 'logistics_insights'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('logistics_provider_id')->nullable()->constrained('logistics_providers')->nullOnDelete();
                $table->index(['logistics_provider_id', 'id'], $tableName.'_provider_id_index');
            });
        }

        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable();
            $table->string('requested_service_area')->nullable();
            $table->string('approved_service_area')->nullable();
            $table->foreignId('requested_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $table->string('requested_rider_type')->nullable();
            $table->string('rider_type')->nullable();
            $table->string('vehicle_make_model')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->string('vehicle_registration_number')->nullable();
            $table->string('proof_of_address_path')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
        });

        DB::table('logistics_providers')->whereNotNull('owner_user_id')->orderBy('id')->each(function ($provider) {
            DB::table('logistics_provider_user')->insertOrIgnore([
                'logistics_provider_id' => $provider->id,
                'user_id' => $provider->owner_user_id,
                'membership_role' => 'owner',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_hub_id');
            $table->dropColumn(['profile_photo_path','requested_service_area','approved_service_area','requested_rider_type','rider_type','vehicle_make_model','vehicle_color','vehicle_registration_number','proof_of_address_path','emergency_contact_relationship']);
        });
        foreach (['logistics_insights', 'logistics_settings', 'logistics_invoices', 'delivery_areas', 'shipments', 'vehicles', 'logistics_hubs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex($tableName.'_provider_id_index');
                $table->dropConstrainedForeignId('logistics_provider_id');
            });
        }
        Schema::table('logistics_providers', function (Blueprint $table) {
            $table->dropColumn(['trade_name','logo_path','representative_position','representative_email','representative_phone','representative_id_path','street','barangay','city','province','postal_code','business_registration_path','tax_registration_info','tax_registration_path','areas_served','pickup_coverage_areas','delivery_coverage_areas','operating_hours','number_of_riders','number_of_vehicles','supported_vehicle_types','operations_contact','customer_support_contact','emergency_contact']);
        });
        Schema::dropIfExists('logistics_provider_user');
    }
};
