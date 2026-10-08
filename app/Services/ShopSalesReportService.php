<?php

namespace App\Services;

use App\Models\{OrderItem, SellerOrder, Store};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShopSalesReportService
{
    public const RANGES = ['all'=>'All Time', 'today'=>'Today', '7days'=>'Last 7 Days', '30days'=>'Last 30 Days',
        'week'=>'This Week', 'last_week'=>'Last Week', 'month'=>'This Month', 'last_month'=>'Last Month', 'custom'=>'Custom Range'];

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'range'=>['nullable', Rule::in(array_keys(self::RANGES))],
            'from'=>'nullable|required_if:range,custom|date_format:Y-m-d',
            'to'=>'nullable|required_if:range,custom|date_format:Y-m-d|after_or_equal:from',
            'status'=>['nullable', Rule::in(SellerOrder::STATUSES)],
            'q'=>'nullable|string|max:200',
        ]);
        $range = $data['range'] ?? 'all';
        $range = $range ?: 'all';
        [$from, $to] = match ($range) {
            'today'=>[today(), today()],
            '7days'=>[today()->subDays(6), today()],
            '30days'=>[today()->subDays(29), today()],
            'week'=>[today()->startOfWeek(Carbon::MONDAY), today()->endOfWeek(Carbon::SUNDAY)],
            'last_week'=>[today()->subWeek()->startOfWeek(Carbon::MONDAY), today()->subWeek()->endOfWeek(Carbon::SUNDAY)],
            'month'=>[today()->startOfMonth(), today()->endOfMonth()],
            'last_month'=>[today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'custom'=>[Carbon::parse($data['from']), Carbon::parse($data['to'])],
            default=>[null, null],
        };
        $previousFrom = $previousTo = null;
        if ($from) {
            $previousTo = $from->copy()->subDay()->startOfDay();
            $previousFrom = in_array($range, ['month','last_month'], true)
                ? $previousTo->copy()->startOfMonth()
                : $from->copy()->subDays((int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        }
        $query = ['range'=>$range];
        if ($range === 'custom') $query += ['from'=>$data['from'], 'to'=>$data['to']];
        if (!empty($data['status'])) $query['status'] = $data['status'];
        if (trim($data['q'] ?? '') !== '') $query['q'] = trim($data['q']);
        return compact('range','from','to','previousFrom','previousTo','query');
    }

    public function orders(?Store $shop, array $filters, bool $sales = false, bool $previous = false): Builder
    {
        $query = SellerOrder::query()->when($shop, fn ($q) => $q->where('store_id', $shop->id));
        if ($sales) $query->where('status','completed');
        if (isset($filters['query']['status'])) $query->where('status',$filters['query']['status']);
        if (isset($filters['query']['q'])) {
            $search = '%'.$filters['query']['q'].'%';
            $query->where(fn ($q) => $q->where('seller_order_number','like',$search)
                ->orWhereHas('order', fn ($order) => $order->where('order_number','like',$search)->orWhere('buyer_name','like',$search)
                    ->orWhereHas('user', fn ($user) => $user->where('name','like',$search))));
        }
        $from = $previous ? $filters['previousFrom'] : $filters['from'];
        $to = $previous ? $filters['previousTo'] : $filters['to'];
        // Financial metrics use completion dates, matching existing seller analytics.
        // Noncompleted rows use placement dates. Completed rows use the financial
        // completion date, so CSV completed totals exactly match the report cards.
        if ($from) {
            $start = $from->copy()->startOfDay();
            $end = $to->copy()->addDay()->startOfDay();
            if ($sales) {
                $query->where('completed_at','>=',$start)->where('completed_at','<',$end);
            } else {
                $query->where(fn ($date) => $date
                    ->where(fn ($completed) => $completed->where('status','completed')->where('completed_at','>=',$start)->where('completed_at','<',$end))
                    ->orWhere(fn ($open) => $open->where('status','!=','completed')->where('created_at','>=',$start)->where('created_at','<',$end)));
            }
        }
        return $query;
    }

    public function salesAggregate(?Store $shop, array $filters, bool $previous = false): Builder
    {
        if (isset($filters['query']['category'])) {
            $items = $this->allocatedItems($filters,$previous)->select('seller_order_id')
                ->selectRaw('SUM(allocated_sales) as sales, SUM(quantity) as units')->groupBy('seller_order_id');
            return $this->orders($shop,$filters,true,$previous)->joinSub($items,'category_sales','category_sales.seller_order_id','=','seller_orders.id')
                ->selectRaw('COALESCE(SUM(category_sales.sales),0) as sales, COUNT(*) as orders, COALESCE(SUM(category_sales.units),0) as units, MIN(completed_at) as first_sale, MAX(completed_at) as last_sale');
        }
        $units = OrderItem::select('seller_order_id')->selectRaw('SUM(quantity) as units')->groupBy('seller_order_id');
        return $this->orders($shop,$filters,true,$previous)->leftJoinSub($units,'item_units','item_units.seller_order_id','=','seller_orders.id')
            ->selectRaw('COALESCE(SUM(seller_total),0) as sales, COUNT(*) as orders, COALESCE(SUM(item_units.units),0) as units, MIN(completed_at) as first_sale, MAX(completed_at) as last_sale');
    }

    public function allocatedItems(array $filters, bool $previous = false)
    {
        // Attribution only: preserve each completed vendor order's stored net total.
        // Gross item value determines shares; zero-value orders use quantity, then
        // equal shares. Orders without item records remain an unallocated bucket.
        $weights = DB::table('order_items')->select('seller_order_id')->selectRaw('SUM(CASE WHEN total > 0 THEN total ELSE 0 END) as gross, SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as units, COUNT(*) as item_count')->groupBy('seller_order_id');
        $orders = $this->orders(null,$filters,true,$previous)->select('seller_orders.*');
        $items = DB::query()->fromSub($orders,'recognized_orders')
            ->leftJoin('order_items as item','item.seller_order_id','=','recognized_orders.id')
            ->leftJoinSub($weights,'weights','weights.seller_order_id','=','recognized_orders.id')
            ->leftJoin('products as product','product.id','=','item.product_id')
            ->leftJoin('categories as category','category.id','=','product.category_id')
            ->selectRaw("recognized_orders.id as seller_order_id, recognized_orders.store_id, recognized_orders.completed_at, item.product_id, COALESCE(product.name,item.product_name,'No item records') as product_name, item.product_image, product.category_id, COALESCE(category.name,'Uncategorized / unallocated') as category_name, COALESCE(item.quantity,0) as quantity,
                recognized_orders.seller_total * CASE WHEN item.id IS NULL THEN 1.0 WHEN weights.gross > 0 THEN 1.0 * CASE WHEN item.total > 0 THEN item.total ELSE 0 END / weights.gross WHEN weights.units > 0 THEN 1.0 * CASE WHEN item.quantity > 0 THEN item.quantity ELSE 0 END / weights.units ELSE 1.0 / weights.item_count END as allocated_sales");
        $query = DB::query()->fromSub($items,'allocated_items');
        if (isset($filters['query']['category'])) $query->where('category_id',$filters['query']['category']);
        return $query;
    }

    private function summary(Store $shop, array $filters, bool $previous = false): array
    {
        $row = $this->salesAggregate($shop,$filters,$previous)->first();
        return ['sales'=>(float)$row->sales, 'orders'=>(int)$row->orders, 'units'=>(int)$row->units,
            'average'=>$row->orders ? (float)$row->sales / $row->orders : 0, 'first_sale'=>$row->first_sale, 'last_sale'=>$row->last_sale];
    }

    public function build(Store $shop, array $filters): array
    {
        $shop->loadMissing('user:id,name');
        $summary = $this->summary($shop,$filters);
        $previous = $filters['from'] ? $this->summary($shop,$filters,true) : null;
        ['changes'=>$changes,'performance'=>$performance] = $this->comparison($summary,$previous);
        $completedIds = $this->orders($shop,$filters,true)->select('seller_orders.id');
        $topProducts = OrderItem::whereIn('seller_order_id',clone $completedIds)
            ->select('product_id','product_name')->selectRaw('SUM(quantity) as units, COUNT(DISTINCT seller_order_id) as orders, SUM(total) as item_sales')
            ->groupBy('product_id','product_name')->orderByDesc('item_sales')->orderBy('product_name')->limit(10)->get();
        $monthly = !$filters['from'] || $filters['from']->diffInDays($filters['to']) > 90;
        $bucket = $monthly
            ? (DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', completed_at)" : "DATE_FORMAT(completed_at, '%Y-%m')")
            : 'DATE(completed_at)';
        $salesByDate = $this->orders($shop,$filters,true)->selectRaw("{$bucket} as period, SUM(seller_total) as sales, COUNT(*) as orders")
            ->groupByRaw($bucket)->orderBy('period')->get();
        $statusBreakdown = $this->orders($shop,$filters)->select('status')->selectRaw('COUNT(*) as orders')->groupBy('status')->orderBy('status')->get();
        $recentOrders = $this->orders($shop,$filters)->with('order.user:id,name')->withSum('items','quantity')->latest('created_at')->orderByDesc('id')->limit(20)->get();
        $inventory = $shop->products()->selectRaw('COUNT(*) as products, COALESCE(SUM(CASE WHEN stock > 0 AND (stock <= low_stock_threshold OR stock <= 5) THEN 1 ELSE 0 END),0) as low_stock, COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END),0) as out_of_stock')->first();
        $period = $filters['from'] ? $filters['from']->format('M d, Y').' – '.$filters['to']->format('M d, Y') : 'All Time';
        $generated = now();
        return compact('shop','filters','summary','previous','changes','performance','topProducts','salesByDate','statusBreakdown','recentOrders','inventory','period','generated','monthly');
    }

    public const PERFORMANCE = ['Improving','Stable','Declining','Mixed Performance','New Activity','No Sales','No comparable period'];

    public function comparison(array $summary, ?array $previous): array
    {
        $changes = [];
        foreach (['sales','orders','units','average'] as $key) {
            $before = $previous[$key] ?? null;
            $changes[$key] = $this->change($summary[$key],$before);
        }
        return ['changes'=>$changes,'performance'=>$this->performanceFor($summary,$previous)];
    }

    public function change($current, $previous): array
    {
        return ['difference'=>$previous === null ? null : $current-$previous,
            'percent'=>$previous ? ($current-$previous)/$previous*100 : null,
            'label'=>$previous === null ? 'No comparable period' : ($previous == 0 ? ($current > 0 ? 'New Activity' : 'No change') : sprintf('%+.1f%%',($current-$previous)/$previous*100))];
    }

    public function performanceSql(array $current, array $previous, bool $comparable): string
    {
        if (!$comparable) return "'No comparable period'";
        $up = $down = $same = [];
        foreach (['sales','orders','units','average'] as $key) {
            $up[] = "({$current[$key]} > {$previous[$key]})";
            $down[] = "({$current[$key]} < {$previous[$key]})";
            $same[] = "({$current[$key]} = {$previous[$key]})";
        }
        return "CASE WHEN {$current['orders']} = 0 AND {$previous['orders']} = 0 THEN 'No Sales'
            WHEN {$previous['orders']} = 0 AND {$current['orders']} > 0 THEN 'New Activity'
            WHEN ".implode(' AND ',$same)." THEN 'Stable'
            WHEN (".implode(' OR ',$up).") AND (".implode(' OR ',$down).") THEN 'Mixed Performance'
            WHEN ".implode(' OR ',$up)." THEN 'Improving' ELSE 'Declining' END";
    }

    private function performanceFor(array $summary, ?array $previous): string
    {
        // Bind values; no report input is interpolated into SQL.
        $metrics = ['sales','orders','units','average'];
        $current = $before = [];
        $select = $bindings = [];
        foreach ($metrics as $key) {
            $select[] = "CAST(? AS DECIMAL(20,6)) as current_{$key}";
            $select[] = "CAST(? AS DECIMAL(20,6)) as previous_{$key}";
            $bindings[] = $summary[$key];
            $bindings[] = $previous[$key] ?? 0;
            $current[$key] = "current_{$key}";
            $before[$key] = "previous_{$key}";
        }
        $values = DB::query()->selectRaw(implode(',',$select),$bindings);
        return DB::query()->fromSub($values,'metrics')->selectRaw($this->performanceSql($current,$before,$previous !== null).' as performance')->first()->performance;
    }
}
