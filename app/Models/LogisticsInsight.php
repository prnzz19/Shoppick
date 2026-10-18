<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class LogisticsInsight extends Model { use ScopesLogisticsProvider; protected $fillable=['logistics_provider_id','type','severity','title','explanation','status','target_type','target_id','acted_by','acted_at']; protected $casts=['acted_at'=>'datetime']; public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');} public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);} }
