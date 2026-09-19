<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'created_by', 'source_type', 'code', 'title', 'description', 'type', 'application_scope', 'platform_scope', 'value', 'min_purchase',
        'max_discount', 'usage_limit', 'used_count', 'per_user_limit',
        'starts_at', 'ends_at', 'archived_at', 'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'archived_at' => 'datetime',
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
    ];

    public function usages()
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function store() { return $this->belongsTo(Store::class); }
    public function products() { return $this->belongsToMany(Product::class, 'product_voucher'); }

    public function isActive(): bool
    {
        if ($this->status !== 'active' || $this->archived_at) {
            return false;
        }
        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && now()->gt($this->ends_at)) {
            return false;
        }
        return true;
    }

    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    public function userUsageCount($userId): int
    {
        return $this->usages()->where('user_id', $userId)->count();
    }

    public function isValidFor($userId, $subtotal, $scope = 'all'): bool
    {
        if (! $this->isActive()) {
            return false;
        }
        if ($this->min_purchase && $subtotal < $this->min_purchase) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        if ($this->per_user_limit && $this->userUsageCount($userId) >= $this->per_user_limit) {
            return false;
        }
        return true;
    }

    public function computeDiscount($subtotal): float
    {
        if ($this->type === 'free_shipping') return 0.0;
        if ($this->type === 'percent') {
            $discount = $subtotal * ($this->value / 100);
            if ($this->max_discount && $discount > $this->max_discount) {
                return round($this->max_discount, 2);
            }
            return round($discount, 2);
        }

        return round(min($this->value, $subtotal), 2);
    }
}
