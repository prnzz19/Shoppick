<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderActivationToken extends Model
{
    protected $fillable = ['rider_profile_id', 'token_hash', 'expires_at'];
    protected $casts = ['expires_at' => 'datetime'];

    public function riderProfile() { return $this->belongsTo(RiderProfile::class); }
}
