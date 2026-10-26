<?php
namespace App\Models;
use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;
class LogisticsInvoice extends Model { use ScopesLogisticsProvider; protected $fillable=['logistics_provider_id','invoice_number','shipment_id','delivery_fee','adjustments','total','status','due_at','paid_at','notes']; protected $casts=['due_at'=>'datetime','paid_at'=>'datetime']; public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');} public function scopeForProvider($q,int $id){return $q->where('logistics_provider_id',$id);} public function shipment(){return $this->belongsTo(Shipment::class);} public static function number():string{return 'LI-'.now()->format('ymdHis').'-'.strtoupper(substr(uniqid(),-3));} }
