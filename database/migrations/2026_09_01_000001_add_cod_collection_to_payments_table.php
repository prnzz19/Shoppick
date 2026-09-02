<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments',function(Blueprint $table){
            $table->foreignId('collected_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable()->after('collected_by');
            $table->index(['status','collected_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments',function(Blueprint $table){
            $table->dropIndex(['status','collected_at']);
            $table->dropConstrainedForeignId('collected_by');
            $table->dropColumn('collected_at');
        });
    }
};
