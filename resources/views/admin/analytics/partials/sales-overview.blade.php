<div class="dashboard-sales-overview" data-sales-chart-dashboard data-dashboard-overview>
    <script type="application/json" data-sales-chart-data>{!! json_encode($overview, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <div class="overview-period"><span>{{ $period }}</span>@include('admin.sales-reports.ui.badge',['label'=>$performance])<a href="{{ route('admin.sales-reports.index',array_intersect_key($filters['query'],array_flip(['range','from','to']))) }}">View Sales Reports &rarr;</a></div>
    @include('admin.sales-reports.ui.metrics')
    <div class="overview-grid">
        <section class="report-card"><p class="report-eyebrow">Category contribution</p><h3 class="report-section-title">Sales by Category</h3><p class="report-note">@if(count($overview['categories']))Top 5 &middot; Recognized completed sales @else Category preview &middot; No completed sales @endif</p>
            @include('admin.analytics.partials.sales-ranking',['rows'=>$overview['categories'],'kind'=>'categories','empty'=>'No completed sales recorded for this period.'])
        </section>
        <section class="report-card"><p class="report-eyebrow">Period performance</p><h3 class="report-section-title">Sales by {{ ucfirst($overview['grouping']) }}</h3><p class="report-note">Completed net sales &middot; PHP</p>
            <div class="overview-chart"><canvas data-report-chart="period" role="img" aria-label="Completed net sales by period. Values are available in the trend data table."></canvas>
                @if(!array_sum(array_column($overview['trend'],'currentOrders')))@include('admin.analytics.partials.sales-empty',['message'=>$summary['orders'] ? 'No dated completed sales to plot.' : 'No completed sales for this period.'])@endif
            </div>
            <p class="report-source">Sales are grouped by completion date.</p>
        </section>
        <section class="report-card overview-trend"><div class="overview-card-heading"><div><p class="report-eyebrow">Compare over time</p><h3 class="report-section-title">Sales Trend</h3></div><div class="report-chart-legend"><span><i class="current"></i>Current</span>@if($previous)<span><i class="previous"></i>Previous</span>@endif</div></div>
            <div class="overview-chart overview-trend-chart"><canvas data-report-chart="comparison" role="img" aria-label="Current versus previous period sales. Exact values are in the data table below."></canvas>
                @if(!array_sum(array_column($overview['trend'],'currentOrders')) && !array_sum(array_column($overview['trend'],'previousOrders')))@include('admin.analytics.partials.sales-empty',['message'=>$overview['hasSales'] ? 'No dated completed sales to plot.' : 'No completed sales yet.'])@endif
            </div>
            <p class="report-source">{{ $previous ? 'Periods align by interval position. An unmatched interval has no comparison.' : 'All Time has no equivalent previous period.' }}</p>
            @if(abs($overview['undatedSales'])>0.005)<p class="report-note">&#8369;{{ number_format($overview['undatedSales'],2) }} has no recorded completion date; included in totals, excluded from the dated chart.</p>@endif
            <details class="report-chart-data"><summary>View sales and orders by period</summary><div class="report-table-wrap"><table class="report-table"><thead><tr><th>Current interval</th><th>Previous interval</th><th>Current sales (PHP)</th><th>Previous sales (PHP)</th><th>Completed orders</th></tr></thead><tbody>@forelse($overview['trend'] as $point)<tr><td>{{ $point['label'] }}</td><td>{{ $point['previousLabel'] ?? 'No comparison' }}</td><td>{{ $point['current'] === null ? 'No interval' : number_format($point['current'],2) }}</td><td>{{ $point['previous'] === null ? 'No comparison' : number_format($point['previous'],2) }}</td><td>{{ $point['currentOrders'] ?? 'No interval' }}</td></tr>@empty<tr><td colspan="5">No completed sales recorded for this period.</td></tr>@endforelse</tbody></table></div></details>
        </section>
        <section class="report-card overview-shops"><div class="overview-card-heading"><div><p class="report-eyebrow">Leading sellers</p><h3 class="report-section-title">Top Shops</h3></div><span class="report-note">Top 5</span></div>
            <ol class="overview-shop-list">@forelse($topShops as $row)<li><span class="overview-rank">{{ $loop->iteration }}</span><span class="overview-avatar">@if($row->logo)<img src="{{ asset('storage/'.$row->logo) }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr($row->name,0,1)) }}@endif</span><div class="overview-shop-name"><a href="{{ route('admin.shops.sales-report',[$row->id]+$detailQuery) }}">{{ $row->name }}</a><span>{{ $row->seller_name ?? 'Unavailable seller' }}</span></div><strong>&#8369;{{ number_format($row->sales,2) }}</strong></li>@empty<li class="overview-empty">No shop sales available for this period.</li>@endforelse</ol>
        </section>
        <section class="report-card overview-products"><div class="overview-card-heading"><div><p class="report-eyebrow">Product performance</p><h3 class="report-section-title">Top Products</h3></div><span class="report-note">Top 5 by sales</span></div>
            @include('admin.analytics.partials.sales-ranking',['rows'=>$overview['products']['sales'],'kind'=>'products','empty'=>'No products sold during this period.'])
            <p class="report-source">Order-level net sales allocated by item value, using the same rules as Analytics.</p>
        </section>
        <section class="report-card overview-status"><h3 class="report-section-title">Order Status Breakdown</h3><p class="report-note">{{ number_format($statusTotal) }} matching seller orders</p>
            @if($statusTotal)<div class="overview-status-layout"><div class="overview-chart"><canvas data-report-chart="statuses" role="img" aria-label="Order status distribution. Counts and percentages follow."></canvas></div><ul class="report-status-legend">@foreach($overview['statuses'] as $row)<li><span>{{ $row['label'] }}</span><strong>{{ number_format($row['value']) }} <small>{{ number_format($row['value']/$statusTotal*100,1) }}%</small></strong></li>@endforeach</ul></div>@else<div class="overview-empty">No orders recorded for this period.</div>@endif
            <p class="report-source">Completed orders use completion dates; other statuses use placement dates.</p>
        </section>
        <section class="report-card overview-insights"><div class="overview-card-heading"><div><p class="report-eyebrow">What the numbers show</p><h3 class="report-section-title">Sales Insights</h3></div><a href="#analytics-details">Explore Analytics &rarr;</a></div><div class="overview-insight-grid">@foreach($insights as $insight)<div><span class="analytics-priority {{ $insight['priority']==='Needs Attention'?'attention':($insight['priority']==='Watch'?'watch':'') }}">{{ $insight['priority'] }}</span><p>{{ $insight['text'] }}</p></div>@endforeach</div></section>
    </div>
</div>
