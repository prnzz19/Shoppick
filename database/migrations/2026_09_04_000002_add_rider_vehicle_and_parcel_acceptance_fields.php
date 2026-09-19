<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('rider_profiles',function(Blueprint $t){$t->string('vehicle_type')->nullable();$t->string('plate_number')->nullable();$t->string('or_cr_path')->nullable();$t->string('driver_license_path')->nullable();});Schema::table('shipments',function(Blueprint $t){$t->timestamp('pickup_arrived_at')->nullable();$t->timestamp('delivery_accepted_at')->nullable();});}
 public function down():void {Schema::table('shipments',fn(Blueprint $t)=>$t->dropColumn(['pickup_arrived_at','delivery_accepted_at']));Schema::table('rider_profiles',fn(Blueprint $t)=>$t->dropColumn(['vehicle_type','plate_number','or_cr_path','driver_license_path']));}
};
