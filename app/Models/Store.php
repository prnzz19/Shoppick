<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'seller_profile_id', 'name', 'slug', 'description', 'logo', 'banner',
        'location', 'rating_avg', 'rating_count', 'status', 'administrative_notes', 'status_reason', 'status_changed_by'];
    protected $casts = ['rating_avg' => 'decimal:2', 'archived_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function sellerProfile() { return $this->belongsTo(SellerProfile::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function sellerOrders() { return $this->hasMany(SellerOrder::class); }
    public function settings() { return $this->hasOne(StoreSetting::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
    public function violations() { return $this->hasMany(Violation::class); }
    public function reports() { return $this->morphMany(Report::class, 'target'); }
    public function statusChangedBy() { return $this->belongsTo(User::class, 'status_changed_by'); }
    public function scopeActive($query) { return $query->marketplaceActive(); }
    public function scopeMarketplaceActive($query)
    {
        return $query->whereNull('stores.archived_at')->where('status', 'active')
            ->whereHas('user', fn ($user) => $user->where('is_active', true))
            ->whereHas('sellerProfile', fn ($profile) => $profile->whereNull('archived_at')->where('status', 'approved'));
    }
    public function isMarketplaceActive(): bool
    {
        return ! $this->archived_at && ! $this->sellerProfile?->archived_at && $this->status === 'active'
            && (bool) $this->user?->is_active
            && $this->sellerProfile?->status === 'approved';
    }
}
