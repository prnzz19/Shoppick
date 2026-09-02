<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RiderProfile extends Model { protected $fillable=['user_id','hub_id','account_status','availability','rating','notes']; protected $casts=['rating'=>'decimal:2']; public function user(){return $this->belongsTo(User::class);} public function hub(){return $this->belongsTo(LogisticsHub::class);} }
