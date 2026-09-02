<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerOrder extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'processing', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'completed', 'cancelled'];
    public const SELLER_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['packed', 'cancelled'],
        'packed' => ['ready_to_ship', 'cancelled'],
    ];
    public const TAB_GROUPS = [
        'to_ship' => ['pending', 'confirmed', 'processing', 'packed', 'ready_to_ship'],
        'to_receive' => ['shipped'],
        'completed' => ['delivered', 'completed'],
        'cancelled' => ['cancelled'],
    ];
    protected $fillable = ['order_id', 'store_id', 'seller_order_number', 'status', 'subtotal', 'shipping_fee',
        'discount', 'commission_rate', 'commission_amount', 'seller_total', 'completed_at', 'cancelled_at', 'cancellation_reason'];
    protected $casts = ['completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    public function order() { return $this->belongsTo(Order::class); }
    public function store() { return $this->belongsTo(Store::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function histories() { return $this->hasMany(OrderStatusHistory::class); }
    public function shipment() { return $this->hasOne(Shipment::class); }

    public function nextSellerStatus(): ?string
    {
        return match ($this->status) {
            'pending' => 'confirmed',
            'confirmed' => 'processing',
            'processing' => 'packed',
            'packed' => 'ready_to_ship',
            default => null,
        };
    }
}
