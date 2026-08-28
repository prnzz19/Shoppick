<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Jobs\ModerateProductImage;

class ProductService
{
    /**
     * Create a product along with its images and variants.
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($this->extractProductData($data));

            $this->storeImages($product, $data['images'] ?? [], $data['primary_image'] ?? null);
            $this->storeVariants($product, $data['variants'] ?? []);

            return $product->load('images', 'variants');
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($this->extractProductData($data));

            if (! empty($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            }

            return $product->fresh(['images', 'variants']);
        });
    }

    public function storeImages(Product $product, array $images, $primaryImageKey = null): void
    {
        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $first = ! $hasPrimary;

        foreach ($images as $index => $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('products', 'public');
                $createdImage = $product->images()->create([
                    'path' => $path,
                    'is_primary' => $first,
                    'sort_order' => $product->images()->count(),
                ]);
                if ($product->store_id) {
                    $scan = $createdImage->moderationScans()->create([
                        'product_id' => $product->id,
                        'seller_id' => $product->store?->user_id,
                        'store_id' => $product->store_id,
                        'provider' => config('services.image_moderation.provider', 'local'),
                        'status' => 'pending_scan',
                    ]);
                    $product->update(['moderation_status' => 'pending_scan', 'is_active' => false]);
                    ModerateProductImage::dispatch($scan->id)->afterCommit();
                }
                $first = false;
            }
        }

        if ($primaryImageKey !== null) {
            $product->images()->update(['is_primary' => false]);
            $image = $product->images()->get()->get((int) $primaryImageKey);
            if ($image) {
                $image->update(['is_primary' => true]);
            }
        }
    }

    public function storeVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variant) {
            if (empty($variant['type']) || empty($variant['value'])) {
                continue;
            }
            $product->variants()->create([
                'type' => $variant['type'],
                'value' => $variant['value'],
                'sku' => $variant['sku'] ?? null,
                'price' => $variant['price'] !== '' ? $variant['price'] : null,
                'stock' => $variant['stock'] ?? 0,
            ]);
        }
    }

    public function syncVariants(Product $product, array $variants): void
    {
        $product->variants()->delete();
        $this->storeVariants($product, $variants);
    }

    public function deleteImage(Product $product, $imageId): void
    {
        $image = $product->images()->findOrFail($imageId);
        $image->delete();

        if (! $product->images()->where('is_primary', true)->exists()) {
            $product->images()->first()?->update(['is_primary' => true]);
        }
    }

    public function setPrimaryImage(Product $product, $imageId): void
    {
        $product->images()->where('id', $imageId)->firstOrFail();
        $product->images()->update(['is_primary' => false]);
        $product->images()->where('id', $imageId)->update(['is_primary' => true]);
    }

    protected function extractProductData(array $data): array
    {
        $fields = [
            'store_id', 'category_id', 'name', 'description', 'specifications', 'brand', 'sku',
            'price', 'original_price', 'discount', 'stock', 'low_stock_threshold',
            'is_featured', 'is_active',
        ];

        $product = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $product[$field] = $data[$field];
            }
        }

        if (empty($product['slug']) && ! empty($product['name'])) {
            $product['slug'] = $this->uniqueSlug($product['name']);
        }

        return $product;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
