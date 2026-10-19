<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ShipmentEvent extends Model { protected $fillable=['shipment_id','actor_id','sorting_center_id','status','event_type','event_key','location','note','metadata']; protected $casts=['metadata'=>'array']; public function shipment(){return $this->belongsTo(Shipment::class);} public function actor(){return $this->belongsTo(User::class,'actor_id');} public function sortingCenter(){return $this->belongsTo(LogisticsHub::class,'sorting_center_id');} }
