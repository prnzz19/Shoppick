<?php
namespace App\Services;

use App\Models\{LogisticsInvoice,LogisticsProvider,SellerOrder,Shipment,User,Vehicle};
use App\Support\LogisticsProviderContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class ShipmentService
{
    public function createForSellerOrder(SellerOrder $sellerOrder, ?User $actor=null): Shipment
    {
        return DB::transaction(function() use($sellerOrder,$actor){
            $sellerOrder=SellerOrder::with(['order','store'])->whereKey($sellerOrder->id)->lockForUpdate()->firstOrFail();
            if($sellerOrder->status!=='ready_to_ship') throw ValidationException::withMessages(['status'=>'Shipment requires a ready-to-ship Seller Order.']);
            $provider=$this->defaultMarketplaceProvider();
            $shipment=Shipment::firstOrCreate(['seller_order_id'=>$sellerOrder->id],[
                'shipment_number'=>Shipment::number(),'parcel_code'=>'PCL-'.strtoupper(substr(uniqid(),-8)),'order_id'=>$sellerOrder->order_id,'store_id'=>$sellerOrder->store_id,
                'status'=>'ready_for_pickup','pickup_address'=>['address'=>$sellerOrder->store?->location],
                'delivery_address'=>$sellerOrder->order->shipping_address,'ready_at'=>now(),
                'logistics_provider_id'=>$provider->id,
            ]);
            if(!$shipment->logistics_provider_id)$shipment->update(['logistics_provider_id'=>$provider->id]);

            $shipment->events()->firstOrCreate(['status'=>'ready_for_pickup'],[
                'actor_id'=>$actor?->id,'note'=>'Seller Order is ready for Logistics pickup.',
            ]);
            $sellerOrder->histories()->firstOrCreate(['status'=>'ready_to_ship'],[
                'order_id'=>$sellerOrder->order_id,'changed_by'=>$actor?->id,
                'note'=>'Seller Order entered Logistics as ready for pickup.',
            ]);
            // Dispatch is idempotent: the shipment row lock and assignment type
            // prevent repeated Seller status hooks from creating duplicates.
            app(AutomaticRiderAssignmentService::class)->assignPickup($shipment, $provider->id);
            $this->notifyLogistics($shipment,$sellerOrder,$shipment->logistics_provider_id);
            return $shipment;
        });
    }

    protected function defaultMarketplaceProvider(): LogisticsProvider
    {
        $id = app(SystemSettings::class)->get('logistics.default_marketplace_provider_id');
        $provider = $id ? LogisticsProvider::query()->whereKey((int) $id)->lockForUpdate()->first() : null;
        if (!$provider || !$provider->isOperational()) {
            Log::warning('Marketplace shipment blocked because the configured Logistics Provider is unavailable.');
            throw ValidationException::withMessages(['status' => 'LOGISTICS PROVIDER UNAVAILABLE']);
        }
        return $provider;
    }

    protected function notifyLogistics(Shipment $shipment,SellerOrder $sellerOrder,?int $providerId=null): void
    {
        if(!$providerId)return;
        $recipients=User::where('is_active',true)->whereHas('logisticsProviders',fn($q)=>$q->where('logistics_providers.id',$providerId)->where('logistics_provider_user.is_active',true))->get();
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
        abort_unless($actor->hasRole('logistics')&&$actor->is_active&&in_array($actor->registration_status,['approved',null],true)&&$actor->hasPermissionTo('assign_shipments'),403);
        $provider=app(LogisticsProviderContext::class)->requireFor($actor);
        abort_unless($shipment->logistics_provider_id===$provider->id,404);
        return DB::transaction(function()use($shipment,$actor,$rider,$vehicle,$reason){
            $shipment=Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $profile=$rider->riderProfile()->lockForUpdate()->first();
            if(!$rider->hasRole('rider')||!$rider->is_active||!$profile||!$profile->isAssignmentEligible())
                throw ValidationException::withMessages(['rider_id'=>'This Rider is not assignment-eligible. Check account, availability, and verified Driver license validity.']);
            $provider=app(LogisticsProviderContext::class)->requireFor($actor);
            if($profile->logistics_provider_id!==$provider->id)throw ValidationException::withMessages(['rider_id'=>'You can only assign a Rider owned by your Logistics Provider.']);
            $conflict=Shipment::forProvider($provider->id)->whereKeyNot($shipment->id)->where(fn($q)=>$q->where('pickup_rider_id',$rider->id)->orWhere('rider_id',$rider->id))->activeNonTerminal()->exists();
            if($conflict)throw ValidationException::withMessages(['rider_id'=>'This Rider already has an active assignment.']);
            if($vehicle){$vehicle=Vehicle::forProvider($provider->id)->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();if($vehicle->status!=='available')throw ValidationException::withMessages(['vehicle_id'=>'This Vehicle is not available.']);}
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
        $rider=['picked_up','in_transit','out_for_delivery','delivery_attempted','delivered','exception'];
        $logisticsActor = $actor->hasRole('logistics') && $actor->is_active
            && in_array($actor->registration_status, ['approved', null], true);
        abort_unless(($logisticsActor&&in_array($status,$logistics,true))||($actor->hasRole('rider')&&$shipment->rider_id===$actor->id&&in_array($status,$rider,true)),403);
        if($actor->hasRole('logistics')){
            $provider=app(LogisticsProviderContext::class)->requireFor($actor);
            abort_unless((int)$shipment->logistics_provider_id===(int)$provider->id,404);
        }
        return DB::transaction(function()use($shipment,$actor,$status,$note){
            $shipment=Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $allowed=['assigned'=>['pickup_scheduled','picked_up','exception'],'pickup_scheduled'=>['picked_up','exception'],'picked_up'=>['at_hub','in_transit','exception'],'at_hub'=>['hub_transfer','in_transit','exception'],'hub_transfer'=>['at_hub','in_transit','exception'],'in_transit'=>['out_for_delivery','exception'],'out_for_delivery'=>['delivered','delivery_attempted','exception'],'delivery_attempted'=>['out_for_delivery','exception'],'exception'=>['assigned','in_transit','out_for_delivery']];
            if(!in_array($status,$allowed[$shipment->status]??[],true))throw ValidationException::withMessages(['status'=>'That shipment transition is not allowed.']);
            if($status==='delivered'){
                app(CodCollectionService::class)->settleForDelivery($shipment);
                if(!$shipment->proofOfDelivery)throw ValidationException::withMessages(['pod'=>'Submit Proof of Delivery before completing this delivery.']);
            }
            $shipment->update(['status'=>$status,'picked_up_at'=>$status==='picked_up'?now():$shipment->picked_up_at,'delivered_at'=>$status==='delivered'?now():$shipment->delivered_at]);
            $shipment->events()->create(['actor_id'=>$actor->id,'status'=>$status,'note'=>$note]);
            $shipment->sellerOrder->histories()->create(['order_id'=>$shipment->order_id,'changed_by'=>$actor->id,'status'=>$status,'note'=>$note]);
            app(OrderProgressService::class)->syncShipment($shipment);
            if($status==='delivered'){$shipment->rider?->riderProfile?->update(['availability'=>'available']);$shipment->vehicle?->update(['status'=>'available']);LogisticsInvoice::firstOrCreate(['shipment_id'=>$shipment->id],['logistics_provider_id'=>$shipment->logistics_provider_id,'invoice_number'=>LogisticsInvoice::number(),'delivery_fee'=>$shipment->sellerOrder->shipping_fee,'total'=>$shipment->sellerOrder->shipping_fee,'status'=>'issued']);}
            $buyerStatus=match($status){'picked_up','in_transit'=>'shipped','out_for_delivery'=>'out_for_delivery','delivered'=>'delivered',default=>null};
            if($buyerStatus)app(BuyerOrderNotificationService::class)->send($shipment->order,$buyerStatus,$shipment->sellerOrder);
            return $shipment;
        });
    }
}
