<?php

namespace App\Models;

use App\Traits\ScopesLogisticsProvider;
use Illuminate\Database\Eloquent\Model;

class HubTransfer extends Model
{
    use ScopesLogisticsProvider;

    public const STATUSES = ['pending', 'assigned', 'in_transit', 'arrived', 'completed', 'exception'];

    protected $fillable = [
        'logistics_provider_id', 'shipment_id', 'origin_hub_id', 'destination_hub_id',
        'transfer_reference', 'transfer_driver_id', 'vehicle_id', 'created_by',
        'status', 'exception_reason', 'dispatched_at', 'arrived_at', 'received_at',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function provider() { return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id'); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function originHub() { return $this->belongsTo(LogisticsHub::class, 'origin_hub_id'); }
    public function destinationHub() { return $this->belongsTo(LogisticsHub::class, 'destination_hub_id'); }
    public function driver() { return $this->belongsTo(User::class, 'transfer_driver_id'); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function scopeForProvider($query, int $providerId) { return $query->where('logistics_provider_id', $providerId); }
    public function tripItem() { return $this->hasOne(HubTransferTripItem::class, 'hub_transfer_id'); }
}
