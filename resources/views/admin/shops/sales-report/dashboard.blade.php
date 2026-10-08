@include('admin.sales-reports.ui.metrics')
<p class="report-source">Completed seller-order net sales, recorded on completion dates. Delivered, pending and cancelled orders do not contribute to sales.</p>
<div class="report-sections">
    <section class="report-card" aria-labelledby="report-performance-title">
        <div class="report-filter-heading"><div><h2 id="report-performance-title" class="report-section-title">Performance</h2><p class="report-note">Current results compared with the previous period</p></div>@include('admin.sales-reports.ui.badge',['label'=>$performance])</div>
        @if($previous)
        <p class="report-note">Compared with: {{ $filters['previousFrom']->format('M d, Y') }} – {{ $filters['previousTo']->format('M d, Y') }}. The same status/search filters apply to both periods.</p>
        <div class="report-performance-grid">
            @foreach(['sales'=>'Sales','orders'=>'Orders','units'=>'Units Sold','average'=>'Average Order'] as $key=>$label)
            <div><p class="report-note mb-2">{{ $label }}</p>@include('admin.sales-reports.ui.change',['change'=>$changes[$key]])<p class="report-note mt-2">Previous: {{ in_array($key,['sales','average']) ? '₱'.number_format($previous[$key],2) : number_format($previous[$key]) }}</p><p class="report-note">Current: {{ in_array($key,['sales','average']) ? '₱'.number_format($summary[$key],2) : number_format($summary[$key]) }}</p></div>
            @endforeach
        </div>
        @else
        <div class="rounded-xl bg-slate-50 p-4"><p class="report-strong">No comparable period</p><p class="report-note mt-1">Choose a date range to compare sales, orders, units and average order value with the previous period.</p></div>
        @endif
    </section>
    <div class="report-two-columns">
        <section class="report-card" aria-labelledby="report-sales-title">
            <h2 id="report-sales-title" class="report-section-title">Sales Over Time</h2><p class="report-note mb-4">{{ $monthly ? 'Monthly' : 'Daily' }} completed sales · PHP</p>
            @php($maxSales = max(1,(float)$salesByDate->max('sales')))
            <div class="report-table-wrap"><table class="report-table"><thead><tr><th scope="col">Period</th><th scope="col" class="report-number">Orders</th><th scope="col" class="report-number">Sales</th></tr></thead><tbody>
            @forelse($salesByDate as $point)<tr><td><p class="report-strong">{{ $point->period ?? 'Unknown completion date' }}</p><div class="report-progress" aria-hidden="true"><span style="width:{{ max(0,min(100,$point->sales/$maxSales*100)) }}%"></span></div></td><td class="report-number">{{ number_format($point->orders) }}</td><td class="report-number report-strong">₱{{ number_format($point->sales,2) }}</td></tr>@empty<tr><td colspan="3" class="report-empty">No completed sales in this period.</td></tr>@endforelse
            </tbody></table></div>
        </section>
        <section class="report-card" aria-labelledby="report-status-title">
            <h2 id="report-status-title" class="report-section-title">Order Status Breakdown</h2><p class="report-note">Matching seller orders by status</p>
            @php($statusTotal = max(1,(int)$statusBreakdown->sum('orders')))
            @forelse($statusBreakdown as $status)
            <div class="report-status-row"><div class="report-status-heading">@include('admin.sales-reports.ui.badge',['label'=>(string)str($status->status)->headline()])<span class="report-number report-strong">{{ number_format($status->orders) }}</span></div><div class="report-progress" aria-hidden="true"><span style="width:{{ $status->orders/$statusTotal*100 }}%;{{ $status->status==='cancelled' ? 'background:#fb923c' : '' }}"></span></div></div>
            @empty<div class="report-empty">No matching seller orders.</div>@endforelse
            <p class="report-source">Completed orders use completion dates; other statuses use placement dates. Noncompleted orders are not recognized sales.</p>
        </section>
    </div>
    <section class="report-card" aria-labelledby="report-products-title">
        <h2 id="report-products-title" class="report-section-title">Top Products</h2><p class="report-note mb-4">Up to 10 products · Item sales before order-level discounts and commission</p>
        <div class="report-table-wrap"><table class="report-table"><thead><tr><th scope="col">Product</th><th scope="col" class="report-number">Units Sold</th><th scope="col" class="report-number">Orders</th><th scope="col" class="report-number">Item Sales</th></tr></thead><tbody>
        @forelse($topProducts as $product)<tr><td><div class="report-identity"><span class="report-avatar" aria-hidden="true">@include('admin.sales-reports.ui.icon',['icon'=>'product'])</span><div><span class="report-strong">{{ $product->product_name }}</span>@if($loop->iteration<=3)<span class="report-top-rank ml-2">#{{ $loop->iteration }}</span>@endif</div></div></td><td class="report-number">{{ number_format($product->units) }}</td><td class="report-number">{{ number_format($product->orders) }}</td><td class="report-number report-strong">₱{{ number_format($product->item_sales,2) }}</td></tr>@empty<tr><td colspan="4" class="report-empty">No products sold in this period.</td></tr>@endforelse
        </tbody></table></div><p class="report-source">Item sales are gross item totals and differ from the net seller revenue in the summary.</p>
    </section>
    <section class="report-card" aria-labelledby="report-orders-title">
        <h2 id="report-orders-title" class="report-section-title">Recent Orders</h2><p class="report-note mb-4">Latest 20 matching seller orders</p>
        <div class="report-table-wrap"><table class="report-table"><thead><tr>@foreach(['Order','Buyer','Placed / Completed','Units','Status','Seller Total','Action'] as $label)<th scope="col" class="{{ in_array($label,['Units','Seller Total']) ? 'report-number' : '' }}">{{ $label }}</th>@endforeach</tr></thead><tbody>
        @forelse($recentOrders as $order)<tr><td class="report-strong">{{ $order->seller_order_number }}</td><td>{{ $order->order?->buyer_name ?: ($order->order?->user?->name ?? 'Unavailable buyer') }}</td><td class="whitespace-nowrap">{{ $order->created_at->format('M d, Y') }}<p class="report-note">{{ $order->completed_at?->format('M d, Y') ?? 'Not completed' }}</p></td><td class="report-number">{{ (int)($order->items_sum_quantity ?? 0) }}</td><td>@include('admin.sales-reports.ui.badge',['label'=>(string)str($order->status)->headline()])</td><td class="report-number report-strong">₱{{ number_format($order->seller_total,2) }}</td><td>@if($order->order_id)<a class="report-action" aria-label="View order {{ $order->seller_order_number }}" href="{{ route('admin.orders.show',$order->order_id) }}">View <span aria-hidden="true">→</span></a>@endif</td></tr>@empty<tr><td colspan="7" class="report-empty">No matching seller orders.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="report-card" aria-labelledby="report-inventory-title">
        <h2 id="report-inventory-title" class="report-section-title">Current Inventory Snapshot</h2><p class="report-note">Current stock, not date-filtered</p>
        <div class="report-inventory">@foreach(['products'=>'Products','low_stock'=>'Low Stock','out_of_stock'=>'Out of Stock'] as $key=>$label)<div><p class="report-note mb-2">{{ $label }}</p><p class="report-value">{{ number_format($inventory->$key) }}</p></div>@endforeach</div>
    </section>
</div>
<p class="report-source">Generated: {{ $generated->format('M d, Y H:i T') }}</p>
