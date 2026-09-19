<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('hub_id')->constrained()->nullOnDelete();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->json('notification_preferences')->nullable();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('remittance_status', 30)->nullable()->index();
            $table->timestamp('remitted_at')->nullable();
            $table->foreignId('remitted_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('rider_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_messages');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remitted_by');
            $table->dropColumn(['remittance_status', 'remitted_at']);
        });
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone', 'notification_preferences']);
        });
    }
};
