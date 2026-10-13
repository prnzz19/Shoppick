<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SHOPPICK Seller Sales Report</title>
    @include('admin.shops.sales-report.styles')
</head>
<body>
<main class="shop-sales-report-content">
    <h1>SHOPPICK Seller Sales Report</h1>
    <p><strong>Shop:</strong> {{ $store->name }}<br>
        <strong>Seller:</strong> {{ $sellerName }}<br>
        <strong>Report Period:</strong> {{ $period }}<br>
        <strong>Generated:</strong> {{ $generated->format('M d, Y H:i') }} {{ config('app.timezone') }}</p>

    <h2>Earnings Summary</h2>
    <table>
        <thead><tr><th>Gross Sales</th><th>Commission</th><th>Net Earnings</th><th>Pending Earnings</th></tr></thead>
        <tbody><tr>
            <td>₱{{ number_format($earnings->gross,2) }}</td>
            <td>₱{{ number_format($earnings->commission,2) }}</td>
            <td>₱{{ number_format($earnings->net,2) }}</td>
            <td>₱{{ number_format($stats['pending'],2) }}</td>
        </tr></tbody>
    </table>

    <h2>Transactions</h2>
    <table>
        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Gross</th><th>Commission</th><th>Net</th></tr></thead>
        <tbody>
        @forelse($reportOrders as $order)
            <tr>
                <td>{{ $order->seller_order_number }}</td>
                <td>{{ $order->created_at?->format('M d, Y') }}</td>
                <td>{{ str($order->status)->headline() }}</td>
                <td>₱{{ number_format($order->subtotal,2) }}</td>
                <td>₱{{ number_format($order->commission_amount,2) }}</td>
                <td>₱{{ number_format($order->seller_total,2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6">No transactions match this report period.</td></tr>
        @endforelse
        </tbody>
    </table>
    <p class="muted">Completed orders are recognized as earnings by completion date. Pending and cancelled orders are not included in completed earnings.</p>
</main>
</body>
</html>
