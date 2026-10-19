<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class DeliveryArea extends Model {use ScopesLogisticsProvider;protected $attributes=['is_active'=>true];protected $fillable=['logistics_provider_id','code','name','province','municipality','barangays','is_active'];protected $casts=['barangays'=>'array','is_active'=>'boolean'];public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');}public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);}public function riders(){return $this->belongsToMany(RiderProfile::class,'delivery_area_rider');}public function shipments(){return $this->hasMany(Shipment::class);}public function sortingCenters(){return $this->belongsToMany(LogisticsHub::class,'delivery_area_hub')->withTimestamps();}}
