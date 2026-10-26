<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class LogisticsSetting extends Model {
 use ScopesLogisticsProvider;
 protected $fillable=['logistics_provider_id','key','value','is_enabled','updated_by']; protected $casts=['value'=>'array','is_enabled'=>'boolean'];
 public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');} public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);}
 public static function valueFor(string $key,mixed $default=null):mixed {$setting=static::where('key',$key)->first();return $setting?data_get($setting->value,'value',$default):$default;}
}
