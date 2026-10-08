<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use App\Models\SellerOrder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
class SellerController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.sellers.active', $this->sellerData($request));
    }

    public function filter(Request $request)
    {
        $data = $this->sellerData($request, false);
        return response()->json([
            'summary_html' => view('admin.sellers.partials.summary', $data)->render(),
            'table_html' => view('admin.sellers.partials.table', $data)->render(),
            'pagination_html' => view('admin.sellers.partials.pagination', $data)->render(),
            'tabs_html' => view('admin.sellers.partials.tabs', $data)->render(),
            'period_html' => view('admin.sellers.partials.period', $data)->render(),
            'counts' => $data['archiveCounts'],
            'query' => (object) $data['queryParams'],
            'url' => route('admin.sellers.index', $data['queryParams']),
            'total' => $data['sellers']->total(),
            'page' => $data['sellers']->currentPage(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function sellerData(Request $request, bool $includeShops = true): array
    {
        // Keep the existing from/to URLs; accept date_from/date_to aliases as well.
        $request->merge([
            'from' => $request->input('from', $request->input('date_from')),
            'to' => $request->input('to', $request->input('date_to')),
        ]);
        $ranges = ['all' => 'All Time', 'today' => 'Today', '7days' => 'Last 7 Days', '30days' => 'Last 30 Days', 'month' => 'This Month', 'last_month' => 'Last Month', 'custom' => 'Custom Range'];
        $sorts = ['newest' => 'Newest Seller', 'oldest' => 'Oldest Seller', 'sales_desc' => 'Highest Sales', 'sales_asc' => 'Lowest Sales', 'orders_desc' => 'Most Orders', 'orders_asc' => 'Least Orders', 'shop' => 'Shop Name A–Z', 'name' => 'Seller Name A–Z'];
        $validated = $request->validate([
            'q' => 'nullable|string|max:200', 'shop' => 'nullable|integer|exists:stores,id',
            'range' => ['nullable', Rule::in(array_keys($ranges))],
            'sort' => ['nullable', Rule::in(array_keys($sorts))],
            'status' => ['nullable', Rule::in(['approved', 'suspended'])],
            'shop_status' => ['nullable', Rule::in(['active', 'suspended'])],
            'sales' => ['nullable', Rule::in(['with', 'none'])],
            'from' => 'nullable|required_if:range,custom|date_format:Y-m-d',
            'to' => 'nullable|required_if:range,custom|date_format:Y-m-d|after_or_equal:from',
            'page' => 'nullable|integer|min:1',
            'tab' => ['nullable', Rule::in(['current', 'archived'])],
        ]);
        $queryParams = array_filter($validated, fn ($value) => $value !== null && $value !== '');
        if (isset($queryParams['q'])) {
            $queryParams['q'] = trim($queryParams['q']);
            if ($queryParams['q'] === '') unset($queryParams['q']);
        }
        foreach (['range' => 'all', 'sort' => 'newest', 'page' => '1', 'tab' => 'current'] as $key => $default) {
            if (isset($queryParams[$key]) && (string) $queryParams[$key] === $default) unset($queryParams[$key]);
        }
        if (($queryParams['range'] ?? 'all') !== 'custom') unset($queryParams['from'], $queryParams['to']);
        $request->merge(['q' => $queryParams['q'] ?? null]);
        $range = $request->input('range') ?: 'all';
        [$from, $to] = match ($range) {
            'today' => [today(), today()],
            '7days' => [today()->subDays(6), today()],
            '30days' => [today()->subDays(29), today()],
            'month' => [today()->startOfMonth(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [Carbon::parse($request->from), Carbon::parse($request->to)],
            default => [null, null],
        };
        // Match the seller Sales/Dashboard source of truth: completed net seller totals.
        // Count vendor orders, never the shared parent order or its full buyer total.
        $performance = SellerOrder::query()->select('store_id')
            ->selectRaw('SUM(seller_total) as sales_total, COUNT(*) as orders_count')
            ->where('status', 'completed')
            ->when($from, fn ($q) => $q->where('completed_at', '>=', $from->copy()->startOfDay())
                ->where('completed_at', '<', $to->copy()->addDay()->startOfDay()))
            ->groupBy('store_id');
        $archived = $request->input('tab') === 'archived';
        $archiveCounts = [
            'normal' => User::whereHas('roles', fn($q)=>$q->where('slug','seller'))->whereHas('sellerProfile', fn($q)=>$q->whereNull('archived_at')->whereIn('status',['approved','suspended']))->whereHas('store')->count(),
            'archived' => User::whereHas('roles', fn($q)=>$q->where('slug','seller'))->whereHas('sellerProfile', fn($q)=>$q->whereNotNull('archived_at'))->count(),
        ];
        $query = User::whereHas('roles', fn($q)=>$q->where('slug','seller'))->whereHas('sellerProfile', fn($q)=>$q->when($archived, fn($q)=>$q->whereNotNull('archived_at'), fn($q)=>$q->whereNull('archived_at')->whereIn('status',['approved','suspended'])))
            ->when(! $archived, fn($q)=>$q->whereHas('store'))->with(['sellerProfile','store'])
            ->leftJoin('stores as seller_shop', fn ($join) => $join->on('seller_shop.user_id', '=', 'users.id')->whereNull('seller_shop.deleted_at'))
            ->leftJoinSub($performance, 'performance', 'performance.store_id', '=', 'seller_shop.id')
            ->leftJoinSub(Store::marketplaceActive()->select('stores.id'), 'active_shop', 'active_shop.id', '=', 'seller_shop.id')
            ->select('users.*')->selectRaw('COALESCE(performance.sales_total, 0) as sales_total, COALESCE(performance.orders_count, 0) as orders_count, CASE WHEN active_shop.id IS NULL THEN 0 ELSE 1 END as shop_active')
            ->when($request->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('users.name','like','%'.$request->q.'%')->orWhere('users.email','like','%'.$request->q.'%')->orWhereHas('store',fn($s)=>$s->where('stores.name','like','%'.$request->q.'%'))))
            ->when($request->filled('shop'), fn ($q) => $q->where('seller_shop.id', $request->shop))
            ->when($request->filled('status'), fn ($q) => $q->whereHas('sellerProfile', fn ($p) => $p->where('status', $request->status)))
            ->when($request->filled('shop_status'), fn ($q) => $q->where('seller_shop.status', $request->shop_status))
            ->when($request->sales === 'with', fn ($q) => $q->whereRaw('COALESCE(performance.sales_total, 0) > 0'))
            ->when($request->sales === 'none', fn ($q) => $q->whereRaw('COALESCE(performance.sales_total, 0) = 0'));
        $summary = \Illuminate\Support\Facades\DB::query()->fromSub((clone $query)->toBase(), 'sellers')
            ->selectRaw('COUNT(*) as sellers, COALESCE(SUM(shop_active), 0) as active_shops, COALESCE(SUM(sales_total), 0) as sales, COALESCE(SUM(orders_count), 0) as orders')->first();
        $shops = $includeShops
            ? Store::whereHas('user.roles', fn ($q) => $q->where('slug', 'seller'))->orderBy('name')->get(['id', 'name'])
            : collect();
        [$column, $direction] = match ($request->input('sort')) {
            'oldest' => ['users.created_at', 'asc'], 'sales_desc' => ['sales_total', 'desc'],
            'sales_asc' => ['sales_total', 'asc'], 'orders_desc' => ['orders_count', 'desc'],
            'orders_asc' => ['orders_count', 'asc'], 'shop' => ['seller_shop.name', 'asc'],
            'name' => ['users.name', 'asc'], default => ['users.created_at', 'desc'],
        };
        $sellers = $query->orderBy($column, $direction)->orderBy('users.id')->paginate(15)
            ->withPath(route('admin.sellers.index'))->appends($queryParams);
        $hasFilters = count(array_diff_key($queryParams, array_flip(['tab', 'page']))) > 0;
        return compact('sellers','archiveCounts','archived','summary','shops','ranges','sorts','range','from','to','queryParams','hasFilters');
    }
    public function show(User $user)
    {
        abort_unless($user->hasRole('seller') && in_array($user->sellerProfile?->status, ['approved', 'suspended'], true) && $user->store,404);
        $user->load(['store.products','sellerApplications.reviewer']);
        $orderCount=$user->store->sellerOrders()->count();
        $sales=$user->store->sellerOrders()->where('status','completed')->sum('seller_total');
        return view('admin.sellers.show',compact('user','orderCount','sales'));
    }
}
