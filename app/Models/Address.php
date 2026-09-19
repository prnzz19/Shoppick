<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'full_name', 'phone', 'region', 'region_code', 'province', 'province_code', 'city', 'city_code',
        'barangay', 'barangay_code', 'postal_code', 'country', 'address_line', 'label', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute()
    {
        return implode(', ', array_filter([
            $this->address_line,
            $this->barangay,
            $this->city,
            $this->province,
            trim((string) $this->postal_code),
        ]));
    }
}
