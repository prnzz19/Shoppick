<div class="report-chart-dashboard" data-sales-chart-dashboard>
    <script type="application/json" data-sales-chart-data>{!! json_encode($charts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <section class="report-card report-trend-card">
        <div class="report-filter-heading"><div><p class="report-eyebrow">Revenue overview</p><h2 class="report-section-title">{{ isset($shop) ? $shop->name.' - Sales Trend' : 'Sales Trend Analysis' }}</h2><p class="report-note">Completed seller-order net sales &middot; {{ ucfirst($charts['grouping']) }} intervals &middot; PHP</p></div>@include('admin.sales-reports.ui.badge',['label'=>$charts['performance']])</div>
        <div class="report-chart-legend"><span><i class="current"></i>Current sales</span>@if($charts['previous'])<span><i class="previous"></i>Previous sales</span><span><i class="growth"></i>Growth %</span>@else<span class="report-note">Select a date range for a previous-period comparison.</span>@endif</div>
        @if($charts['hasSales'] && count($charts['trend']))
        <div class="report-chart-frame report-chart-wide"><canvas data-report-chart="trend" role="img" aria-label="Current and previous period sales with percentage growth. Exact values are in the chart data table below."></canvas></div>
        @else<div class="report-chart-empty"><span class="report-icon-tile">@include('admin.sales-reports.ui.icon',['icon'=>'sales'])</span><strong>No completed sales for this period.</strong><span>Try another date range or shop to explore sales performance.</span></div>@endif
        @if(abs($charts['undatedSales'])>0.005)<p class="report-note">&#8369;{{ number_format($charts['undatedSales'],2) }} has no completion date and is included in totals, but cannot be plotted by date.</p>@endif
        @if($charts['previous'])<p class="report-source">Aligned by interval position within each period. Growth is unavailable when prior sales are zero or an interval has no counterpart.</p>@endif
        <details class="report-chart-data"><summary>View chart data</summary><div class="report-table-wrap"><table class="report-table"><thead><tr><th>Current interval</th><th>Previous interval</th><th>Current sales (PHP)</th><th>Previous sales (PHP)</th><th>Growth</th></tr></thead><tbody>@forelse($charts['trend'] as $point)<tr><td>{{ $point['label'] }}</td><td>{{ $point['previousLabel'] ?? 'No comparison' }}</td><td>{{ $point['current'] === null ? 'No interval' : number_format($point['current'],2) }}</td><td>{{ $point['previous'] === null ? 'No comparison' : number_format($point['previous'],2) }}</td><td>{{ $point['growth'] === null ? 'Not comparable' : sprintf('%+.1f%%',$point['growth']) }}</td></tr>@empty<tr><td colspan="5">No completed sales for this period.</td></tr>@endforelse</tbody></table></div></details>
    </section>
    <div class="report-chart-grid">
        <section class="report-card"><h2 class="report-section-title">Order Status</h2><p class="report-note">Matching seller orders &middot; {{ number_format(array_sum(array_column($charts['statuses'],'value'))) }} total</p>
            @if(count($charts['statuses']))<div class="report-chart-frame"><canvas data-report-chart="statuses" role="img" aria-label="Order status distribution. Counts and percentages follow."></canvas></div><ul class="report-status-legend">@foreach($charts['statuses'] as $row)<li><span>{{ $row['label'] }}</span><strong>{{ number_format($row['value']) }} <small>{{ number_format($row['value']/max(1,array_sum(array_column($charts['statuses'],'value')))*100,1) }}%</small></strong></li>@endforeach</ul>@else<div class="report-chart-empty compact"><strong>No orders for this period.</strong><span>Order statuses will appear here.</span></div>@endif
            <p class="report-source">Completed orders use completion dates; other statuses use placement dates.</p>
        </section>
        @if($charts['topShops'] !== null)
        <section class="report-card"><h2 class="report-section-title">Top 5 Shops by Sales</h2><p class="report-note">Completed net sales &middot; All matching shops</p>
            @include('admin.sales-reports.ranking',['rows'=>$charts['topShops'],'kind'=>'shops','empty'=>'No shop sales data for this period.'])
        </section>
        @endif
        <section class="report-card {{ $charts['topShops'] !== null ? 'report-chart-products' : '' }}"><div class="report-filter-heading"><div><h2 class="report-section-title">Top 5 Products</h2><p class="report-note">Ranked across all matching completed orders</p></div><label class="report-note">Rank by <select data-product-ranking class="input"><option value="sales">Sales</option><option value="units">Units Sold</option></select></label></div>
            @include('admin.sales-reports.ranking',['rows'=>$charts['products']['sales'],'kind'=>'products','empty'=>'No products sold for this period.'])
            <p class="report-source">Sales use the same net allocation as Analytics. Order-level net sales are allocated by item value; orders without product records remain in the summary.</p>
        </section>
    </div>
</div>
