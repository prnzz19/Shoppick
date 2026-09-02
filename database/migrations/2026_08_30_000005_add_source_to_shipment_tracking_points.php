<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('shipment_tracking_points',fn(Blueprint $t)=>$t->string('source',20)->default('device')->after('rider_id')->index());}public function down():void{Schema::table('shipment_tracking_points',fn(Blueprint $t)=>$t->dropColumn('source'));}};
