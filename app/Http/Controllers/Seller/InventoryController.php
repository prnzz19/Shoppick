<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable','string','max:200'], 'category' => ['nullable','integer'],
            'status' => ['nullable','in:in,low,out'], 'type' => ['nullable','in:simple,variants'],
            'sort' => ['nullable','in:name_asc,name_desc,stock_asc,stock_desc,updated'],
            'page' => ['nullable','integer','min:1'],
        ]);
        $filters['q'] = trim($filters['q'] ?? '');
        $filters['sort'] = $filters['sort'] ?? 'updated';
        $storeId = $request->user()->store->id;
        $base = Product::where('store_id', $storeId);
        $summary = (clone $base)->selectRaw('COUNT(*) as total, COALESCE(SUM(stock),0) as units, COALESCE(SUM(CASE WHEN stock > 0 AND stock <= low_stock_threshold THEN 1 ELSE 0 END),0) as low, COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END),0) as `out`')->first();
        $summary->with_variants = (clone $base)->whereHas('variants')->count();
        $categories = Category::whereHas('products', fn ($q) => $q->where('store_id',$storeId))->orderBy('name')->get(['id','name']);
        $query = (clone $base)->with(['primaryImage','category','variants']);
        if ($filters['q'] !== '') {
            $term = '%'.mb_strtolower($filters['q']).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?',[$term])->orWhereRaw('LOWER(sku) LIKE ?',[$term]));
        }
        if (!empty($filters['category'])) $query->where('category_id',$filters['category']);
        match ($filters['status'] ?? '') {
            'in' => $query->whereColumn('stock','>','low_stock_threshold')->where('stock','>',0),
            'low' => $query->where('stock','>',0)->whereColumn('stock','<=','low_stock_threshold'),
            'out' => $query->where('stock','<=',0),
            default => null,
        };
        match ($filters['type'] ?? '') {
            'simple' => $query->whereDoesntHave('variants'),
            'variants' => $query->whereHas('variants'),
            default => null,
        };
        [$column,$direction] = match ($filters['sort']) {
            'name_asc' => ['name','asc'], 'name_desc' => ['name','desc'],
            'stock_asc' => ['stock','asc'], 'stock_desc' => ['stock','desc'],
            default => ['updated_at','desc'],
        };
        $products = $query->orderBy($column,$direction)->orderBy('id')->paginate(10)->withPath(route('seller.inventory.index',[],false))->withQueryString();
        if ($products->currentPage() > $products->lastPage()) {
            $redirect = ['q' => $filters['q'] !== '' ? $filters['q'] : null,
                'category' => $filters['category'] ?? null,
                'status' => $filters['status'] ?? null,
                'type' => $filters['type'] ?? null,
                'sort' => $filters['sort'] ?? 'updated',
                'page' => $products->lastPage(),];
            $redirect = array_filter($redirect, fn ($value) => $value !== null && $value !== '');
            return new \Illuminate\Http\RedirectResponse(route('seller.inventory.index',$redirect,false));
        }
        return view('seller.inventory.index',compact('products','categories','summary','filters'));
    }

    public function update(Request $request, Product $product)
    {
        abort_unless($product->store_id === $request->user()->store->id,403);
        $rules = ['required','integer','min:0','max:2147483647'];
        $data = $request->validate(['stock'=>$rules,'low_stock_threshold'=>$rules,'variants'=>['nullable','array'],'variants.*'=>$rules],[
            'stock.integer'=>'Stock must be a whole number.', 'stock.min'=>'Stock cannot be negative.',
            'low_stock_threshold.integer'=>'The alert threshold must be a whole number.',
            'low_stock_threshold.min'=>'Low-stock threshold must be 0 or greater.',
            'variants.*.integer'=>'Variant stock must be a whole number.', 'variants.*.min'=>'Variant stock cannot be negative.',
        ]);
        DB::transaction(function () use ($product,$data,$request) {
            $current = Product::where('store_id',$request->user()->store->id)->lockForUpdate()->findOrFail($product->id);
            $variants = $current->variants()->lockForUpdate()->get()->keyBy('id');
            foreach ($data['variants'] ?? [] as $id => $stock) {
                if (!$variants->has($id)) throw ValidationException::withMessages(['variants'=>'A selected variant does not belong to this product.']);
            }
            // Parent stock is an independent limit in InventoryService, not a variant sum.
            $current->update(['stock'=>$data['stock'],'low_stock_threshold'=>$data['low_stock_threshold']]);
            foreach ($data['variants'] ?? [] as $id => $stock) $variants->get($id)->update(['stock'=>$stock]);
        });
        if ($request->expectsJson()) return response()->json(['message'=>'Stock updated successfully.']);
        return back()->with('success','Stock updated successfully.');
    }
}
