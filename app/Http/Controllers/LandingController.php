<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;

class LandingController extends Controller
{
    public function __invoke()
    {
        $picks = Product::active()
            ->inStock()
            ->with(['images', 'store', 'category'])
            ->latest()
            ->limit(6)
            ->get();

        // Keep the two landing collections distinct when the marketplace has
        // enough published products, while still rendering gracefully with a
        // small catalogue.
        $popularPicks = Product::active()
            ->inStock()
            ->whereNotIn('id', $picks->modelKeys())
            ->with(['images', 'store', 'category'])
            ->orderByDesc('sold_count')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $categories = Category::active()
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($query) => $query->active()->inStock()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get();

        // One eager-loaded image-bearing product gives category cards a real
        // storefront visual without adding a homepage-specific image table.
        $categoryProducts = Product::active()
            ->inStock()
            ->whereIn('category_id', $categories->modelKeys())
            ->with('images')
            ->latest()
            ->get()
            ->groupBy('category_id');

        $categories->each(function (Category $category) use ($categoryProducts): void {
            $category->setRelation('landingProduct', $categoryProducts->get($category->id)?->first());
        });

        $featuredCategories = $categories
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values();

        $collectionCategories = Category::active()
            ->whereRaw('LOWER(name) <> ?', ['audio'])
            ->whereHas('products', fn ($query) => $query->active()->inStock())
            ->withCount(['products' => fn ($query) => $query->active()->inStock()])
            ->orderByDesc('products_count')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get();

        $collectionProducts = Product::active()
            ->inStock()
            ->whereIn('category_id', $collectionCategories->modelKeys())
            ->with('images')
            ->latest()
            ->get()
            ->groupBy('category_id');

        $collectionCategories->each(function (Category $category) use ($collectionProducts): void {
            $category->setRelation('landingProduct', $collectionProducts->get($category->id)?->first());
        });

        return view('landing', [
            'picks' => $picks,
            'popularPicks' => $popularPicks,
            'shops' => Store::marketplaceActive()->whereHas('products', fn ($query) => $query->active())->withCount(['products' => fn ($query) => $query->active()])->orderByDesc('products_count')->limit(3)->get(),
            'categories' => $categories,
            'featuredCategories' => $featuredCategories,
            'collectionCategories' => $collectionCategories,
        ]);
    }
}
