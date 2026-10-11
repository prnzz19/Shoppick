<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Chart presentation uses the existing recognition, allocation and comparison rules. */
class SalesReportChartService
{
    public function __construct(private ShopSalesReportService $reports) {}

    public function timeline(array $filters, $storeIds, ?Store $shop = null, bool $dashboard = false): array
    {
        $scope = fn ($query) => $query->whereIn('seller_orders.store_id', clone $storeIds);
        $current = $scope($this->reports->salesAggregate($shop, $filters))->first();
        $previous = $filters['from'] ? $scope($this->reports->salesAggregate($shop, $filters, true))->first() : null;
        $metrics = fn ($row) => ['sales'=>(float)$row->sales, 'orders'=>(int)$row->orders, 'units'=>(int)$row->units,
            'average'=>$row->orders ? (float)$row->sales / $row->orders : 0.0];
        $comparison = $this->reports->comparison($metrics($current), $previous ? $metrics($previous) : null);
        $from = $filters['from']?->copy() ?? ($current->first_sale ? Carbon::parse($current->first_sale)->startOfMonth() : null);
        $to = $filters['to']?->copy() ?? ($current->last_sale ? Carbon::parse($current->last_sale)->endOfMonth() : null);
        // An empty All Time dashboard shows the current month as a zero scaffold, not invented history.
        if ($dashboard && !$from && !$to) {
            $from = now()->startOfMonth();
            $to = now()->endOfMonth();
        }
        $days = $from && $to ? (int)$from->diffInDays($to->copy()->startOfDay()) + 1 : 0;
        $grouping = !$filters['from'] ? 'month' : ($days === 1 ? 'hour' : ($days <= ($dashboard ? 14 : 31) ? 'day' : ($days <= 120 ? 'week' : 'month')));
        $monthStep = $from && $to ? max(1, (int)ceil(($from->diffInMonths($to) + 1) / 24)) : 1;
        $windows = function ($start, $end) use ($grouping, $monthStep) {
            $rows = [];
            if (!$start || !$end) return $rows;
            $cursor = $start->copy()->startOfDay(); $stop = $end->copy()->addDay()->startOfDay();
            while ($cursor->lt($stop)) {
                $next = match ($grouping) {
                    'hour'=>$cursor->copy()->addHour(), 'day'=>$cursor->copy()->addDay(),
                    'week'=>$cursor->copy()->addWeek(), default=>$cursor->copy()->startOfMonth()->addMonths($monthStep),
                };
                if ($next->gt($stop)) $next = $stop->copy();
                $label = $grouping === 'hour' ? $cursor->format('M d, H:00') : ($grouping === 'month' ? $cursor->format('M Y') : $cursor->format('M d, Y'));
                if ($grouping === 'week' || ($grouping === 'month' && $monthStep > 1)) $label .= ' to '.$next->copy()->subDay()->format('M d, Y');
                $axisLabel = match ($grouping) {
                    'hour'=>$cursor->format('H:00'), 'day'=>$cursor->format('D'),
                    'week'=>'Week '.(count($rows) + 1), default=>$label,
                };
                $rows[] = ['axisLabel'=>$axisLabel, 'label'=>$label, 'start'=>$cursor->copy(), 'end'=>$next->copy(), 'sales'=>0.0, 'orders'=>0];
                $cursor = $next;
            }
            return $rows;
        };
        $series = function ($start, $end, bool $prior) use ($windows, $scope, $filters, $shop, $grouping) {
            $rows = $windows($start, $end);
            if (!$rows) return $rows;
            $bucket = $grouping === 'hour'
                ? (DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m-%d %H:00:00', completed_at)" : "DATE_FORMAT(completed_at, '%Y-%m-%d %H:00:00')")
                : 'DATE(completed_at)';
            $points = $scope($this->reports->salesAggregate($shop, $filters, $prior))
                ->whereNotNull('completed_at')->addSelect(DB::raw($bucket.' as bucket'))->groupByRaw($bucket)->orderBy('bucket')->cursor();
            $index = 0;
            foreach ($points as $point) {
                $date = Carbon::parse($point->bucket);
                while (isset($rows[$index]) && $date->gte($rows[$index]['end'])) $index++;
                if (isset($rows[$index]) && $date->gte($rows[$index]['start'])) {
                    $rows[$index]['sales'] += (float)$point->sales;
                    $rows[$index]['orders'] += (int)$point->orders;
                }
            }
            return $rows;
        };
        $now = $series($from, $to, false);
        $before = $previous ? $series($filters['previousFrom'], $filters['previousTo'], true) : [];
        $trend = [];
        for ($i=0; $i<max(count($now),count($before)); $i++) {
            $value = $now[$i]['sales'] ?? null; $prior = $previous ? ($before[$i]['sales'] ?? null) : null;
            $growth = $value !== null && $prior !== null ? $this->reports->change($value, $prior)['percent'] : null;
            $trend[] = ['axisLabel'=>$now[$i]['axisLabel'] ?? 'Prior only', 'label'=>$now[$i]['label'] ?? 'Prior-only interval', 'previousLabel'=>$before[$i]['label'] ?? null,
                'current'=>$value, 'previous'=>$prior, 'growth'=>$growth,
                'currentOrders'=>$now[$i]['orders'] ?? null, 'previousOrders'=>$before[$i]['orders'] ?? null];
        }
        return $comparison + ['previous'=>$previous ? $metrics($previous) : null, 'trend'=>$trend, 'grouping'=>$grouping,
            'hasSales'=>(int)$current->orders > 0 || ($previous && (int)$previous->orders > 0),
            'undatedSales'=>(float)$current->sales - array_sum(array_column($now,'sales'))];
    }

    public function data(array $filters, $storeIds, ?Store $shop = null): array
    {
        $timeline = $this->timeline($filters, $storeIds, $shop);
        $scope = fn ($query) => $query->whereIn('seller_orders.store_id', clone $storeIds);
        $statuses = $scope($this->reports->orders($shop, $filters))->select('status')->selectRaw('COUNT(*) as orders')
            ->groupBy('status')->orderBy('status')->get()->map(fn ($row)=>['label'=>(string)str($row->status)->headline(),'value'=>(int)$row->orders])->all();
        $topShops = $shop ? null : $scope($this->reports->salesAggregate(null, $filters))->joinSub(Store::select('id','name'), 'chart_shops', 'chart_shops.id', '=', 'seller_orders.store_id')
            ->addSelect('chart_shops.id','chart_shops.name')->groupBy('chart_shops.id','chart_shops.name')->orderByDesc('sales')->orderBy('chart_shops.id')->limit(5)->get()
            ->map(fn ($row)=>['label'=>$row->name,'value'=>(float)$row->sales])->all();
        $items = $this->reports->allocatedItems($filters)->whereIn('store_id', clone $storeIds)
            ->whereNotNull('product_id')->select('store_id','product_id')->selectRaw('MAX(product_name) as name, SUM(allocated_sales) as sales, SUM(quantity) as units')
            ->groupBy('store_id','product_id');
        $products = [];
        foreach (['sales','units'] as $rank) {
            $products[$rank] = (clone $items)->orderByDesc($rank)->orderBy('store_id')->orderBy('product_id')->limit(5)->get()
                ->map(fn ($row)=>['label'=>$row->name,'value'=>(float)$row->$rank])->all();
        }
        return $timeline + ['statuses'=>$statuses, 'topShops'=>$topShops, 'products'=>$products];
    }
}
