<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function __construct(protected ProductService $productService)
    {
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'images', 'variants']);

        if ($request->filled('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%")
                ->orWhere('sku', 'like', "%{$request->input('q')}%");
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $products = $query->latest()->paginate(12)->withQueryString();
        $categories = Category::with('children')->whereNull('parent_id')->get();

        return view('admin.products.index', compact('products', 'categories'));
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
