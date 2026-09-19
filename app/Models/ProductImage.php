<?php

namespace App\Models;

use App\Services\ProductImageModerationEnrollmentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (ProductImage $image) {
            app(ProductImageModerationEnrollmentService::class)->enroll($image);
        });
    }

    protected $fillable = ['product_id', 'path', 'is_primary', 'sort_order'];

    protected $casts = ['is_primary' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function moderationScans()
    {
        return $this->hasMany(ModerationScan::class);
    }

    public function getUrlAttribute()
    {
        return asset('storage/'.$this->path);
    }
}
