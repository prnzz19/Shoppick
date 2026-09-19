<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->string('driver_license_number', 80)->nullable()->unique();
            $table->string('driver_license_classification', 100)->nullable();
            $table->date('driver_license_expires_at')->nullable()->index();
            $table->string('driver_license_front_path')->nullable();
            $table->string('driver_license_back_path')->nullable();
            $table->string('driver_license_status', 30)->default('pending_review')->index();
            $table->text('driver_license_rejection_reason')->nullable();
            $table->foreignId('driver_license_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('driver_license_reviewed_at')->nullable();
        });

        Schema::create('rider_license_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['rider_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_license_audits');
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_license_reviewed_by');
            $table->dropUnique(['driver_license_number']);
            $table->dropColumn([
                'driver_license_number', 'driver_license_classification',
                'driver_license_expires_at', 'driver_license_front_path',
                'driver_license_back_path', 'driver_license_status',
                'driver_license_rejection_reason', 'driver_license_reviewed_at',
            ]);
        });
    }
};
