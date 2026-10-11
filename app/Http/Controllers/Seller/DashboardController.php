<?php
namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerDashboardService;
use App\Services\ShopSalesReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SellerDashboardService $dashboard, ShopSalesReportService $reports)
    {
        $store = $request->user()->store()->firstOrFail();
        // Only dates are accepted; request-provided shop/category IDs cannot broaden seller scope.
        $dates = $request->only('range','from','to');
        $dates['range'] ??= '30days';
        $filters = $reports->filters(Request::create('/', 'GET', $dates));
        return view('seller.dashboard', $dashboard->data($store, $filters) + ['dashboardUnread'=>$request->user()->notificationsData()->unread()->count()]);
    }
}
