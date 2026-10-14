<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->foreignId('logistics_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['logistics_user_id', 'account_status']);
        });

        Schema::create('rider_activation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_activation_tokens');
        Schema::table('rider_profiles', function (Blueprint $table) {
            $table->dropIndex(['logistics_user_id', 'account_status']);
            $table->dropConstrainedForeignId('logistics_user_id');
        });
    }
};
