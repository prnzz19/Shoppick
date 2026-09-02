<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LogisticsInvoice extends Model { protected $fillable=['invoice_number','shipment_id','delivery_fee','adjustments','total','status','due_at','paid_at','notes']; protected $casts=['due_at'=>'datetime','paid_at'=>'datetime']; public function shipment(){return $this->belongsTo(Shipment::class);} public static function number():string{return 'LI-'.now()->format('ymdHis').'-'.strtoupper(substr(uniqid(),-3));} }
