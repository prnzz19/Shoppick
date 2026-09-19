<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderLicenseAudit extends Model
{
    protected $fillable = ['rider_profile_id', 'actor_id', 'event', 'status', 'reason', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function riderProfile() { return $this->belongsTo(RiderProfile::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
