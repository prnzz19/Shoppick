<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class ReportAction extends Model { protected $fillable=['report_id','user_id','action','reason','metadata']; protected $casts=['metadata'=>'array']; public function user(){return $this->belongsTo(User::class);} }
