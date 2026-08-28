<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StoreSetting extends Model { protected $fillable=['store_id','shipping_fee','processing_days','cod_enabled']; protected $casts=['cod_enabled'=>'boolean','shipping_fee'=>'decimal:2']; public function store(){return $this->belongsTo(Store::class);} }
