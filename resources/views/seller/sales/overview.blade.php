@php
    $categoryTotal=(float)$categories->sum('sales');
    $statusCounts=collect($chart['statuses'])->keyBy(fn($status)=>str($status['label'])->snake()->toString());
    $performanceStatuses=[
        'ready_to_ship'=>['Ready to Ship','teal'],
        'shipped'=>['Shipped','blue'],
        'delivered'=>['Delivered','violet'],
        'completed'=>['Completed','green'],
    ];
    $maxStatusCount=max(1,...array_map(fn($key)=>(int)($statusCounts[$key]['value']??0),array_keys($performanceStatuses)));
@endphp
<div class="sales-kpis">
    @foreach([['today',"Today's Sales",'Completed today','chart'],['month','This Month','Completed this month','orders'],['completed','Total Earnings','All-time completed net','store'],['pending','Pending Earnings','Open orders, not yet earned','bell']] as [$key,$label,$note,$icon])
    <article class="sales-card sales-kpi"><div><span class="sales-icon"><x-seller.icon :name="$icon"/></span><h2>{{ $label }}</h2></div><strong>&#8369;{{ number_format($stats[$key],2) }}</strong><p>{{ $note }}</p></article>
    @endforeach
</div>
<div class="sales-analytics">
    <article class="sales-card"><header><div><h2><x-seller.icon name="chart"/> Sales Overview</h2><p>Completed net sales &middot; {{ $period }}</p></div><span class="sales-chart-key">{{ ucfirst($chart['grouping']) }} intervals</span></header>
        <div class="sales-chart"><canvas data-sales-chart="overview" aria-label="Completed net sales by report interval" role="img"></canvas></div>
        @if(collect($chart['trend'])->sum('currentOrders')===0)<p class="sales-chart-note">No completed sales in this period.</p>@endif
    </article>
    <article class="sales-card"><header><div><h2><x-seller.icon name="chart"/> Sales Trend</h2><p>Your earnings over time</p></div><span class="sales-chart-key sales-key-orange">Net earnings</span></header>
        <div class="sales-chart"><canvas data-sales-chart="trend" aria-label="Completed net earnings trend" role="img"></canvas></div>
        @if(collect($chart['trend'])->sum('currentOrders')===0)<p class="sales-chart-note">Sales trend will update when completed orders are recorded.</p>@endif
    </article>
</div>
<div class="sales-support">
    <article class="sales-card sales-order-summary"><header><div><h2><x-seller.icon name="orders"/> Order Performance</h2><p>Order status in selected period</p></div></header><div class="sales-status-grid">@foreach($performanceStatuses as $key=>[$label,$color])@php($count=(int)($statusCounts[$key]['value']??0))<div class="sales-status status-{{ $color }}"><div><span><i class="sales-status-dot"></i>{{ $label }}</span><strong>{{ $count }}</strong></div><progress value="{{ $count }}" max="{{ $maxStatusCount }}" aria-label="{{ $label }}: {{ $count }} orders"></progress></div>@endforeach</div></article>
    <article class="sales-card sales-earnings"><header><div><h2><x-seller.icon name="store"/> Earnings Summary</h2><p>Selected-period earnings · pending open-order balance</p></div></header><dl><div><dt>Gross Sales</dt><dd>&#8369;{{ number_format($earnings->gross,2) }}</dd></div><div><dt>Commission</dt><dd>&#8369;{{ number_format($earnings->commission,2) }}</dd></div><div><dt>Net Earnings</dt><dd>&#8369;{{ number_format($earnings->net,2) }}</dd></div><div><dt>Pending Earnings</dt><dd>&#8369;{{ number_format($stats['pending'],2) }}</dd></div></dl></article>
</div>
<div class="sales-bottom">
    <article class="sales-card sales-transactions"><header><div><h2><x-seller.icon name="orders"/> Recent Transactions</h2><p>Your latest seller transactions</p></div><a href="{{ route('seller.sales.index',$filters['query']+['tab'=>'transactions'],false) }}">See Details <x-seller.icon name="right"/></a></header>@include('seller.sales.table')</article>
    <article class="sales-card sales-category-card"><header><div><h2><x-seller.icon name="box"/> Sales by Category</h2><p>Completed net earnings</p></div></header>
        <div class="sales-category-content {{ $categoryTotal<=0 ? 'sales-category-empty' : '' }}">
            <div class="sales-donut"><canvas data-sales-chart="categories" aria-label="Net earnings by category" role="img"></canvas><div><strong>&#8369;{{ number_format($categoryTotal,0) }}</strong><span>Net Sales</span></div></div>
            @if($categoryTotal>0)<ul class="sales-legend">@foreach($categories as $category)<li><span><i data-category-color="{{ $loop->index }}"></i>{{ $category->category_name }}</span><strong>&#8369;{{ number_format($category->sales,2) }} <small>{{ number_format($category->sales/$categoryTotal*100,1) }}%</small></strong></li>@endforeach</ul>
            @else<div class="sales-category-message"><p>No completed category sales yet.</p></div>@endif
        </div>
    </article>
</div>
