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
        $recentApplications = \App\Models\SellerApplication::with('user')->latest()->take(5)->get();
        $stats = [
            'buyers' => \App\Models\User::whereHas('roles', fn($q)=>$q->where('slug','buyer'))->count(),
            'seller_applications' => \App\Models\SellerApplication::whereIn('status', \App\Models\SellerApplication::REVIEWABLE)->count(),
            'active_sellers' => \App\Models\User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','seller'))->whereHas('sellerProfile',fn($q)=>$q->where('status','approved'))->whereHas('store',fn($q)=>$q->where('status','active'))->count(),
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
            'recentApplications', 'stats', 'recentOrders', 'lowStock', 'statusCounts', 'salesByDay'
        ));
    }
}
