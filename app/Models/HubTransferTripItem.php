<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HubTransferTripItem extends Model
{
    protected $fillable = ['trip_id', 'hub_transfer_id', 'shipment_id', 'status', 'received_by', 'received_at', 'exception_reason'];
    protected $casts = ['received_at' => 'datetime'];
    public function trip() { return $this->belongsTo(HubTransferTrip::class, 'trip_id'); }
    public function transfer() { return $this->belongsTo(HubTransfer::class, 'hub_transfer_id'); }
    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
}
