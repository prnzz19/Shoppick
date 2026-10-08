<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketplaceSalesReportService
{
    public const SORTS = ['sales_desc'=>'Highest Sales','sales_asc'=>'Lowest Sales','orders_desc'=>'Most Orders','name'=>'Shop Name'];

    public function __construct(private ShopSalesReportService $reports) {}

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'shop'=>'nullable|integer|exists:stores,id', 'q'=>'nullable|string|max:200',
            'shop_status'=>['nullable',Rule::in(['active','suspended'])],
            'performance'=>['nullable',Rule::in(ShopSalesReportService::PERFORMANCE)],
            'sort'=>['nullable',Rule::in(array_keys(self::SORTS))], 'page'=>'nullable|integer|min:1',
        ]);
        // Overview search is for shops/sellers, never an order-search constraint.
        $filters = $this->reports->filters(Request::create('/', 'GET', $request->only('range','from','to')));
        foreach (['shop','q','shop_status','performance','sort'] as $key) {
            if (trim((string)($data[$key] ?? '')) !== '') $filters['query'][$key] = trim((string)$data[$key]);
        }
        return $filters;
    }

    public function query(array $filters)
    {
        $dates = $filters;
        $dates['query'] = array_intersect_key($filters['query'],array_flip(['range','from','to','status','category']));
        $current = $this->reports->salesAggregate(null,$dates)->addSelect('seller_orders.store_id')->groupBy('seller_orders.store_id');
        $previous = $this->reports->salesAggregate(null,$dates,true)->addSelect('seller_orders.store_id')->groupBy('seller_orders.store_id');
        $base = Store::query()->leftJoin('users','users.id','=','stores.user_id')
            ->leftJoinSub($current,'current_sales','current_sales.store_id','=','stores.id')
            ->leftJoinSub($previous,'previous_sales','previous_sales.store_id','=','stores.id')
            ->select('stores.id','stores.name','stores.status','users.name as seller_name','users.email as seller_email');
        $now = $before = [];
        foreach (['sales','orders','units'] as $key) {
            $now[$key] = "COALESCE(current_sales.{$key},0)";
            $before[$key] = "COALESCE(previous_sales.{$key},0)";
            $base->selectRaw("{$now[$key]} as {$key}, {$before[$key]} as previous_{$key}");
        }
        $now['average'] = 'COALESCE(1.0 * current_sales.sales / NULLIF(current_sales.orders,0),0)';
        $before['average'] = 'COALESCE(1.0 * previous_sales.sales / NULLIF(previous_sales.orders,0),0)';
        $base->selectRaw("{$now['average']} as average, {$before['average']} as previous_average")
            ->selectRaw($this->reports->performanceSql($now,$before,$filters['from'] !== null).' as performance');
        if (!empty($filters['query']['shop'])) $base->where('stores.id',$filters['query']['shop']);
        if (!empty($filters['query']['shop_status'])) $base->where('stores.status',$filters['query']['shop_status']);
        if (!empty($filters['query']['q'])) {
            $search = '%'.$filters['query']['q'].'%';
            $base->where(fn ($q) => $q->where('stores.name','like',$search)->orWhere('users.name','like',$search)->orWhere('users.email','like',$search));
        }
        $query = DB::query()->fromSub($base,'shop_sales');
        if (!empty($filters['query']['performance'])) $query->where('performance',$filters['query']['performance']);
        return $query;
    }

    public function data(array $filters, bool $export = false): array
    {
        $query = $this->query($filters);
        $totals = (clone $query)->selectRaw('COALESCE(SUM(sales),0) as sales, COALESCE(SUM(orders),0) as orders, COALESCE(SUM(units),0) as units, COUNT(*) as shops')->first();
        $summary = ['sales'=>(float)$totals->sales,'orders'=>(int)$totals->orders,'units'=>(int)$totals->units,
            'average'=>$totals->orders ? (float)$totals->sales / $totals->orders : 0,'shops'=>(int)$totals->shops];
        match ($filters['query']['sort'] ?? 'sales_desc') {
            'sales_asc'=>$query->orderBy('sales'), 'orders_desc'=>$query->orderByDesc('orders'),
            'name'=>$query->orderBy('name'), default=>$query->orderByDesc('sales'),
        };
        $query->orderBy('id');
        $shops = $export ? $query->cursor() : $query->paginate(15)->withPath(route('admin.sales-reports.index'))->appends($filters['query']);
        $period = $filters['from'] ? $filters['from']->format('M d, Y').' – '.$filters['to']->format('M d, Y') : 'All Time';
        $generated = now();
        $detailQuery = array_intersect_key($filters['query'],array_flip(['range','from','to']));
        return compact('filters','shops','summary','period','generated','detailQuery');
    }
}
