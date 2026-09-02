<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShipmentAssignment extends Model { protected $fillable=['shipment_id','rider_id','vehicle_id','assigned_by','assigned_at','ended_at','reason']; protected $casts=['assigned_at'=>'datetime','ended_at'=>'datetime']; public function shipment(){return $this->belongsTo(Shipment::class);} public function rider(){return $this->belongsTo(User::class,'rider_id');} public function vehicle(){return $this->belongsTo(Vehicle::class);} }
