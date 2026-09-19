<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('type', ['percent','fixed','free_shipping'])->default('percent')->change();
            $table->string('application_scope')->default('entire_shop')->after('type');
            $table->timestamp('archived_at')->nullable()->after('ends_at');
        });
        Schema::create('product_voucher', function (Blueprint $table) {
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['voucher_id','product_id']);
        });
        Schema::table('voucher_usages', function (Blueprint $table) {
            $table->foreignId('seller_order_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->decimal('discount_amount',12,2)->default(0);
            $table->decimal('shipping_discount_amount',12,2)->default(0);
            $table->string('voucher_code')->nullable();
            $table->string('voucher_title')->nullable();
            $table->timestamp('used_at')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_discount',12,2)->default(0)->after('voucher_discount');
        });
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->decimal('voucher_discount',12,2)->default(0)->after('discount');
            $table->decimal('shipping_discount',12,2)->default(0)->after('voucher_discount');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', fn(Blueprint $table) => $table->dropColumn(['voucher_discount','shipping_discount']));
        Schema::table('orders', fn(Blueprint $table) => $table->dropColumn('shipping_discount'));
        Schema::table('voucher_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_order_id');
            $table->dropColumn(['discount_amount','shipping_discount_amount','voucher_code','voucher_title','used_at']);
        });
        Schema::dropIfExists('product_voucher');
        Schema::table('vouchers', fn(Blueprint $table) => $table->dropColumn(['application_scope','archived_at']));
    }
};
