<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogisticsProvider extends Model
{
    protected $fillable = ['name', 'code', 'logo', 'contact_email', 'contact_phone', 'status'];

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function riders()
    {
        return $this->hasMany(RiderProfile::class);
    }
}
