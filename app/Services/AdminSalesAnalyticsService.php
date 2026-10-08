<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminSalesAnalyticsService
{
    public const SORTS = ['sales_desc' => 'Highest Sales', 'orders_desc' => 'Most Orders', 'growth' => 'Highest Growth', 'sales_asc' => 'Lowest Sales'];

    public function __construct(private ShopSalesReportService $reports, private MarketplaceSalesReportService $marketplace) {}

    public function filters(Request $request, string $default = '30days'): array
    {
        $data = $request->validate(['shop' => 'nullable|integer|exists:stores,id', 'category' => 'nullable|integer|exists:categories,id', 'sort' => ['nullable', Rule::in(array_keys(self::SORTS))], 'page' => 'nullable|integer|min:1']);
        $dates = $request->only('range', 'from', 'to', 'status');
        if (! isset($dates['range'])) {
            $dates['range'] = isset($dates['from'],$dates['to']) ? 'custom' : $default;
        }
        $filters = $this->reports->filters(Request::create('/', 'GET', $dates));
        foreach (['shop', 'category', 'sort'] as $key) {
            if (! empty($data[$key])) {
                $filters['query'][$key] = (string) $data[$key];
            }
        }

        return $filters;
    }

    private function storeIds(array $filters)
    {
        return Store::select('stores.id')->when(isset($filters['query']['shop']), fn ($q) => $q->where('stores.id', $filters['query']['shop']));
    }

    private function orders(array $filters, bool $sales = true, bool $previous = false)
    {
        $query = $this->reports->orders(null, $filters, $sales, $previous)->whereIn('store_id', $this->storeIds($filters));
        if (isset($filters['query']['category'])) {
            $query->whereHas('items.product', fn ($q) => $q->where('category_id', $filters['query']['category']));
        }

        return $query;
    }

    private function items(array $filters, bool $previous = false)
    {
        return $this->reports->allocatedItems($filters, $previous)->whereIn('store_id', $this->storeIds($filters));
    }

    private function breakdown(array $filters, string $dimension, bool $previous = false)
    {
        $key = $dimension === 'category' ? 'category_id' : 'product_id';
        $query = $this->items($filters, $previous);
        if ($dimension === 'product') {
            $query->whereNotNull('product_id')->addSelect('store_id')->selectRaw('MAX(product_image) as product_image')->groupBy('store_id');
        }

        return $query->addSelect($key)->selectRaw('MAX('.$dimension.'_name) as name, SUM(allocated_sales) as sales, COUNT(DISTINCT seller_order_id) as orders, SUM(quantity) as units')->groupBy($key);
    }

    private function comparedBreakdown(array $filters, string $dimension)
    {
        $key = $dimension === 'category' ? 'category_id' : 'product_id';
        $current = $this->breakdown($filters, $dimension);
        $previous = $this->breakdown($filters, $dimension, true);
        // Union the two grouped periods once, including previous-only activity.
        // Products keep the seller-order shop identity across both periods.
        $now = DB::query()->fromSub($current, 'current_values')->selectRaw($key.' as id, name, sales, orders, units, 0 as previous_sales');
        $before = DB::query()->fromSub($previous, 'previous_values')->selectRaw($key.' as id, name, 0 as sales, 0 as orders, 0 as units, sales as previous_sales');
        if (! $filters['from']) {
            $before->whereRaw('1 = 0');
        }
        if ($dimension === 'product') {
            $now->addSelect('store_id', 'product_image');
            $before->addSelect('store_id', 'product_image');
        }
        $grouped = DB::query()->fromSub($now->unionAll($before), 'period_values')
            ->selectRaw('id, MAX(name) as name, SUM(sales) as sales, SUM(orders) as orders, SUM(units) as units, SUM(previous_sales) as previous_sales')->groupBy('id');
        if ($dimension === 'product') {
            $grouped->addSelect('store_id')->selectRaw('MAX(product_image) as product_image')->groupBy('store_id');
        }
        $query = DB::query()->fromSub($grouped, 'compared_sales');
        if ($dimension === 'product') {
            $query->leftJoin('stores', 'stores.id', '=', 'compared_sales.store_id')->select('compared_sales.*', 'stores.name as shop_name');
        }

        return $query;
    }

    private function stats($rows): array
    {
        $sales = (float) $rows->sales;
        $orders = (int) $rows->orders;

        return ['sales' => $sales, 'orders' => $orders, 'units' => (int) $rows->units, 'average' => $orders ? $sales / $orders : 0.0];
    }

    public function data(array $filters, bool $dashboard = false, bool $export = false): array
    {
        $shopQuery = $this->marketplace->query($filters);
        $totals = (clone $shopQuery)->selectRaw('COALESCE(SUM(sales),0) as sales, COALESCE(SUM(orders),0) as orders, COALESCE(SUM(units),0) as units, COALESCE(SUM(previous_sales),0) as previous_sales, COALESCE(SUM(previous_orders),0) as previous_orders, COALESCE(SUM(previous_units),0) as previous_units, COUNT(*) as shops, COALESCE(SUM(CASE WHEN orders>0 THEN 1 ELSE 0 END),0) as shops_with_sales, COALESCE(SUM(CASE WHEN previous_orders>0 THEN 1 ELSE 0 END),0) as previous_shops_with_sales')->first();
        $summary = $this->stats($totals);
        $previous = $filters['from'] ? $this->stats((object) ['sales' => $totals->previous_sales, 'orders' => $totals->previous_orders, 'units' => $totals->previous_units]) : null;
        ['changes' => $changes,'performance' => $performance] = $this->reports->comparison($summary, $previous);
        $counts = ['shops' => (int) $totals->shops, 'selling' => (int) $totals->shops_with_sales, 'no_sales' => (int) $totals->shops - (int) $totals->shops_with_sales, 'previous_selling' => (int) $totals->previous_shops_with_sales];
        $topShops = (clone $shopQuery)->where('orders', '>', 0)->orderByDesc('sales')->orderBy('id')->limit(5)->get();
        $topFiveShare = $summary['sales'] > 0 ? $topShops->sum('sales') / $summary['sales'] * 100 : 0;
        $sort = $filters['query']['sort'] ?? 'sales_desc';
        match ($sort) {
            'orders_desc' => $shopQuery->orderByDesc('orders'),'sales_asc' => $shopQuery->orderBy('sales'),'growth' => $shopQuery->orderByRaw('CASE WHEN previous_sales>0 THEN (sales-previous_sales)*1.0/previous_sales ELSE NULL END DESC'),default => $shopQuery->orderByDesc('sales')
        };
        $shopQuery->orderBy('id');
        $shops = $dashboard ? $topShops : ($export ? $shopQuery->cursor() : $shopQuery->paginate(10)->withPath(route('admin.analytics.index'))->appends($filters['query']));
        $categoryQuery = $this->comparedBreakdown($filters, 'category');
        $categoryDecline = (clone $categoryQuery)->whereColumn('sales', '<', 'previous_sales')->orderByRaw('sales - previous_sales')->first();
        $categories = $export ? (clone $categoryQuery)->orderByDesc('sales')->get() : (clone $categoryQuery)->orderByDesc('sales')->limit(20)->get();
        $products = $this->comparedBreakdown($filters, 'product')->orderByDesc('sales')->orderBy('id')->limit($dashboard ? 5 : 10)->get();
        $monthly = ! $filters['from'] || $filters['from']->diffInDays($filters['to']) > 90;
        $bucket = $monthly ? (DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', seller_orders.completed_at)" : "DATE_FORMAT(seller_orders.completed_at, '%Y-%m')") : 'DATE(seller_orders.completed_at)';
        // Category attribution uses the same grouped net amounts as KPI cards.
        $aggregate = $this->reports->salesAggregate(null, $filters)->whereIn('seller_orders.store_id', $this->storeIds($filters));
        $series = $aggregate->addSelect(DB::raw($bucket.' as period'))->groupByRaw($bucket)->orderBy('period')->get();
        $chart = $this->chart($series, $filters, $monthly);
        $statuses = $this->orders($filters, false)->select('status')->selectRaw('COUNT(*) as orders')->groupBy('status')->orderBy('status')->get();
        $oldStatuses = $filters['from'] ? $this->orders($filters, false, true)->select('status')->selectRaw('COUNT(*) as orders')->groupBy('status')->pluck('orders', 'status') : collect();
        $statusTotal = (int) $statuses->sum('orders');
        $cancellations = ['current' => (int) ($statuses->firstWhere('status', 'cancelled')?->orders ?? 0), 'previous' => $filters['from'] ? (int) ($oldStatuses['cancelled'] ?? 0) : null, 'rate' => $statusTotal ? (float) ($statuses->firstWhere('status', 'cancelled')?->orders ?? 0) / $statusTotal * 100 : 0, 'previous_rate' => $filters['from'] ? ($oldStatuses->sum() ? ($oldStatuses['cancelled'] ?? 0) / $oldStatuses->sum() * 100 : 0) : null];
        $noSaleQuery = Product::whereIn('store_id', $this->storeIds($filters))->when(isset($filters['query']['category']), fn ($q) => $q->where('category_id', $filters['query']['category']))->whereNotIn('id', $this->items($filters)->whereNotNull('product_id')->select('product_id'));
        $noSaleProducts = ['count' => (clone $noSaleQuery)->count(), 'out_of_stock' => (clone $noSaleQuery)->where('stock', '<=', 0)->count(), 'rows' => $dashboard ? collect() : (clone $noSaleQuery)->with('store:id,name')->orderBy('stock')->orderBy('id')->limit(10)->get()];
        $noSaleShops = (clone $this->marketplace->query($filters))->where('orders', 0)->orderBy('name')->limit(10)->get();
        $insights = $this->insights($summary, $previous, $changes, $counts, $topShops, collect($categoryDecline ? [$categoryDecline] : []), $cancellations, $noSaleProducts);
        if ($previous) {
            $decliningShop = (clone $this->marketplace->query($filters))->whereColumn('sales', '<', 'previous_sales')->orderByRaw('sales - previous_sales')->first();
            if ($decliningShop) {
                $cancelled = (clone $this->orders($filters, false))->where('store_id', $decliningShop->id)->where('status', 'cancelled')->count();
                $priorCancelled = (clone $this->orders($filters, false, true))->where('store_id', $decliningShop->id)->where('status', 'cancelled')->count();
                $insights[] = ['priority' => 'Observed', 'text' => $decliningShop->name.' recorded the largest shop decrease: ₱'.number_format($decliningShop->previous_sales - $decliningShop->sales, 2).' ('.number_format(($decliningShop->previous_sales - $decliningShop->sales) / $decliningShop->previous_sales * 100, 1).'%). Completed orders: '.$decliningShop->previous_orders.' to '.$decliningShop->orders.'. Cancellations: '.$priorCancelled.' to '.$cancelled.'. These are observed changes during the same period.'];
            }
        }
        if ($dashboard) {
            $insights = array_slice($insights, 0, 4);
        }
        $period = $filters['from'] ? $filters['from']->format('M d, Y').' – '.$filters['to']->format('M d, Y') : 'All Time';
        $generated = now();
        $detailQuery = array_intersect_key($filters['query'], array_flip(['range', 'from', 'to', 'status']));
        $decorate = function ($row) use ($summary, $previous) {
            $row->share_percent = $summary['sales'] ? $row->sales / $summary['sales'] * 100 : 0;
            $row->trend = $this->reports->change((float) $row->sales, $previous ? (float) $row->previous_sales : null);

            return $row;
        };
        $topShops->each($decorate);
        if ($export) {
            $shops = $shops->map($decorate);
        } elseif (! $dashboard) {
            $shops->setCollection($shops->getCollection()->map($decorate));
        }
        $categories->each($decorate);
        $products->each($decorate);
        $statusRows = collect(SellerOrder::STATUSES)->map(function ($status) use ($statuses, $oldStatuses, $previous, $statusTotal) {
            $count = (int) ($statuses->firstWhere('status', $status)?->orders ?? 0);

            return (object) ['status' => $status, 'orders' => $count, 'share_percent' => $statusTotal ? $count / $statusTotal * 100 : 0, 'previous_orders' => $previous ? (int) ($oldStatuses[$status] ?? 0) : null, 'change' => $this->reports->change($count, $previous ? (int) ($oldStatuses[$status] ?? 0) : null)];
        });

        return compact('filters', 'summary', 'previous', 'changes', 'performance', 'counts', 'topShops', 'topFiveShare', 'shops', 'categories', 'products', 'series', 'chart', 'monthly', 'statuses', 'statusRows', 'oldStatuses', 'statusTotal', 'cancellations', 'noSaleProducts', 'noSaleShops', 'insights', 'period', 'generated', 'detailQuery', 'dashboard');
    }

    private function insights(array $summary, ?array $previous, array $changes, array $counts, $shops, $categories, array $cancellations, array $noProducts): array
    {
        $items = [];
        $percent = $changes['sales']['percent'];
        $priority = $percent !== null && $percent < -20 ? 'Needs Attention' : ($percent !== null && $percent <= -10 ? 'Watch' : 'Normal');
        $items[] = ['priority' => $priority, 'text' => $previous ? ($previous['sales'] > 0 ? ($percent == 0 ? 'Sales were unchanged compared with the previous period.' : 'Sales '.($percent < 0 ? 'decreased' : 'increased').' '.number_format(abs($percent), 1).'% compared with the previous period.') : 'No prior completed sales baseline is available. Current net sales: ₱'.number_format($summary['sales'], 2).'.') : 'All Time has no equivalent previous period for comparison.'];
        if ($previous && $summary['orders'] !== $previous['orders']) {
            $items[] = ['priority' => 'Observed', 'text' => 'Completed seller orders changed from '.$previous['orders'].' to '.$summary['orders'].' during the same period.'];
        }
        if ($cancellations['previous'] !== null && $cancellations['current'] > $cancellations['previous']) {
            $items[] = ['priority' => 'Watch', 'text' => 'Cancelled seller orders increased from '.$cancellations['previous'].' to '.$cancellations['current'].'. These changes occurred during the same period; no causal link is established.'];
        }
        if ($previous && $counts['selling'] < $counts['previous_selling']) {
            $items[] = ['priority' => 'Observed', 'text' => ($counts['previous_selling'] - $counts['selling']).' fewer shops recorded completed sales than in the previous period.'];
        }
        $decline = $categories->filter(fn ($row) => $row->sales < $row->previous_sales)->sortBy(fn ($row) => $row->sales - $row->previous_sales)->first();
        if ($previous && $decline) {
            $items[] = ['priority' => 'Observed', 'text' => $decline->name.' recorded the largest category decrease: ₱'.number_format($decline->previous_sales - $decline->sales, 2).' in allocated net sales.'];
        }
        if ($summary['sales'] > 0 && $shops->first()) {
            $items[] = ['priority' => 'Observed', 'text' => $shops->first()->name.' contributed '.number_format($shops->first()->sales / $summary['sales'] * 100, 1).'% of completed net sales.'];
        }
        $items[] = ['priority' => 'Observed', 'text' => $counts['no_sales'].' of '.$counts['shops'].' shops recorded no completed seller orders in this period.'];
        if ($noProducts['count']) {
            $items[] = ['priority' => 'Context', 'text' => $noProducts['out_of_stock'].' of '.$noProducts['count'].' products with no completed sales are currently out of stock. Stock is a current snapshot, not a proven cause.'];
        }

        return $items;
    }

    public function options(): array
    {
        return ['shopOptions' => Store::orderBy('name')->get(['id', 'name']), 'categoryOptions' => Category::orderBy('name')->get(['id', 'name'])];
    }

    private function chart($series, array $filters, bool $monthly): array
    {
        $values = $series->filter(fn ($row) => $row->period !== null)->keyBy('period');
        $start = $filters['from']?->copy() ?? ($values->isNotEmpty() ? Carbon::parse($values->keys()->first()) : null);
        $end = $filters['to']?->copy() ?? ($values->isNotEmpty() ? Carbon::parse($values->keys()->last()) : null);
        $points = [];
        if ($start && $end) {
            if ($monthly) {
                $start->startOfMonth();
                $end->endOfMonth();
            }
            for ($date = $start->copy(); $date->lte($end); $monthly ? $date->addMonth() : $date->addDay()) {
                $key = $date->format($monthly ? 'Y-m' : 'Y-m-d');
                $points[] = ['period' => $key, 'sales' => (float) ($values[$key]->sales ?? 0)];
            }
        }
        $min = min(array_merge([0], array_column($points, 'sales')));
        $max = max(array_merge([1], array_column($points, 'sales')));
        foreach ($points as $i => &$point) {
            $point['x'] = round(35 + $i / max(1, count($points) - 1) * 650, 2);
            $point['y'] = round(175 - ($point['sales'] - $min) / ($max - $min) * 140, 2);
        }unset($point);

        return ['points' => $points, 'line' => implode(' ', array_map(fn ($p) => $p['x'].','.$p['y'], $points)), 'zero' => round(175 - (0 - $min) / ($max - $min) * 140, 2), 'max' => $max, 'min' => $min, 'undated_sales' => (float) ($series->firstWhere('period', null)?->sales ?? 0)];
    }
}
