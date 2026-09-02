<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LogisticsSetting extends Model {
 protected $fillable=['key','value','is_enabled','updated_by']; protected $casts=['value'=>'array','is_enabled'=>'boolean'];
 public static function valueFor(string $key,mixed $default=null):mixed {$setting=static::where('key',$key)->first();return $setting?data_get($setting->value,'value',$default):$default;}
}
