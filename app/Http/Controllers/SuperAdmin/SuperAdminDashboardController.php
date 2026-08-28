<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Report;
use App\Models\ModerationScan;
use App\Models\Store;

class SuperAdminDashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $totalBuyers = Role::where('slug', 'buyer')->first()?->users()->count() ?? 0;
        $totalAdmins = (Role::where('slug', 'admin')->first()?->users()->count() ?? 0)
            + (Role::where('slug', 'super_admin')->first()?->users()->count() ?? 0);

        $stats = [
            'total_users' => $totalUsers,
            'total_buyers' => $totalBuyers,
            'total_admins' => $totalAdmins,
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'cancelled_orders' => Order::whereIn('status', ['cancelled', 'refunded'])->count(),
            'total_sales' => Order::completed()->sum('total'),
            'open_reports' => Report::whereIn('status',['open','under_review','escalated'])->count(),
            'awaiting_review' => ModerationScan::whereIn('status',['pending_scan','flagged','under_review'])->count(),
            'flagged_products' => Product::where('moderation_status','flagged')->count(),
            'suspended_shops' => Store::where('status','suspended')->count(),
            'high_priority_alerts' => Report::whereIn('priority',['high','critical'])->whereNotIn('status',['resolved','dismissed'])->count(),
        ];

        $recentOrders = Order::latest()->with('user')->take(6)->get();
        $recentUsers = User::latest()->take(6)->get();
        $recentModeration = ModerationScan::with(['product','reviewer'])->latest()->take(5)->get();

        $bestSelling = Product::orderByDesc('sold_count')->with('images')->take(5)->get();

        $lowStock = Product::where('is_active', true)
            ->where(function ($q) {
                $q->whereColumn('stock', '<=', 'low_stock_threshold')
                    ->orWhere('stock', '<=', 5);
            })
            ->orderBy('stock')
            ->take(5)
            ->get();

        // Sales by day (last 14 days)
        $salesByDay = Order::completed()
            ->where('completed_at', '>=', now()->subDays(14))
            ->get()
            ->groupBy(fn ($o) => $o->completed_at->format('Y-m-d'))
            ->map(fn ($g) => round($g->sum('total'), 2));

        // Order status distribution
        $orderStatusDist = collect(Order::STATUSES)->mapWithKeys(
            fn ($s) => [$s => Order::where('status', $s)->count()]
        );

        // New user registrations last 14 days
        $newUsersByDay = User::where('created_at', '>=', now()->subDays(14))
            ->get()
            ->groupBy(fn ($u) => $u->created_at->format('Y-m-d'))
            ->map(fn ($g) => $g->count());

        // Top selling categories
        $topCategories = Category::whereHas('products')
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'sold' => (int) $c->products()->sum('sold_count'),
            ])
            ->sortByDesc('sold')
            ->take(5)
            ->values();

        return view('superadmin.dashboard.index', compact(
            'stats', 'recentOrders', 'recentUsers', 'bestSelling', 'lowStock',
            'salesByDay', 'orderStatusDist', 'newUsersByDay', 'topCategories', 'recentModeration'
        ));
    }
}
