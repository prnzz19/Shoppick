<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;

class LandingController extends Controller
{
    public function __invoke()
    {
        return view('landing', [
            'picks' => Product::active()->inStock()->with(['images', 'store', 'category'])->latest()->limit(6)->get(),
            'shops' => Store::marketplaceActive()->whereHas('products', fn ($query) => $query->active())->withCount(['products' => fn ($query) => $query->active()])->orderByDesc('products_count')->limit(3)->get(),
            'categories' => Category::active()->whereNull('parent_id')->orderBy('sort_order')->limit(6)->get(),
        ]);
    }
}
