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
        'sold_count', 'rating_avg', 'rating_count', 'is_featured', 'is_active', 'moderation_status', 'publication_status', 'suspension_reason',
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
    public function orderItems() { return $this->hasMany(OrderItem::class); }
    public function moderationScans() { return $this->hasMany(ModerationScan::class); }
    public function violations() { return $this->hasMany(Violation::class); }
    public function reports() { return $this->morphMany(Report::class, 'target'); }

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
        return $q->publiclyVisible();
    }

    public function scopePubliclyVisible($q)
    {
        return $q->where('is_active', true)
            ->where('publication_status', 'published')
            ->where('price', '>', 0)
            ->whereIn('moderation_status', ['clean', 'approved'])
            ->whereHas('store', fn ($store) => $store->marketplaceActive());
    }

    public function sellerStatus(): string
    {
        if ($this->trashed()) return 'Archived';
        if ($this->store?->status !== 'active') return 'Store Suspended';
        if ($this->publication_status === 'draft') return 'Draft';
        if (in_array($this->moderation_status, ['pending_scan', 'scanning', 'under_review', 'flagged'])) return 'Under Moderation';
        if ($this->moderation_status === 'rejected') return 'Rejected';
        if ($this->moderation_status === 'scan_failed') return 'Review Required';
        if (! $this->is_active) return 'Suspended';
        if ($this->stock < 1) return 'Out of Stock';
        return 'Active';
    }

    public function sellerVisibilityReason(): string
    {
        return match ($this->sellerStatus()) {
            'Archived' => 'Archived products are hidden from Buyers until restored and republished.',
            'Under Moderation' => 'Waiting for image review.',
            'Rejected' => $this->suspension_reason ?: 'This product was rejected during review.',
            'Review Required' => 'The image check could not finish. An Admin can review it.',
            'Suspended' => $this->suspension_reason ?: 'This product is currently suspended.',
            'Store Suspended' => 'Your store is currently suspended.',
            'Draft' => 'Publish this draft when it is ready.',
            'Out of Stock' => 'Restock this product to make it purchasable.',
            default => 'Visible to Buyers.',
        };
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

        if (!empty($filters['sort']) && $filters['sort'] !== 'relevance') {
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
