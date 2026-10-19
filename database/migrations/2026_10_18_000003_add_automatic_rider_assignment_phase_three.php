<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('shipment_assignments')) {
            return;
        }

        Schema::table('shipment_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipment_assignments', 'assignment_type')) {
                $table->string('assignment_type', 20)->nullable()->after('rider_id');
            }
            if (! Schema::hasColumn('shipment_assignments', 'status')) {
                $table->string('status', 20)->default('assigned')->after('assigned_by');
            }
            if (! Schema::hasColumn('shipment_assignments', 'declined_at')) {
                $table->timestamp('declined_at')->nullable()->after('ended_at');
            }
            if (! Schema::hasColumn('shipment_assignments', 'decline_reason')) {
                $table->text('decline_reason')->nullable()->after('declined_at');
            }
            if (! Schema::hasColumn('shipment_assignments', 'assignment_cycle')) {
                $table->string('assignment_cycle', 80)->nullable()->after('decline_reason');
            }
        });

        Schema::table('shipment_assignments', function (Blueprint $table): void {
            $table->index(['shipment_id', 'assignment_type', 'status'], 'shipment_assignments_phase_three_lookup');
            $table->index(['rider_id', 'status'], 'shipment_assignments_rider_status_lookup');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipment_assignments')) {
            return;
        }

        Schema::table('shipment_assignments', function (Blueprint $table): void {
            if (Schema::hasIndex('shipment_assignments', 'shipment_assignments_phase_three_lookup')) {
                $table->dropIndex('shipment_assignments_phase_three_lookup');
            }
            if (Schema::hasIndex('shipment_assignments', 'shipment_assignments_rider_status_lookup')) {
                $table->dropIndex('shipment_assignments_rider_status_lookup');
            }
            foreach (['assignment_type', 'status', 'declined_at', 'decline_reason', 'assignment_cycle'] as $column) {
                if (Schema::hasColumn('shipment_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
