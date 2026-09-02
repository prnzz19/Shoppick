<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'seller_order_id', 'product_id', 'product_variant_id', 'product_name',
        'product_image', 'variant_label', 'sku', 'price', 'quantity', 'total',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerOrder() { return $this->belongsTo(SellerOrder::class); }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
