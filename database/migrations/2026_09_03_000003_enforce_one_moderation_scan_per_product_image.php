<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moderation_scans', function (Blueprint $table) {
            $table->unique('product_image_id', 'moderation_scans_product_image_unique');
        });
    }

    public function down(): void
    {
        Schema::table('moderation_scans', function (Blueprint $table) {
            $table->dropUnique('moderation_scans_product_image_unique');
        });
    }
};
