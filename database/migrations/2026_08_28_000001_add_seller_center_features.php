<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('review_replies', function(Blueprint $table){ $table->id(); $table->foreignId('review_id')->unique()->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->text('reply'); $table->timestamps(); });
        Schema::create('store_settings', function(Blueprint $table){ $table->id(); $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete(); $table->decimal('shipping_fee',10,2)->default(50); $table->unsignedInteger('processing_days')->default(2); $table->boolean('cod_enabled')->default(true); $table->timestamps(); });
        Schema::table('vouchers', function(Blueprint $table){ $table->foreignId('store_id')->nullable()->after('id')->constrained()->cascadeOnDelete(); $table->index(['store_id','status']); });
    }
    public function down(): void { Schema::table('vouchers',fn(Blueprint $table)=>$table->dropConstrainedForeignId('store_id')); Schema::dropIfExists('store_settings'); Schema::dropIfExists('review_replies'); }
};
