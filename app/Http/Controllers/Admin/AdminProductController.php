<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\ProductService;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function __construct(protected ProductService $productService)
    {
    }

    public function index(Request $request)
    {
        $query = Product::withTrashed()->with(['category', 'images', 'variants', 'store.user']);

        if ($request->filled('q')) {
            $query->where(function ($product) use ($request) {
                $product->where('name', 'like', "%{$request->input('q')}%")
                    ->orWhere('sku', 'like', "%{$request->input('q')}%");
            });
        }

        if ($request->filled('shop_id')) {
            $request->input('shop_id') === 'unassigned'
                ? $query->whereNull('store_id')
                : $query->where('store_id', $request->integer('shop_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            match ($request->input('status')) {
                'archived' => $query->onlyTrashed(),
                'active' => $query->whereNull('deleted_at')->where('is_active', true),
                'inactive' => $query->whereNull('deleted_at')->where('is_active', false),
                'pending' => $query->whereNull('deleted_at')->whereIn('moderation_status', ['pending_scan', 'scanning', 'under_review', 'scan_failed']),
                'rejected' => $query->whereNull('deleted_at')->where('moderation_status', 'rejected'),
                'low_stock' => $query->whereNull('deleted_at')->where('stock', '>', 0)->whereColumn('stock', '<=', 'low_stock_threshold'),
                'out_of_stock' => $query->whereNull('deleted_at')->where('stock', '<=', 0),
                default => null,
            };
        }

        $products = $query->latest()->paginate(12)->withQueryString();
        $categories = Category::with('children')->whereNull('parent_id')->get();
        $shops = Store::with('user')->orderBy('name')->get();

        $storeIds = $products->getCollection()->pluck('store_id')->filter()->unique()->values();
        $stats = Product::withTrashed()
            ->where(function ($query) use ($storeIds, $products) {
                $query->whereIn('store_id', $storeIds);
                if ($products->getCollection()->contains(fn ($product) => $product->store_id === null)) {
                    $query->orWhereNull('store_id');
                }
            })
            ->selectRaw('store_id, COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND is_active = 1 THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND is_active = 0 THEN 1 ELSE 0 END) as inactive_count')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND stock > 0 AND stock <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count')
            ->selectRaw('SUM(CASE WHEN deleted_at IS NULL AND stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ->groupBy('store_id')
            ->get()
            ->keyBy(fn ($row) => $row->store_id === null ? 'unassigned' : (string) $row->store_id);

        $productGroups = $products->getCollection()
            ->groupBy(fn ($product) => $product->store_id === null ? 'unassigned' : (string) $product->store_id)
            ->map(function ($groupProducts, $key) use ($stats) {
                $store = $groupProducts->first()->store;
                return (object) [
                    'key' => $key,
                    'store' => $store,
                    'products' => $groupProducts->sortByDesc('created_at')->values(),
                    'stats' => $stats->get($key),
                ];
            })
            ->sortBy(fn ($group) => $group->store?->name ?? '~~~ Unassigned');

        return view('admin.products.index', compact('products', 'productGroups', 'categories', 'shops'));
    }

    public function create()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'variants' => ['nullable', 'array'],
        ]);

        $product = $this->productService->create($data);

        AdminActivityLog::record('product.created', 'product', $product->id, ['name' => $product->name]);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        $product->load(['images', 'variants', 'category']);
        $categories = Category::with('children')->whereNull('parent_id')->get();
        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'price' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
        ]);

        $this->productService->update($product, $data);

        AdminActivityLog::record('product.updated', 'product', $product->id, ['name' => $product->name]);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $product->delete();
        AdminActivityLog::record('product.deleted', 'product', $product->id, ['name' => $product->name]);

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);
        AdminActivityLog::record('product.status', 'product', $product->id, ['is_active' => $product->is_active]);

        return back()->with('success', 'Product status updated.');
    }

    public function storeImages(Request $request, Product $product)
    {
        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $this->productService->storeImages($product, $request->file('images'));

        return back()->with('success', 'Images uploaded.');
    }

    public function deleteImage(Product $product, $imageId)
    {
        $this->productService->deleteImage($product, $imageId);
        return back()->with('success', 'Image deleted.');
    }

    public function setPrimaryImage(Product $product, $imageId)
    {
        $this->productService->setPrimaryImage($product, $imageId);
        return back()->with('success', 'Primary image updated.');
    }
}
