<h1>SHOPPICK</h1>
<p><strong>Shop Sales Report</strong></p>
<p>Shop: {{ $shop->name }}<br>Seller: {{ $shop->user?->name ?? 'Unavailable seller' }}<br>Report Period: {{ $period }}<br>Generated: {{ $generated->format('M d, Y H:i:s T') }}</p>
@if(isset($filters['query']['status']) || isset($filters['query']['q']))<p class="muted">Filters: {{ isset($filters['query']['status']) ? 'Status: '.str($filters['query']['status'])->headline() : '' }} {{ isset($filters['query']['q']) ? 'Search: '.$filters['query']['q'] : '' }}</p>@endif
<h2>Summary</h2>
<table><thead><tr><th>Total Sales</th><th>Completed Orders</th><th>Units Sold</th><th>Average Order Value</th></tr></thead><tbody><tr><td class="amount">₱{{ number_format($summary['sales'],2) }}</td><td>{{ number_format($summary['orders']) }}</td><td>{{ number_format($summary['units']) }}</td><td class="amount">₱{{ number_format($summary['average'],2) }}</td></tr></tbody></table>
<p class="muted">Sales use stored net seller totals from completed seller orders and their completion dates. Delivered, pending and cancelled orders do not contribute to these metrics. Payment refunds are not independently deducted by the existing seller analytics.</p>
<h2>Performance: {{ $performance }}</h2>
@if($previous)
    <p class="muted">Previous Period: {{ $filters['previousFrom']->format('M d, Y') }} – {{ $filters['previousTo']->format('M d, Y') }}. The same status/search filters apply to both periods.</p>
    <table><thead><tr><th>Metric</th><th>Previous</th><th>Current</th><th>Difference</th><th>Change</th></tr></thead><tbody>
    @foreach(['sales'=>'Sales','orders'=>'Orders','units'=>'Units Sold','average'=>'Average Order Value'] as $key=>$label)
        <tr><td>{{ $label }}</td><td>{{ in_array($key,['sales','average']) ? '₱'.number_format($previous[$key],2) : number_format($previous[$key]) }}</td><td>{{ in_array($key,['sales','average']) ? '₱'.number_format($summary[$key],2) : number_format($summary[$key]) }}</td><td>{{ $changes[$key]['difference'] > 0 ? '+' : '' }}{{ in_array($key,['sales','average']) ? '₱'.number_format($changes[$key]['difference'],2) : number_format($changes[$key]['difference']) }}</td><td>{{ $changes[$key]['label'] }}</td></tr>
    @endforeach
    </tbody></table>
@else<p class="muted">All Time has no equal previous period for comparison.</p>@endif
<h2>Sales by {{ $monthly ? 'Month' : 'Date' }}</h2>
<table><thead><tr><th>Period</th><th>Completed Orders</th><th>Sales</th></tr></thead><tbody>@forelse($salesByDate as $point)<tr><td>{{ $point->period ?? 'Unknown completion date' }}</td><td>{{ $point->orders }}</td><td>₱{{ number_format($point->sales,2) }}</td></tr>@empty<tr><td colspan="3">No completed sales in this period.</td></tr>@endforelse</tbody></table>
<h2>Top Products (up to 10)</h2>
<p class="muted">Item sales are stored item totals before order-level discounts and commission; they are not the net seller revenue above.</p>
<table><thead><tr><th>Product</th><th>Units Sold</th><th>Orders</th><th>Item Sales</th></tr></thead><tbody>@forelse($topProducts as $product)<tr><td>{{ $product->product_name }}</td><td>{{ $product->units }}</td><td>{{ $product->orders }}</td><td>₱{{ number_format($product->item_sales,2) }}</td></tr>@empty<tr><td colspan="4">No products sold in this period.</td></tr>@endforelse</tbody></table>
<h2>Order Status Breakdown</h2>
<p class="muted">Completed rows use completion dates; all other statuses use order placement dates. These same rows are included in the CSV. Noncompleted totals are not recognized sales.</p>
<table><thead><tr><th>Status</th><th>Seller Orders</th></tr></thead><tbody>@forelse($statusBreakdown as $status)<tr><td>{{ str($status->status)->headline() }}</td><td>{{ $status->orders }}</td></tr>@empty<tr><td colspan="2">No matching seller orders.</td></tr>@endforelse</tbody></table>
<h2>Recent Orders (latest 20 matching seller orders)</h2>
<table><thead><tr><th>Order / Buyer</th><th>Placed / Completed</th><th>Status</th><th>Units</th><th>Seller Total</th></tr></thead><tbody>@forelse($recentOrders as $order)<tr><td>{{ $order->seller_order_number }}<br>{{ $order->order?->buyer_name ?: ($order->order?->user?->name ?? 'Unavailable buyer') }}</td><td>{{ $order->created_at->format('M d, Y') }}<br>{{ $order->completed_at?->format('M d, Y') ?? '—' }}</td><td>{{ str($order->status)->headline() }}</td><td>{{ (int)($order->items_sum_quantity ?? 0) }}</td><td>₱{{ number_format($order->seller_total,2) }}</td></tr>@empty<tr><td colspan="5">No matching seller orders.</td></tr>@endforelse</tbody></table>
<h2>Current Inventory Snapshot</h2>
<p class="muted">Current stock, not date-filtered. Counts follow the existing Inventory product-stock rules.</p>
<table><thead><tr><th>Products</th><th>Low Stock</th><th>Out of Stock</th></tr></thead><tbody><tr><td>{{ $inventory->products }}</td><td>{{ $inventory->low_stock }}</td><td>{{ $inventory->out_of_stock }}</td></tr></tbody></table>
