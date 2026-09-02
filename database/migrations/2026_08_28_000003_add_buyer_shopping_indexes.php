<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->unique('user_id', 'carts_user_id_unique');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_lookup_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('name', 'products_name_index');
            $table->index('brand', 'products_brand_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_name_index');
            $table->dropIndex('products_brand_index');
        });
        Schema::table('cart_items', fn (Blueprint $table) => $table->dropIndex('cart_items_lookup_index'));
        Schema::table('carts', fn (Blueprint $table) => $table->dropUnique('carts_user_id_unique'));
    }
};
