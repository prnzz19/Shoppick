<?php
namespace App\Services;

use App\Models\{LogisticsInvoice,SellerOrder,Shipment,User,Vehicle};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function createForSellerOrder(SellerOrder $sellerOrder, ?User $actor=null): Shipment
    {
        return DB::transaction(function() use($sellerOrder,$actor){
            $sellerOrder=SellerOrder::with(['order','store'])->whereKey($sellerOrder->id)->lockForUpdate()->firstOrFail();
            if($sellerOrder->status!=='ready_to_ship') throw ValidationException::withMessages(['status'=>'Shipment requires a ready-to-ship Seller Order.']);
            $shipment=Shipment::firstOrCreate(['seller_order_id'=>$sellerOrder->id],[
                'shipment_number'=>Shipment::number(),'order_id'=>$sellerOrder->order_id,'store_id'=>$sellerOrder->store_id,
                'status'=>'ready_for_pickup','pickup_address'=>['address'=>$sellerOrder->store?->location],
                'delivery_address'=>$sellerOrder->order->shipping_address,'ready_at'=>now(),
            ]);

            $shipment->events()->firstOrCreate(['status'=>'ready_for_pickup'],[
                'actor_id'=>$actor?->id,'note'=>'Seller Order is ready for Logistics pickup.',
            ]);
            $sellerOrder->histories()->firstOrCreate(['status'=>'ready_to_ship'],[
                'order_id'=>$sellerOrder->order_id,'changed_by'=>$actor?->id,
                'note'=>'Seller Order entered Logistics as ready for pickup.',
            ]);
            $this->notifyLogistics($shipment,$sellerOrder);
            return $shipment;
        });
    }

    protected function notifyLogistics(Shipment $shipment,SellerOrder $sellerOrder): void
    {
        $recipients=User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','logistics'))->get();
        foreach($recipients as $user){
            if(!$user->hasPermissionTo('view_shipments')) continue;
            $exists=$user->notificationsData()->where('type','logistics_ready_for_pickup')
                ->where('data->shipment_id',$shipment->id)->exists();
            if(!$exists) NotificationService::send(
                $user->id,'New Order / Load Ready for Pickup',
                "Order {$sellerOrder->order->order_number} from {$sellerOrder->store?->name} is ready for Logistics pickup.",
                'logistics_ready_for_pickup',route('logistics.shipments.show',$shipment),
                ['shipment_id'=>$shipment->id,'seller_order_id'=>$sellerOrder->id,'order_number'=>$sellerOrder->order->order_number],'package'
            );
        }
    }

    public function assign(Shipment $shipment,User $actor,User $rider,?Vehicle $vehicle=null,?string $reason=null): Shipment
    {
        abort_unless($actor->hasRole('logistics')&&$actor->hasPermissionTo('assign_shipments'),403);
        return DB::transaction(function()use($shipment,$actor,$rider,$vehicle,$reason){
            $shipment=Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $profile=$rider->riderProfile()->lockForUpdate()->first();
            if(!$rider->hasRole('rider')||!$rider->is_active||!$profile||$profile->account_status!=='active'||$profile->availability!=='available')
                throw ValidationException::withMessages(['rider_id'=>'This Rider is not available for assignment.']);
            if($vehicle){$vehicle=Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();if($vehicle->status!=='available')throw ValidationException::withMessages(['vehicle_id'=>'This Vehicle is not available.']);}
            if($shipment->rider_id&&$shipment->rider_id!==$rider->id)$shipment->rider?->riderProfile?->update(['availability'=>'available']);
            if($shipment->vehicle_id&&$shipment->vehicle_id!==$vehicle?->id)$shipment->vehicle?->update(['status'=>'available']);
            $shipment->assignments()->whereNull('ended_at')->update(['ended_at'=>now()]);
            $shipment->assignments()->create(['rider_id'=>$rider->id,'vehicle_id'=>$vehicle?->id,'assigned_by'=>$actor->id,'assigned_at'=>now(),'reason'=>$reason]);
            $shipment->update(['rider_id'=>$rider->id,'vehicle_id'=>$vehicle?->id,'status'=>'assigned','assigned_at'=>now()]);
            $profile->update(['availability'=>'assigned']);if($vehicle)$vehicle->update(['status'=>'in_use']);
            $shipment->events()->create(['actor_id'=>$actor->id,'status'=>'assigned','note'=>'Rider and Vehicle assignment recorded.']);
            NotificationService::send($rider->id,'New delivery assignment.',"You were assigned {$shipment->shipment_number}.",'logistics',route('rider.shipments.show',$shipment),null,'package');
            return $shipment;
        });
    }

    public function transition(Shipment $shipment,User $actor,string $status,?string $note=null): Shipment
    {
        $logistics=['pickup_scheduled','picked_up','at_hub','hub_transfer','in_transit','exception'];
        $rider=['out_for_delivery','delivery_attempted','delivered','exception'];
        abort_unless(($actor->hasRole('logistics')&&in_array($status,$logistics,true))||($actor->hasRole('rider')&&$shipment->rider_id===$actor->id&&in_array($status,$rider,true)),403);
        return DB::transaction(function()use($shipment,$actor,$status,$note){
            $shipment=Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $allowed=['assigned'=>['pickup_scheduled','picked_up','exception'],'pickup_scheduled'=>['picked_up','exception'],'picked_up'=>['at_hub','in_transit','exception'],'at_hub'=>['hub_transfer','in_transit','exception'],'hub_transfer'=>['at_hub','in_transit','exception'],'in_transit'=>['out_for_delivery','exception'],'out_for_delivery'=>['delivered','delivery_attempted','exception'],'delivery_attempted'=>['out_for_delivery','exception'],'exception'=>['assigned','in_transit','out_for_delivery']];
            if(!in_array($status,$allowed[$shipment->status]??[],true))throw ValidationException::withMessages(['status'=>'That shipment transition is not allowed.']);
            if($status==='delivered')app(CodCollectionService::class)->settleForDelivery($shipment);
            $shipment->update(['status'=>$status,'picked_up_at'=>$status==='picked_up'?now():$shipment->picked_up_at,'delivered_at'=>$status==='delivered'?now():$shipment->delivered_at]);
            $shipment->events()->create(['actor_id'=>$actor->id,'status'=>$status,'note'=>$note]);
            $shipment->sellerOrder->histories()->create(['order_id'=>$shipment->order_id,'changed_by'=>$actor->id,'status'=>$status,'note'=>$note]);
            app(OrderProgressService::class)->syncShipment($shipment);
            if($status==='delivered'){$shipment->rider?->riderProfile?->update(['availability'=>'available']);$shipment->vehicle?->update(['status'=>'available']);LogisticsInvoice::firstOrCreate(['shipment_id'=>$shipment->id],['invoice_number'=>LogisticsInvoice::number(),'delivery_fee'=>$shipment->sellerOrder->shipping_fee,'total'=>$shipment->sellerOrder->shipping_fee,'status'=>'issued']);}
            $buyerStatus=match($status){'picked_up','in_transit'=>'shipped','out_for_delivery'=>'out_for_delivery','delivered'=>'delivered',default=>null};
            if($buyerStatus)app(BuyerOrderNotificationService::class)->send($shipment->order,$buyerStatus,$shipment->sellerOrder);
            return $shipment;
        });
    }
}
