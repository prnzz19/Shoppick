<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    public const STATUSES=['ready_for_pickup','assigned','pickup_scheduled','picked_up','at_hub','hub_transfer','in_transit','out_for_delivery','delivery_attempted','delivered','exception'];
    protected $fillable=['shipment_number','seller_order_id','order_id','store_id','rider_id','vehicle_id','current_hub_id','status','priority','pickup_address','delivery_address','ready_at','assigned_at','picked_up_at','estimated_delivery_at','delivered_at','internal_notes'];
    protected $casts=['pickup_address'=>'array','delivery_address'=>'array','ready_at'=>'datetime','assigned_at'=>'datetime','picked_up_at'=>'datetime','estimated_delivery_at'=>'datetime','delivered_at'=>'datetime'];
    public function order(){return $this->belongsTo(Order::class);} public function sellerOrder(){return $this->belongsTo(SellerOrder::class);}
    public function store(){return $this->belongsTo(Store::class);} public function rider(){return $this->belongsTo(User::class,'rider_id');}
    public function vehicle(){return $this->belongsTo(Vehicle::class);} public function currentHub(){return $this->belongsTo(LogisticsHub::class,'current_hub_id');}
    public function events(){return $this->hasMany(ShipmentEvent::class);} public function assignments(){return $this->hasMany(ShipmentAssignment::class);}
    public function trackingPoints(){return $this->hasMany(ShipmentTrackingPoint::class);} public function latestTrackingPoint(){return $this->hasOne(ShipmentTrackingPoint::class)->latestOfMany('recorded_at');}
    public function proofOfDelivery(){return $this->hasOne(ProofOfDelivery::class);} public function invoice(){return $this->hasOne(LogisticsInvoice::class);}
    public static function number(): string { do{$n='SH-'.now()->format('ymd').'-'.strtoupper(substr(uniqid(),-5));}while(static::where('shipment_number',$n)->exists());return $n; }
}
