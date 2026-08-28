<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('variants');

        if ($request->filled('scope')) {
            $query->where(function ($q) {
                $q->whereColumn('stock', '<=', 'low_stock_threshold')
                    ->orWhere('stock', '<=', 5);
            });
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', "%{$request->input('q')}%");
        }

        $products = $query->orderBy('stock')->paginate(15)->withQueryString();

        return view('admin.inventory.index', compact('products'));
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
