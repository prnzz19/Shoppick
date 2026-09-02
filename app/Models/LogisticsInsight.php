<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LogisticsInsight extends Model { protected $fillable=['type','severity','title','explanation','status','target_type','target_id','acted_by','acted_at']; protected $casts=['acted_at'=>'datetime']; }
