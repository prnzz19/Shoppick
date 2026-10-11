<?php
namespace App\Services;

use App\Models\Store;

/** Read-only seller overview; shared reporting scopes retain completion-date recognition. */
class SellerDashboardService
{
    public function __construct(private ShopSalesReportService $reports, private SalesReportChartService $charts) {}

    public function data(Store $store, array $filters): array
    {
        $totals = function (bool $prior = false) use ($store, $filters) {
            $sales = $this->reports->orders($store, $filters, true, $prior)
                ->selectRaw('COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(seller_total),0) as net, COALESCE(SUM(commission_amount),0) as commission')->first();
            $orders = $this->reports->orders($store, $filters, false, $prior);
            return ['gross'=>(float)$sales->gross, 'net'=>(float)$sales->net, 'commission'=>(float)$sales->commission,
                'orders'=>(clone $orders)->count(),
                'pending'=>(float)(clone $orders)->whereNotIn('status',['completed','cancelled'])->sum('seller_total')];
        };
        $metrics = $totals();
        $prior = $filters['from'] ? $totals(true) : null;
        $changes = [];
        foreach (['gross','net','orders'] as $key) $changes[$key] = $this->reports->change($metrics[$key], $prior[$key] ?? null);
        // Pending earnings are a current status snapshot, not historical payout balances.
        $changes['pending'] = $this->reports->change($metrics['pending'], null);
        $chart = $this->charts->timeline($filters, Store::whereKey($store->id)->select('id'), $store, true);
        $chart['statuses'] = $this->reports->orders($store,$filters,false)->select('status')->selectRaw('COUNT(*) as count')
            ->groupBy('status')->orderBy('status')->get()->map(fn($row)=>['status'=>$row->status,'label'=>(string)str($row->status)->headline(),'value'=>(int)$row->count])->all();
        $recentOrders = $this->reports->orders($store,$filters,false)->with('order')->latest()->limit(6)->get();
        $inventory = $store->products()->with(['images','store'])->orderBy('stock')->orderBy('id')->limit(5)->get();
        return compact('metrics','prior','changes','chart','recentOrders','inventory','filters','store');
    }
}
