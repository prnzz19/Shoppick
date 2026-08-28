<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class ReportNote extends Model { protected $fillable=['report_id','user_id','note','is_super_admin']; protected $casts=['is_super_admin'=>'boolean']; public function user(){return $this->belongsTo(User::class);} }
