<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id', 'category_id', 'name', 'slug', 'description', 'specifications', 'brand', 'sku',
        'price', 'original_price', 'discount', 'stock', 'low_stock_threshold',
        'sold_count', 'rating_avg', 'rating_count', 'is_featured', 'is_active',
    ];

    protected $casts = [
        'specifications' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'rating_avg' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function store() { return $this->belongsTo(Store::class); }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function salePrice()
    {
        if ($this->discount && $this->discount > 0) {
            return round($this->price * (1 - $this->discount / 100), 2);
        }
        return $this->price;
    }

    public function originalPrice()
    {
        if ($this->discount && $this->discount > 0) {
            return $this->price;
        }
        return $this->original_price ?: $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->discount && $this->discount > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    public function getMainImageAttribute()
    {
        $primary = $this->images->firstWhere('is_primary', true)
            ?? $this->images->first();
        return $primary ? $primary->path : null;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeInStock($q)
    {
        return $q->where('stock', '>', 0);
    }

    public function scopeFiltered($q, array $filters)
    {
        if (!empty($filters['category']) && !empty($filters['category_ids'])) {
            $q->whereIn('category_id', $filters['category_ids']);
        } elseif (!empty($filters['category'])) {
            $q->where('category_id', $filters['category']);
        }

        if (!empty($filters['brand'])) {
            $q->where('brand', $filters['brand']);
        }

        if (!empty($filters['min_price'])) {
            $q->where('price', '>=', $filters['min_price']);
        }
        if (!empty($filters['max_price'])) {
            $q->where('price', '<=', $filters['max_price']);
        }

        if (!empty($filters['rating'])) {
            $q->where('rating_avg', '>=', $filters['rating']);
        }

        if (!empty($filters['availability']) && $filters['availability'] === 'in_stock') {
            $q->where('stock', '>', 0);
        }

        if (!empty($filters['discount'])) {
            $q->where('discount', '>', 0);
        }

        if (isset($filters['sort'])) {
            switch ($filters['sort']) {
                case 'latest':
                    $q->latest();
                    break;
                case 'price_asc':
                    $q->orderBy('price');
                    break;
                case 'price_desc':
                    $q->orderBy('price', 'desc');
                    break;
                case 'popular':
                    $q->orderByDesc('sold_count');
                    break;
                case 'rating':
                    $q->orderByDesc('rating_avg');
                    break;
                default:
                    $q->recent();
            }
        }

        return $q;
    }

    public function scopeRecent($q)
    {
        return $q->orderByDesc('created_at');
    }
}
