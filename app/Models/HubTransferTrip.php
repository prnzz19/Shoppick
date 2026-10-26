<?php

namespace App\Models;

use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;

class HubTransferTrip extends Model
{
    use ScopesLogisticsProvider;

    public const STATUSES = ['pending', 'assigned', 'in_transit', 'arrived', 'completed', 'exception'];

    protected $fillable = [
        'logistics_provider_id', 'origin_hub_id', 'destination_hub_id', 'trip_reference',
        'transport_driver_id', 'vehicle_id', 'created_by', 'status', 'capacity_kg',
        'exception_reason', 'dispatched_at', 'arrived_at',
    ];

    protected $casts = [
        'capacity_kg' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
    ];

    public function provider() { return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id'); }
    public function originHub() { return $this->belongsTo(LogisticsHub::class, 'origin_hub_id'); }
    public function destinationHub() { return $this->belongsTo(LogisticsHub::class, 'destination_hub_id'); }
    public function driver() { return $this->belongsTo(User::class, 'transport_driver_id'); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(HubTransferTripItem::class, 'trip_id'); }
    public function scopeForProvider($query, int $providerId) { return $query->where('logistics_provider_id', $providerId); }
}
