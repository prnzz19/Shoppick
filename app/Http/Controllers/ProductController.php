<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()->with('images', 'category', 'store');

        $search = preg_replace('/\s+/u', ' ', trim((string) $request->input('q', '')));
        $request->merge(['q' => $search]);

        $filters = $request->only(['category', 'brand', 'min_price', 'max_price', 'rating', 'availability', 'discount', 'sort', 'q']);

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$search}%"));
            })->when(! $request->filled('sort') || $request->input('sort') === 'relevance', function ($q) use ($search, $like) {
                $q->orderByRaw(
                    'CASE WHEN LOWER(name) = LOWER(?) THEN 1 WHEN LOWER(name) LIKE LOWER(?) THEN 2 WHEN LOWER(name) LIKE LOWER(?) THEN 3 ELSE 4 END',
                    [$search, $search.'%', $like]
                )->orderByDesc('rating_avg');
            });
        }

        if ($request->filled('category')) {
            $cat = Category::find($request->input('category'));
            $ids = $cat ? array_merge([$cat->id], $cat->children()->pluck('id')->all()) : [];
            $filters['category_ids'] = $ids;
        }

        $query->filtered($filters);

        $products = $query->paginate(12)->withQueryString();

        $categories = Category::whereNull('parent_id')->active()->orderBy('sort_order')->orderBy('name')->with('children')->get();
        $brands = Product::active()->whereNotNull('brand')->distinct()->pluck('brand');

        return view('storefront.products.index', compact('products', 'categories', 'brands'));
    }

    public function show(Request $request, $slug)
    {
        $product = Product::where('slug', $slug)->active()->with(['images', 'variants', 'category', 'store'])
            ->withCount(['reviews'])
            ->firstOrFail();

        $product->load(['reviews' => fn ($q) => $q->visible()->latest()->with('user')]);

        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('images', 'store')
            ->take(8)
            ->get();

        if ($related->count() < 8) {
            $related = $related->concat(
                Product::active()->where('id', '!=', $product->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->with('images', 'store')->take(8 - $related->count())->get()
            );
        }

        $ratingCounts = [
            5 => $product->reviews->where('rating', 5)->count(),
            4 => $product->reviews->where('rating', 4)->count(),
            3 => $product->reviews->where('rating', 3)->count(),
            2 => $product->reviews->where('rating', 2)->count(),
            1 => $product->reviews->where('rating', 1)->count(),
        ];

        return view('storefront.products.show', compact('product', 'related', 'ratingCounts'));
    }

    public function autocomplete(Request $request)
    {
        $request->validate(['q' => ['required', 'string', 'max:100']]);
        $q = preg_replace('/\s+/u', ' ', trim((string) $request->input('q')));

        $products = Product::active()
            ->where('name', 'like', "%{$q}%")
            ->with('images')
            ->orderByRaw('CASE WHEN LOWER(name) = LOWER(?) THEN 1 WHEN LOWER(name) LIKE LOWER(?) THEN 2 ELSE 3 END', [$q, $q.'%'])
            ->take(6)
            ->get(['id', 'name', 'slug', 'price', 'discount']);

        $categories = Category::active()
            ->where('name', 'like', "%{$q}%")
            ->take(4)
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'products' => $products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'price' => (float) $p->salePrice(),
                'original_price' => (float) $p->originalPrice(),
                'image' => $p->getMainImageAttribute(),
                'type' => 'product',
            ]),
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'type' => 'category',
            ]),
        ]);
    }
}
