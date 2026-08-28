<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Review;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $store = auth()->user()->store()->withCount('products')->firstOrFail();
        $orders = SellerOrder::where('store_id', $store->id);
        $stats = ['today_sales' => (clone $orders)->where('status','completed')->whereDate('completed_at',today())->sum('seller_total'),
            'new_orders' => (clone $orders)->where('status','pending')->count(), 'products' => $store->products_count,
            'low_stock' => $store->products()->whereColumn('stock', '<=', 'low_stock_threshold')->count(),
            'earnings' => (clone $orders)->where('status', 'completed')->sum('seller_total')];
        $recentOrders = $orders->with(['order.user', 'items'])->latest()->limit(8)->get();
        $lowStockProducts=$store->products()->whereColumn('stock','<=','low_stock_threshold')->orderBy('stock')->limit(5)->get();
        $recentReviews=Review::visible()->whereHas('product',fn($q)=>$q->where('store_id',$store->id))->with(['product','user'])->latest()->limit(4)->get();
        $salesChart=collect(range(6,0))->map(function($days)use($store){$date=today()->subDays($days); return ['label'=>$date->format('D'),'value'=>(float)SellerOrder::where('store_id',$store->id)->where('status','completed')->whereDate('completed_at',$date)->sum('seller_total')];});
        return view('seller.dashboard', compact('store', 'stats', 'recentOrders','lowStockProducts','recentReviews','salesChart'));
    }
}
