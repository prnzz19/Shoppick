<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->foreignId('escalated_by')->nullable()->after('admin_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('escalation_reason')->nullable()->after('escalated_by');
            $table->timestamp('escalated_at')->nullable()->after('escalation_reason');
            $table->index(['status', 'escalated_at']);
        });

        // Convert only unmistakable normal approvals from the short-lived mandatory
        // two-stage workflow. Any application/shop with escalation history is skipped.
        DB::table('seller_applications as applications')
            ->join('stores', 'stores.user_id', '=', 'applications.user_id')
            ->where('applications.status', 'awaiting_final_review')
            ->where('applications.admin_recommendation', 'approved')
            ->whereNotNull('applications.admin_reviewed_by')
            ->whereNotNull('applications.admin_reviewed_at')
            ->select('applications.id', 'applications.user_id', 'applications.admin_reviewed_by',
                'applications.admin_reviewed_at', 'stores.id as store_id', 'stores.seller_profile_id')
            ->orderBy('applications.id')
            ->each(function ($record) {
                $wasEscalated = DB::table('admin_activity_logs')
                    ->where('target_type', 'App\\Models\\Store')
                    ->where('target_id', $record->store_id)
                    ->where('action', 'shop.escalated')
                    ->exists();

                if ($wasEscalated) return;

                DB::table('seller_applications')->where('id', $record->id)->update([
                    'status' => 'approved',
                    'reviewed_by' => $record->admin_reviewed_by,
                    'reviewed_at' => $record->admin_reviewed_at,
                    'updated_at' => now(),
                ]);
                DB::table('stores')->where('id', $record->store_id)->update([
                    'status' => 'active', 'status_reason' => null,
                    'status_changed_by' => $record->admin_reviewed_by, 'updated_at' => now(),
                ]);
                if ($record->seller_profile_id) {
                    DB::table('seller_profiles')->where('id', $record->seller_profile_id)->update([
                        'status' => 'approved', 'approved_at' => $record->admin_reviewed_at, 'updated_at' => now(),
                    ]);
                }
                $sellerRole = DB::table('roles')->where('slug', 'seller')->value('id');
                if ($sellerRole) DB::table('role_user')->insertOrIgnore([
                    'role_id' => $sellerRole, 'user_id' => $record->user_id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropIndex(['status', 'escalated_at']);
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn(['escalation_reason', 'escalated_at']);
        });
    }
};
