<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'type', 'value', 'sku', 'price', 'stock', 'image'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getLabelAttribute()
    {
        return ucfirst($this->type) . ': ' . $this->value;
    }
}
