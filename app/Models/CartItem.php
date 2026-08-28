<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id', 'product_id', 'product_variant_id', 'quantity', 'selected',
    ];

    protected $casts = ['selected' => 'boolean'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unitPrice()
    {
        return $this->variant && $this->variant->price
            ? $this->variant->price
            : $this->product->salePrice();
    }

    public function lineTotal()
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function availableStock()
    {
        if ($this->variant) {
            return $this->variant->stock;
        }
        return $this->product->stock;
    }
}
