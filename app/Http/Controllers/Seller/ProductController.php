<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}
    public function index(Request $request) { $query=auth()->user()->store->products()->with(['category','images']); if($request->q)$query->where('name','like','%'.$request->q.'%'); if($request->category)$query->where('category_id',$request->category); if($request->status==='active')$query->where('is_active',true); if($request->status==='draft')$query->where('is_active',false); if($request->status==='low')$query->whereColumn('stock','<=','low_stock_threshold')->where('stock','>',0); if($request->status==='out')$query->where('stock',0); $products=$query->latest()->paginate(15)->withQueryString(); $categories=Category::active()->get(); return view('seller.products.index',compact('products','categories')); }
    public function create() { $categories = Category::where('is_active', true)->get(); return view('seller.products.form', compact('categories')); }
    public function store(Request $request) { abort_unless($request->user()->store->status==='active',403,'Your shop cannot publish products while restricted or suspended.'); $data = $this->validateProduct($request); $data['store_id'] = $request->user()->store->id; $this->products->create($data); return redirect()->route('seller.products.index')->with('success', 'Product created. Image check is in progress.'); }
    public function edit(Product $product) { $this->own($product); $categories = Category::where('is_active', true)->get(); return view('seller.products.form', compact('product', 'categories')); }
    public function update(Request $request, Product $product) { $this->own($product); abort_unless($request->user()->store->status==='active',403,'Your shop cannot publish products while restricted or suspended.'); $data = $this->validateProduct($request, $product); $data['store_id'] = $request->user()->store->id; $this->products->update($product, $data); return redirect()->route('seller.products.index')->with('success', 'Product updated.'); }
    public function destroy(Product $product) { $this->own($product); $product->delete(); return back()->with('success', 'Product archived.'); }
    private function own(Product $product): void { abort_unless($product->store_id === auth()->user()->store->id, 403); }
    private function validateProduct(Request $request, ?Product $product = null): array { return $request->validate([
        'name'=>['required','string','max:255'],'category_id'=>['required','exists:categories,id'],'description'=>['nullable','string'],
        'brand'=>['nullable','string','max:100'],'sku'=>['nullable','string','max:100','unique:products,sku,'.($product?->id ?? 'NULL')],
        'price'=>['required','numeric','min:0'],'discount'=>['nullable','numeric','between:0,100'],'stock'=>['required','integer','min:0'],
        'low_stock_threshold'=>['nullable','integer','min:0'],'is_active'=>['nullable','boolean'],'images'=>['nullable','array'],'images.*'=>['image','max:2048']]); }
}
