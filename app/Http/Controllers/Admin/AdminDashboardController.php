<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'categories' => Category::count(),
            'orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'revenue' => Order::completed()->sum('total'),
        ];

        $recentOrders = Order::latest()->with('user')->take(6)->get();

        $lowStock = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->with('images')
            ->take(6)
            ->get();

        $statusCounts = [
            'pending' => Order::where('status', 'pending')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'completed' => Order::where('status', 'completed')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        $salesByDay = Order::completed()
            ->where('completed_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy(fn ($o) => $o->completed_at->format('Y-m-d'))
            ->map(fn ($g) => $g->sum('total'));

        return view('admin.dashboard', compact(
            'stats', 'recentOrders', 'lowStock', 'statusCounts', 'salesByDay'
        ));
    }
}
