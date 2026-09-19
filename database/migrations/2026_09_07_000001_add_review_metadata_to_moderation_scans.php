<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moderation_scans', function (Blueprint $table) {
            $table->string('review_type', 20)->nullable()->after('decision');
            $table->string('moderation_result', 50)->nullable()->after('review_type');
            $table->text('rejection_reason')->nullable()->after('review_notes');
        });
    }

    public function down(): void
    {
        Schema::table('moderation_scans', function (Blueprint $table) {
            $table->dropColumn(['review_type', 'moderation_result', 'rejection_reason']);
        });
    }
};
