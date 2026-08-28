<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    protected $fillable = ['user_id', 'phone', 'address', 'business_information', 'status', 'approved_at'];
    protected $casts = ['approved_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function store() { return $this->hasOne(Store::class); }
}
