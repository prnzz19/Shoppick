<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class ReportEvidence extends Model { protected $table='report_evidence'; protected $fillable=['report_id','path']; public function report(){return $this->belongsTo(Report::class);} }
