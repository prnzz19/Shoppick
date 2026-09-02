<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class ShipmentTrackingPoint extends Model {protected $fillable=['shipment_id','rider_id','source','latitude','longitude','accuracy','heading','speed','recorded_at'];protected $casts=['latitude'=>'decimal:7','longitude'=>'decimal:7','accuracy'=>'decimal:2','heading'=>'decimal:2','speed'=>'decimal:2','recorded_at'=>'datetime'];public function shipment(){return $this->belongsTo(Shipment::class);}public function rider(){return $this->belongsTo(User::class,'rider_id');}}
