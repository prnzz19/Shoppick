<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q'));
        $scope = $request->input('scope');
        $inventoryFilter = function ($query) use ($scope, $search) {
            if ($scope === 'out') {
                $query->where('stock', '<=', 0);
            } elseif ($scope === 'low') {
                $query->where('stock', '>', 0)->where(fn ($stock) => $stock->whereColumn('stock', '<=', 'low_stock_threshold')->orWhere('stock', '<=', 5));
            }
            if ($search !== '') {
                $query->where(function ($match) use ($search) {
                    $match->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($seller) => $seller->where('name', 'like', "%{$search}%")));
                });
            }
        };

        $query = Store::with(['user', 'products' => fn ($products) => $inventoryFilter($products->with('variants')->orderBy('stock')->orderBy('name'))])
            ->withCount([
                'products',
                'products as low_stock_count' => fn ($products) => $products->where('stock', '>', 0)->where(fn ($stock) => $stock->whereColumn('stock', '<=', 'low_stock_threshold')->orWhere('stock', '<=', 5)),
                'products as out_of_stock_count' => fn ($products) => $products->where('stock', '<=', 0),
            ]);

        if ($request->filled('shop')) $query->whereKey($request->integer('shop'));
        if ($scope || $search !== '') $query->whereHas('products', $inventoryFilter);

        $shops = $query->orderByDesc('products_count')->orderBy('name')->paginate(10)->withQueryString();
        $shopOptions = Store::orderBy('name')->get(['id', 'name']);

        return view('admin.inventory.index', compact('shops', 'shopOptions'));
    }

    public function updateStock(Request $request, Product $product)
    {
        $request->validate([
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $data = ['stock' => $request->input('stock')];
        if ($request->has('low_stock_threshold')) {
            $data['low_stock_threshold'] = $request->input('low_stock_threshold');
        }

        $product->update($data);
        AdminActivityLog::record('inventory.updated', 'product', $product->id, ['stock' => $product->stock]);

        return back()->with('success', 'Stock updated.');
    }
}
