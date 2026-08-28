<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => ['Phones', 'Laptops', 'Audio', 'Accessories'],
            'Fashion' => ['Men', 'Women', 'Shoes', 'Bags'],
            'Beauty' => ['Skincare', 'Makeup', 'Fragrance'],
            'Home & Living' => ['Kitchen', 'Decor', 'Furniture'],
            'Food' => ['Snacks', 'Beverages', 'Groceries'],
            'Sports' => ['Fitness', 'Outdoor', 'Cycling'],
            'Accessories' => ['Jewelry', 'Watches', 'Sunglasses'],
            'Gaming' => ['Consoles', 'Peripherals', 'Games'],
            'School Supplies' => ['Office', 'Art', 'Notebooks'],
        ];

        foreach ($categories as $name => $subs) {
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => array_search($name, array_keys($categories)),
                ]
            );

            foreach ($subs as $index => $sub) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($sub), 'parent_id' => $parent->id],
                    ['name' => $sub, 'is_active' => true, 'sort_order' => $index]
                );
            }
        }
    }
}
