<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProofOfDelivery extends Model { protected $table='proof_of_deliveries'; protected $fillable=['shipment_id','submitted_by','recipient_name','photo_path','notes','status','reviewed_by','review_notes','submitted_at','reviewed_at']; protected $casts=['submitted_at'=>'datetime','reviewed_at'=>'datetime']; public function shipment(){return $this->belongsTo(Shipment::class);} public function submitter(){return $this->belongsTo(User::class,'submitted_by');} }
