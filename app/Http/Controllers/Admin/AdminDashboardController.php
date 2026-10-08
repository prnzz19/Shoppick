<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\User;
use App\Services\AdminSalesAnalyticsService;
use App\Services\MarketplaceSalesReportService;
use App\Services\ShopSalesReportService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request, AdminSalesAnalyticsService $analytics)
    {
        $sales = $analytics->data($analytics->filters($request, '7days'), true);
        if ($request->expectsJson()) {
            return response()->json(['content_html' => view('admin.analytics.content', $sales)->render(), 'url' => route('admin.dashboard', $sales['filters']['query'])]);
        }
        $recentApplications = SellerApplication::with('user')->latest()->take(5)->get();
        $stats = [
            'buyers' => User::whereHas('roles', fn ($q) => $q->where('slug', 'buyer'))->count(),
            'seller_applications' => SellerApplication::whereIn('status', SellerApplication::REVIEWABLE)->count(),
            'active_sellers' => User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'seller'))->whereHas('sellerProfile', fn ($q) => $q->where('status', 'approved'))->whereHas('store', fn ($q) => $q->where('status', 'active'))->count(),
            'products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'categories' => Category::count(),
            'orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'revenue' => app(MarketplaceSalesReportService::class)->query(app(ShopSalesReportService::class)->filters(Request::create('/', 'GET', ['range' => 'all'])))->sum('sales'),
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

        return view('admin.dashboard', compact(
            'recentApplications', 'stats', 'recentOrders', 'lowStock', 'statusCounts'
        ) + ['sales' => $sales]);
    }
}
