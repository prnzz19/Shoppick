<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('logistics_hubs', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('name'); $t->text('address');
            $t->unsignedInteger('capacity')->nullable(); $t->string('status')->default('active'); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('rider_profiles', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $t->string('account_status')->default('active'); $t->string('availability')->default('available');
            $t->decimal('rating',3,2)->default(0); $t->text('notes')->nullable(); $t->timestamps();
            $t->index(['account_status','availability']);
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id(); $t->string('code')->unique(); $t->string('plate_number')->nullable()->unique();
            $t->string('type'); $t->string('make')->nullable(); $t->string('model')->nullable(); $t->unsignedSmallInteger('year')->nullable();
            $t->decimal('capacity_kg',10,2)->nullable(); $t->foreignId('hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $t->string('status')->default('available'); $t->timestamp('maintenance_due_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
            $t->index(['status','hub_id']);
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id(); $t->string('shipment_number')->unique(); $t->foreignId('seller_order_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete(); $t->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('current_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $t->string('status')->default('ready_for_pickup'); $t->string('priority')->default('normal');
            $t->json('pickup_address')->nullable(); $t->json('delivery_address')->nullable();
            $t->timestamp('ready_at')->nullable(); $t->timestamp('assigned_at')->nullable(); $t->timestamp('picked_up_at')->nullable();
            $t->timestamp('estimated_delivery_at')->nullable(); $t->timestamp('delivered_at')->nullable(); $t->text('internal_notes')->nullable(); $t->timestamps();
            $t->index(['status','ready_at']); $t->index(['rider_id','status']);
        });
        Schema::create('shipment_assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('shipment_id')->constrained()->cascadeOnDelete(); $t->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('assigned_at'); $t->timestamp('ended_at')->nullable(); $t->text('reason')->nullable(); $t->timestamps();
            $t->index(['shipment_id','ended_at']);
        });
        Schema::create('shipment_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('shipment_id')->constrained()->cascadeOnDelete(); $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status'); $t->string('location')->nullable(); $t->text('note')->nullable(); $t->json('metadata')->nullable(); $t->timestamps();
            $t->index(['shipment_id','created_at']);
        });
        Schema::create('hub_movements', function (Blueprint $t) {
            $t->id(); $t->foreignId('shipment_id')->constrained()->cascadeOnDelete(); $t->foreignId('from_hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $t->foreignId('to_hub_id')->constrained('logistics_hubs')->cascadeOnDelete(); $t->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $t->string('type')->default('inbound'); $t->text('note')->nullable(); $t->timestamp('moved_at'); $t->timestamps();
        });
        Schema::create('proof_of_deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete(); $t->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $t->string('recipient_name'); $t->string('photo_path')->nullable(); $t->text('notes')->nullable(); $t->string('status')->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->text('review_notes')->nullable();
            $t->timestamp('submitted_at'); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
        Schema::create('logistics_invoices', function (Blueprint $t) {
            $t->id(); $t->string('invoice_number')->unique(); $t->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('delivery_fee',12,2)->default(0); $t->decimal('adjustments',12,2)->default(0); $t->decimal('total',12,2)->default(0);
            $t->string('status')->default('draft'); $t->timestamp('due_at')->nullable(); $t->timestamp('paid_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('logistics_settings', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->json('value')->nullable(); $t->boolean('is_enabled')->default(true);
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('logistics_insights', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->string('severity')->default('info'); $t->string('title'); $t->text('explanation');
            $t->string('status')->default('generated'); $t->string('target_type')->nullable(); $t->unsignedBigInteger('target_id')->nullable();
            $t->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('acted_at')->nullable(); $t->timestamps();
            $t->index(['status','severity']);
        });

        // Existing ready-to-ship Seller Orders enter Logistics exactly once.
        DB::table('seller_orders')->where('status','ready_to_ship')->orderBy('id')->each(function($sellerOrder){
            $order=DB::table('orders')->where('id',$sellerOrder->order_id)->first();
            $store=DB::table('stores')->where('id',$sellerOrder->store_id)->first();
            DB::table('shipments')->insertOrIgnore([
                'shipment_number'=>'SH-LEG-'.str_pad((string)$sellerOrder->id,6,'0',STR_PAD_LEFT),
                'seller_order_id'=>$sellerOrder->id,'order_id'=>$sellerOrder->order_id,'store_id'=>$sellerOrder->store_id,
                'status'=>'ready_for_pickup','pickup_address'=>json_encode(['address'=>$store?->location]),
                'delivery_address'=>$order?->shipping_address,'ready_at'=>$sellerOrder->updated_at,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $shipmentId=DB::table('shipments')->where('seller_order_id',$sellerOrder->id)->value('id');
            if($shipmentId&&!DB::table('shipment_events')->where('shipment_id',$shipmentId)->exists())DB::table('shipment_events')->insert([
                'shipment_id'=>$shipmentId,'status'=>'ready_for_pickup','note'=>'Existing ready-to-ship Seller Order entered Logistics.','created_at'=>now(),'updated_at'=>now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_insights'); Schema::dropIfExists('logistics_settings'); Schema::dropIfExists('logistics_invoices');
        Schema::dropIfExists('proof_of_deliveries'); Schema::dropIfExists('hub_movements'); Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_assignments'); Schema::dropIfExists('shipments'); Schema::dropIfExists('vehicles');
        Schema::dropIfExists('rider_profiles'); Schema::dropIfExists('logistics_hubs');
    }
};
