<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('logistics_providers')) {
            Schema::create('logistics_providers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('company_name');
                $table->string('contact_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->string('registration_number')->nullable();
                $table->string('permit_path')->nullable();
                $table->string('status')->default('pending')->index();
                $table->boolean('is_active')->default(false)->index();
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'is_active']);
            });
        }
        if (Schema::hasTable('rider_profiles') && !Schema::hasColumn('rider_profiles', 'logistics_provider_id')) {
            Schema::table('rider_profiles', function (Blueprint $table) {
                $table->foreignId('logistics_provider_id')->nullable()->after('logistics_user_id')->constrained('logistics_providers')->nullOnDelete();
                $table->index(['logistics_provider_id', 'account_status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rider_profiles') && Schema::hasColumn('rider_profiles', 'logistics_provider_id')) Schema::table('rider_profiles', fn (Blueprint $table) => $table->dropConstrainedForeignId('logistics_provider_id'));
        Schema::dropIfExists('logistics_providers');
    }
};
