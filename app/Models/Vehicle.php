<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model { protected $fillable=['code','plate_number','type','make','model','year','capacity_kg','hub_id','status','maintenance_due_at','notes']; protected $casts=['maintenance_due_at'=>'datetime','capacity_kg'=>'decimal:2']; public function hub(){return $this->belongsTo(LogisticsHub::class);} public function shipments(){return $this->hasMany(Shipment::class);} }
