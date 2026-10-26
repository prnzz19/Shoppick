<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('psgc_barangays') && ! Schema::hasColumn('psgc_barangays', 'source_version')) {
            Schema::table('psgc_barangays', function (Blueprint $table): void {
                $table->string('source_version', 40)->nullable();
                $table->boolean('is_active')->default(true);
                $table->index(['source_version', 'is_active']);
            });
        }

        Schema::create('psgc_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('correspondence_code', 20)->nullable();
            $table->string('level', 20);
            $table->string('name');
            $table->string('parent_code', 20)->nullable();
            $table->string('region_code', 20)->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('municipality_code', 20)->nullable();
            $table->string('source_version', 40);
            $table->boolean('is_urban')->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['level', 'parent_code']);
            $table->index(['level', 'region_code']);
            $table->index(['level', 'province_code']);
            $table->index(['level', 'municipality_code']);
            $table->index(['source_version', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psgc_locations');
        if (Schema::hasTable('psgc_barangays') && Schema::hasColumn('psgc_barangays', 'source_version')) {
            Schema::table('psgc_barangays', function (Blueprint $table): void {
                $table->dropIndex(['source_version', 'is_active']);
                $table->dropColumn(['source_version', 'is_active']);
            });
        }
    }
};
