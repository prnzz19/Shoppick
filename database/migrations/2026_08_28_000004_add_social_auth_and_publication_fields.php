<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('country', 2)->default('PH')->after('postal_code');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('publication_status')->default('published')->after('moderation_status');
            $table->index(['publication_status', 'is_active', 'created_at'], 'products_public_listing_index');
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_id');
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_public_listing_index');
            $table->dropColumn('publication_status');
        });
        Schema::table('addresses', fn (Blueprint $table) => $table->dropColumn('country'));
    }
};
