<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'seller_profile_id', 'name', 'slug', 'description', 'logo', 'banner',
        'location', 'rating_avg', 'rating_count', 'status'];
    protected $casts = ['rating_avg' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function sellerProfile() { return $this->belongsTo(SellerProfile::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function sellerOrders() { return $this->hasMany(SellerOrder::class); }
    public function settings() { return $this->hasOne(StoreSetting::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
    public function scopeActive($query) { return $query->where('status', 'active'); }
}
