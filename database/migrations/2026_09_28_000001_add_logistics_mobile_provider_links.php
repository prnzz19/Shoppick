<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('logo')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        foreach (['shipments', 'rider_profiles'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['shipments', 'rider_profiles'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('logistics_provider_id'));
        }
        Schema::dropIfExists('logistics_providers');
    }
};
