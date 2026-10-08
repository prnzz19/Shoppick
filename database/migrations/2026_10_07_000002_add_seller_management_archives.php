<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['stores', 'seller_profiles', 'seller_applications'] as $name) {
            if (! Schema::hasColumn($name, 'archived_at')) {
                Schema::table($name, fn (Blueprint $table) => $table->timestamp('archived_at')->nullable()->index());
            }
        }
    }

    public function down(): void
    {
        // Archive timestamps are retained to avoid destroying administrative history.
    }
};
