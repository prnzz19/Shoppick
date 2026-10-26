<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model { use ScopesLogisticsProvider; protected $fillable=['logistics_provider_id','code','plate_number','type','make','model','year','capacity_kg','hub_id','status','maintenance_due_at','notes']; protected $casts=['maintenance_due_at'=>'datetime','capacity_kg'=>'decimal:2']; public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');} public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);} public function hub(){return $this->belongsTo(LogisticsHub::class);} public function shipments(){return $this->hasMany(Shipment::class);} }
