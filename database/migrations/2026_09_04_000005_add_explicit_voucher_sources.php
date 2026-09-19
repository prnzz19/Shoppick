<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up():void{
  Schema::table('vouchers',function(Blueprint $table){$table->string('source_type',20)->default('platform')->after('store_id')->index();$table->string('platform_scope',30)->default('all_shops')->after('application_scope');$table->foreignId('created_by')->nullable()->after('store_id')->constrained('users')->nullOnDelete();});
  DB::table('vouchers')->whereNotNull('store_id')->update(['source_type'=>'shop']);
  DB::table('vouchers')->whereNull('store_id')->update(['source_type'=>'platform','platform_scope'=>'all_shops']);
  Schema::table('voucher_usages',function(Blueprint $table){$table->string('source_type',20)->nullable();$table->string('funding_source',20)->nullable();});
  Schema::table('orders',function(Blueprint $table){$table->decimal('shop_discount',12,2)->default(0);$table->decimal('platform_discount',12,2)->default(0);$table->decimal('platform_shipping_discount',12,2)->default(0);});
  Schema::table('seller_orders',function(Blueprint $table){$table->decimal('platform_shipping_discount',12,2)->default(0);});
 }
 public function down():void{Schema::table('seller_orders',fn(Blueprint $table)=>$table->dropColumn('platform_shipping_discount'));Schema::table('orders',fn(Blueprint $table)=>$table->dropColumn(['shop_discount','platform_discount','platform_shipping_discount']));Schema::table('voucher_usages',fn(Blueprint $table)=>$table->dropColumn(['source_type','funding_source']));Schema::table('vouchers',function(Blueprint $table){$table->dropConstrainedForeignId('created_by');$table->dropIndex(['source_type']);$table->dropColumn(['source_type','platform_scope']);});}
};
