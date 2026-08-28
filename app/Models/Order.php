<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'order_number', 'status', 'payment_method', 'payment_status',
        'subtotal', 'discount', 'shipping_fee', 'voucher_discount', 'total',
        'voucher_id', 'shipping_address', 'buyer_name', 'buyer_phone', 'note',
        'paid_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'packed', 'shipped',
        'delivered', 'completed', 'cancelled', 'refunded',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function sellerOrders() { return $this->hasMany(SellerOrder::class); }
    public function statusHistory() { return $this->hasMany(OrderStatusHistory::class); }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'SP' . now()->format('ymdHis') . strtoupper(substr(uniqid(), -4));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }

    public function timeline()
    {
        $steps = [
            'pending' => now()->setTimezone('UTC'),
        ];

        return collect(self::STATUSES);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }

    public function scopeByStatus($q, $status)
    {
        return $q->where('status', $status);
    }
}
