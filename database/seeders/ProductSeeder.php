<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    protected int $imageSeq = 1000;

    public function run(): void
    {
        $storeId = Store::where('slug', 'panda-picks')->value('id');
        $products = [
            // [name, category, brand, price, discount, stock, sold, featured, description]
            ['Sonic Wireless Earbuds Pro', 'Audio', 'Sonic', 1899, 25, 120, 3400, true, 'Immersive sound with active noise cancellation and 30-hour battery life.'],
            ['Aurora Smart Watch Series 5', 'Phones', 'Aurora', 3499, 15, 60, 2100, true, 'Track your health, calls and workouts with a stunning always-on display.'],
            ['PixelFox 4K Action Camera', 'Audio', 'PixelFox', 2599, 10, 45, 980, true, 'Capture every adventure in stunning 4K with built-in stabilization.'],
            ['CloudComfort Ergonomic Chair', 'Furniture', 'CloudComfort', 5499, 20, 25, 760, true, 'All-day comfort with adjustable lumbar support and breathable mesh.'],
            ['SparkLED Desk Lamp', 'Decor', 'Spark', 899, 30, 200, 5400, true, 'Dimmable LED desk lamp with USB charging and adjustable angles.'],
            ['EcoBrew Stainless Bottle 1L', 'Kitchen', 'EcoBrew', 649, 12, 150, 3100, true, 'Keeps drinks cold 24h or hot 12h. Leak-proof and easy to clean.'],
            ['TrailBlaze Running Shoes', 'Shoes', 'TrailBlaze', 2799, 18, 80, 1900, true, 'Featherlight running shoes with responsive cushioning.'],
            ['GlamGlow Skincare Set', 'Skincare', 'GlamGlow', 1299, 22, 90, 2800, true, 'Complete 4-step routine for radiant skin.'],
            ['NovaBook Laptop 14"', 'Laptops', 'NovaBook', 42999, 8, 15, 420, true, 'Powerful ultrabook with 16GB RAM and 512GB SSD.'],
            ['GamePad Ultra Wireless Controller', 'Peripherals', 'GamePad', 1899, 0, 60, 1500, false, 'Ergonomic wireless controller with customizable buttons.'],
            ['ZenHome Scent Diffuser', 'Decor', 'ZenHome', 1099, 15, 70, 2300, false, 'Ultrasonic aroma diffuser with 7 color night light.'],
            ['FitCore Yoga Mat', 'Fitness', 'FitCore', 799, 10, 110, 3600, false, 'Non-slip, eco-friendly yoga mat with carrying strap.'],
            ['SnackPack Mixed Nuts 1kg', 'Snacks', 'SnackPack', 549, 5, 200, 5200, false, 'A healthy blend of almonds, cashews and peanuts.'],
            ['VoltHub 65W GaN Charger', 'Accessories', 'VoltHub', 1299, 20, 95, 2700, false, 'Compact fast charger with 3 ports for all your devices.'],
            ['ColorPop 120 Crayon Set', 'Art', 'ColorPop', 449, 35, 300, 8100, false, 'Vibrant crayon set perfect for kids and artists.'],
        ];

        foreach ($products as $i => [$name, $categoryName, $brand, $price, $discount, $stock, $sold, $featured, $desc]) {
            $category = Category::where('name', $categoryName)->orWhereHas('parent', fn ($q) => $q->where('name', $categoryName))->first();

            if (! $category) {
                continue;
            }

            $originalPrice = $discount > 0 ? round($price / (1 - $discount / 100), 2) : $price;

            $product = Product::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'store_id' => $storeId,
                    'category_id' => $category->id,
                    'name' => $name,
                    'brand' => $brand,
                    'description' => $desc,
                    'price' => $price,
                    'original_price' => $originalPrice,
                    'discount' => $discount,
                    'stock' => $stock,
                    'low_stock_threshold' => 5,
                    'sold_count' => $sold,
                    'rating_avg' => rand(38, 50) / 10,
                    'rating_count' => rand(20, 900),
                    'is_featured' => $featured,
                    'is_active' => true,
                    'specifications' => [
                        'Brand' => $brand,
                        'Warranty' => '1 Year',
                        'Condition' => 'Brand New',
                    ],
                ]
            );

            if (! $product->store_id && $storeId) {
                $product->update(['store_id' => $storeId]);
            }

            $this->createImages($product, $brand);
            $this->createVariants($product);
        }
    }

    protected function createImages(Product $product, string $brand): void
    {
        $catalogPath = 'products/' . $product->slug . '-catalog.png';
        if (Storage::disk('public')->exists($catalogPath)) {
            $product->images()->update(['is_primary' => false]);
            $product->images()->updateOrCreate(['path' => $catalogPath], [
                'is_primary' => true,
                'sort_order' => 0,
            ]);
        }

        if ($product->images()->exists()) {
            return;
        }

        $colors = ['#14b8a6', '#f97316', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7'];

        for ($i = 0; $i < 3; $i++) {
            $color = $colors[$i % count($colors)];
            $path = $this->placeholderSvg('products', $product->slug . '-' . $i . '.svg', $brand, $color);
            $product->images()->create([
                'path' => $path,
                'is_primary' => $i === 0,
                'sort_order' => $i,
            ]);
        }
    }

    protected function createVariants(Product $product): void
    {
        if ($product->variants()->exists()) {
            return;
        }

        $colors = array_slice(['Red', 'Blue', 'Black', 'White', 'Green'], 0, rand(1, 3));

        foreach ($colors as $color) {
            $product->variants()->create([
                'type' => 'Color',
                'value' => $color,
                'sku' => strtoupper($product->slug) . '-' . strtoupper(substr($color, 0, 2)),
                'price' => $product->price,
                'stock' => max(0, $product->stock / count($colors)),
            ]);
        }
    }

    protected function placeholderSvg(string $dir, string $filename, string $label, string $color): string
    {
        $label = mb_substr($label, 0, 12);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">'
            . '<rect width="600" height="600" fill="' . $color . '"/>'
            . '<circle cx="300" cy="240" r="90" fill="rgba(255,255,255,0.25)"/>'
            . '<text x="300" y="460" font-family="Arial, sans-serif" font-size="46" fill="#ffffff" text-anchor="middle" font-weight="bold">' . htmlspecialchars($label) . '</text>'
            . '<text x="300" y="520" font-family="Arial, sans-serif" font-size="26" fill="rgba(255,255,255,0.85)" text-anchor="middle">SHOPPICK</text>'
            . '</svg>';

        Storage::disk('public')->put($dir . '/' . $filename, $svg);

        return $dir . '/' . $filename;
    }
}
