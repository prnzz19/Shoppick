<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_initial', 5)->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_initial');
            $table->string('sex', 20)->nullable()->after('last_name');
            $table->date('birthday')->nullable()->after('sex');
            $table->string('valid_id_path')->nullable()->after('avatar');
            $table->string('registration_type', 20)->nullable()->after('valid_id_path');
            $table->string('registration_status', 20)->default('approved')->after('registration_type')->index();
            $table->text('registration_review_notes')->nullable()->after('registration_status');
            $table->foreignId('registration_reviewed_by')->nullable()->after('registration_review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('registration_reviewed_at')->nullable()->after('registration_reviewed_by');
        });
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('store_name')->constrained()->nullOnDelete();
            $table->string('valid_id_path')->nullable()->after('business_information');
            $table->string('business_permit_path')->nullable()->after('valid_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['valid_id_path','business_permit_path']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_reviewed_by');
            $table->dropIndex(['registration_status']);
            $table->dropColumn(['first_name','middle_initial','last_name','sex','birthday','valid_id_path','registration_type','registration_status','registration_review_notes','registration_reviewed_at']);
        });
    }
};
