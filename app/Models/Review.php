<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'user_id', 'order_id', 'rating', 'comment', 'images', 'is_visible',
    ];

    protected $casts = ['images' => 'array', 'is_visible' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function reply() { return $this->hasOne(ReviewReply::class); }

    public function scopeVisible($q)
    {
        return $q->where('is_visible', true);
    }
}
