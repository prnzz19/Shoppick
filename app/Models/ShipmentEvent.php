<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShipmentEvent extends Model { protected $fillable=['shipment_id','actor_id','status','location','note','metadata']; protected $casts=['metadata'=>'array']; public function shipment(){return $this->belongsTo(Shipment::class);} public function actor(){return $this->belongsTo(User::class,'actor_id');} }
