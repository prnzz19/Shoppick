<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seller_profiles', fn (Blueprint $table) => $table->string('status')->default('pending')->change());
    }

    public function down(): void
    {
        Schema::table('seller_profiles', fn (Blueprint $table) => $table->enum('status',['approved','suspended'])->default('approved')->change());
    }
};
