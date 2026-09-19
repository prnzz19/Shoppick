<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DeliveryArea extends Model {protected $attributes=['is_active'=>true];protected $fillable=['code','name','province','municipality','barangays','is_active'];protected $casts=['barangays'=>'array','is_active'=>'boolean'];public function riders(){return $this->belongsToMany(RiderProfile::class,'delivery_area_rider');}public function shipments(){return $this->hasMany(Shipment::class);}}
