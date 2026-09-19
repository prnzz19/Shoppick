<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderMessage extends Model
{
    protected $fillable = ['rider_id', 'sender_id', 'message', 'read_at'];
    protected $casts = ['read_at'=>'datetime'];
    public function rider(){return $this->belongsTo(User::class,'rider_id');}
    public function sender(){return $this->belongsTo(User::class,'sender_id');}
}
