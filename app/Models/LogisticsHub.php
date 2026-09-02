<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LogisticsHub extends Model { protected $fillable=['code','name','address','capacity','status','notes']; public function shipments(){return $this->hasMany(Shipment::class,'current_hub_id');} public function riders(){return $this->hasMany(RiderProfile::class,'hub_id');} public function vehicles(){return $this->hasMany(Vehicle::class,'hub_id');} }
