<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class LogisticsHub extends Model { use ScopesLogisticsProvider; protected $fillable=['logistics_provider_id','code','name','address','capacity','status','notes']; public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');} public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);} public function shipments(){return $this->hasMany(Shipment::class,'current_hub_id');} public function riders(){return $this->hasMany(RiderProfile::class,'hub_id');} public function vehicles(){return $this->hasMany(Vehicle::class,'hub_id');} }
