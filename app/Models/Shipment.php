<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    public const STATUSES=['ready_for_pickup','pickup_assigned','pickup_accepted','picked_up','at_sorting_center','sorted','assigned_to_rider','out_for_delivery','delivery_failed','returned','delivered','completed','assigned','pickup_scheduled','at_hub','hub_transfer','in_transit','delivery_attempted','exception'];
    protected $fillable=['shipment_number','parcel_code','seller_order_id','order_id','store_id','pickup_rider_id','rider_id','vehicle_id','current_hub_id','delivery_area_id','status','priority','pickup_address','delivery_address','ready_at','assigned_at','picked_up_at','estimated_delivery_at','delivered_at','pickup_assigned_by','received_by','sorted_by','delivery_assigned_by','pickup_accepted_at','pickup_arrived_at','received_at','parcel_scanned_at','sorted_at','delivery_assigned_at','delivery_accepted_at','returned_at','failure_reason','internal_notes'];
    protected $casts=['pickup_address'=>'array','delivery_address'=>'array','ready_at'=>'datetime','assigned_at'=>'datetime','picked_up_at'=>'datetime','estimated_delivery_at'=>'datetime','delivered_at'=>'datetime','pickup_accepted_at'=>'datetime','pickup_arrived_at'=>'datetime','received_at'=>'datetime','parcel_scanned_at'=>'datetime','sorted_at'=>'datetime','delivery_assigned_at'=>'datetime','delivery_accepted_at'=>'datetime','returned_at'=>'datetime'];
    public function order(){return $this->belongsTo(Order::class);} public function sellerOrder(){return $this->belongsTo(SellerOrder::class);}
    public function store(){return $this->belongsTo(Store::class);} public function pickupRider(){return $this->belongsTo(User::class,'pickup_rider_id');} public function rider(){return $this->belongsTo(User::class,'rider_id');}
    public function vehicle(){return $this->belongsTo(Vehicle::class);} public function currentHub(){return $this->belongsTo(LogisticsHub::class,'current_hub_id');} public function deliveryArea(){return $this->belongsTo(DeliveryArea::class);}
    public function events(){return $this->hasMany(ShipmentEvent::class);} public function assignments(){return $this->hasMany(ShipmentAssignment::class);}
    public function proofOfDelivery(){return $this->hasOne(ProofOfDelivery::class);} public function invoice(){return $this->hasOne(LogisticsInvoice::class);}
    public static function number(): string { do{$n='SH-'.now()->format('ymd').'-'.strtoupper(substr(uniqid(),-5));}while(static::where('shipment_number',$n)->exists());return $n; }
}
