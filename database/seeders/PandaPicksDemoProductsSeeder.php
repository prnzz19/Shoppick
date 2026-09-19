<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PandaPicksDemoProductsSeeder extends Seeder
{
    public const MARKER = 'ITEL304-CHECKPOINT1';

    public function run(): void
    {
        $store = Store::where('slug', 'panda-picks')->where('name', 'Panda Picks')->sole();
        if (! $store->isMarketplaceActive()) {
            throw new RuntimeException('The original Panda Picks shop is not marketplace active. No changes made.');
        }
        $records = [
            ['Wireless Bluetooth Earbuds', 'Audio', 599, 25, 'sonic-wireless-earbuds-pro-catalog.png', 'Compact wireless earbuds with a charging case, touch controls, and convenient everyday use.'],
            ['Portable Mini Fan', 'Home & Living', 349, 30, 'itel304-portable-mini-fan.png', 'Rechargeable portable fan with compact design and multiple speed settings for daily use.'],
            ['Insulated Tumbler', 'Kitchen', 449, 20, 'ecobrew-stainless-bottle-1l-catalog.png', 'Reusable insulated tumbler designed to keep drinks hot or cold while on the go.'],
            ['USB Rechargeable Desk Lamp', 'Decor', 399, 18, 'sparkled-desk-lamp-catalog.png', 'Compact rechargeable desk lamp with adjustable brightness for study, work, or bedside use.'],
        ];
        // Preflight everything before inserting any records.
        foreach ($records as [$name, $category, $price, $stock, $image]) {
            Category::active()->where('name', $category)->sole();
            if (! Storage::disk('public')->exists('products/'.$image)) {
                throw new RuntimeException('Missing local demo image: '.$image);
            }
            $slug = 'itel304-demo-'.Str::slug($name);
            $existing = Product::withTrashed()->where('slug', $slug)->first();
            if ($existing && ($existing->store_id !== $store->id || ($existing->specifications['Demo batch'] ?? null) !== self::MARKER)) {
                throw new RuntimeException('Demo slug collision; existing record will not be overwritten: '.$slug);
            }
        }
        DB::transaction(function () use ($store, $records) {
            foreach ($records as [$name, $category, $price, $stock, $image, $description]) {
                $slug = 'itel304-demo-'.Str::slug($name);
                if (Product::withTrashed()->where('slug', $slug)->exists()) {
                    $this->command?->info('Skipped existing demo: '.$name);
                    continue; // Never reset inventory, restore archived products, or overwrite changes.
                }
                $product = $store->products()->create([
                    'category_id' => Category::active()->where('name', $category)->sole()->id,
                    'name' => $name, 'slug' => $slug,
                    'sku' => self::MARKER.'-'.Str::upper(Str::slug($name)),
                    'description' => $description.' Temporary demo product for ITEL 304 Checkpoint 1; image is illustrative.',
                    'specifications' => ['Demo batch' => self::MARKER, 'Purpose' => 'Temporary screenshot/test data'],
                    'price' => $price, 'stock' => $stock, 'discount' => 0,
                    'sold_count' => 0, 'rating_avg' => 0, 'rating_count' => 0,
                    'publication_status' => 'published', 'moderation_status' => 'pending_scan',
                    'is_active' => false, 'is_featured' => false,
                ]);
                // Keep the existing image-created event and moderation enrollment intact.
                $product->images()->create(['path' => 'products/'.$image, 'is_primary' => true, 'sort_order' => 0]);
                $this->command?->info('Created demo '.$product->id.': '.$name);
            }
        });
    }
}
