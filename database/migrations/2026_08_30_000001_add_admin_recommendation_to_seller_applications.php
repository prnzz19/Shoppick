<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->string('admin_recommendation')->nullable()->after('status');
            $table->text('admin_review_notes')->nullable()->after('admin_recommendation');
            $table->foreignId('admin_reviewed_by')->nullable()->after('admin_review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_reviewed_at')->nullable()->after('admin_reviewed_by');
            $table->index(['status', 'admin_recommendation']);
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropIndex(['status', 'admin_recommendation']);
            $table->dropConstrainedForeignId('admin_reviewed_by');
            $table->dropColumn(['admin_recommendation', 'admin_review_notes', 'admin_reviewed_at']);
        });
    }
};
