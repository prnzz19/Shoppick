<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from') ? now()->parse($request->query('from')) : now()->subDays(30);
        $to = $request->query('to') ? now()->parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $reportType = $request->query('report', 'sales');

        $salesTotal = Order::completed()->whereBetween('completed_at', [$from, $to])->sum('total');
        $ordersCount = Order::whereBetween('created_at', [$from, $to])->count();
        $avgOrder = $ordersCount ? round($salesTotal / $ordersCount, 2) : 0;
        $newUsers = User::whereBetween('created_at', [$from, $to])->count();
        $productsSold = Order::completed()
            ->whereBetween('completed_at', [$from, $to])
            ->with('items')->get()
            ->sum(fn ($o) => $o->items->sum('quantity'));

        $topProducts = Product::select('id', 'name', 'price', 'sold_count', 'stock')
            ->with('images')
            ->orderBy('sold_count', 'desc')
            ->take(6)
            ->get();

        $topCategories = Category::withSum('products as stockCount', 'stock')
            ->withCount('products')
            ->whereHas('products')
            ->orderByDesc('products_count')
            ->take(6)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'products' => $c->products_count,
                'sold' => (int) $c->products()->sum('sold_count'),
                'stock' => (int) ($c->stockCount ?? 0),
            ]);

        $completedOrders = Order::completed()->whereBetween('completed_at', [$from, $to])->get();

        $salesByDay = $completedOrders->groupBy(fn ($o) => $o->completed_at->format('Y-m-d'))
            ->map(fn ($g) => round($g->sum('total'), 2))
            ->sortKeys();

        $ordersByDay = $completedOrders->groupBy(fn ($o) => $o->completed_at->format('Y-m-d'))
            ->map(fn ($g) => $g->count())
            ->sortKeys();

        // Inventory report
        $lowStockProducts = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereColumn('stock', '<=', 'low_stock_threshold')
                    ->orWhere('stock', '<=', 5);
            })
            ->orderBy('stock')
            ->take(10)
            ->get();

        // Users report
        $usersByRole = [];
        foreach (['super_admin', 'admin', 'buyer'] as $slug) {
            $role = \App\Models\Role::withCount('users')->where('slug', $slug)->first();
            $usersByRole[$slug] = $role ? $role->users_count : 0;
        }

        return view('admin.reports.index', compact(
            'from', 'to', 'reportType', 'salesTotal', 'ordersCount', 'avgOrder',
            'newUsers', 'productsSold', 'topProducts', 'topCategories',
            'salesByDay', 'ordersByDay', 'lowStockProducts', 'usersByRole'
        ));
    }
}
