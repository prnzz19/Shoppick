<article class="waybill-document">
    <header class="waybill-doc-head">
        <div><p class="waybill-brand">SHOPPICK</p><h1 class="waybill-doc-title">Shipping Waybill</h1></div>
        <div class="waybill-doc-info">
            <div class="waybill-doc-number"><span>SHOPPICK Waybill Number</span><strong>{{ $shipment->shipment_number }}</strong><p class="waybill-doc-note">Printed {{ $printedAt }}</p></div>
            <section class="waybill-barcode" aria-label="Waybill barcode">
                <div role="img" aria-label="Code 128 barcode for {{ $shipment->shipment_number }}">{!! $barcodeSvg !!}</div>
                <strong>{{ $shipment->shipment_number }}</strong>
            </section>
        </div>
    </header>
    <div class="waybill-parties">
        <section class="waybill-party"><h2>Ship From</h2><strong>{{ $store?->name ?? 'SHOPPICK Seller' }}</strong><p>{{ $pickupAddress ?: 'Pickup address not available' }}</p></section>
        <section class="waybill-party"><h2>Ship To</h2><strong>{{ $recipient ?: 'Recipient' }}</strong><p>{{ $deliveryAddress ?: 'Delivery address not available' }}</p>@if($recipientPhone)<p>{{ $recipientPhone }}</p>@endif</section>
    </div>
    <section class="waybill-facts">
        <div class="waybill-fact"><span class="waybill-label">Buyer Order</span><strong>{{ $order->order_number }}</strong></div>
        <div class="waybill-fact"><span class="waybill-label">Seller Order</span><strong>{{ $sellerOrder->seller_order_number }}</strong></div>
        <div class="waybill-fact"><span class="waybill-label">Delivery Status</span><strong>{{ str($shipment->status)->headline() }}</strong></div>
        <div class="waybill-fact"><span class="waybill-label">Order Date</span><strong>{{ $sellerOrder->created_at?->format('M d, Y') }}</strong></div>
        @if($shipment->parcel_code)<div class="waybill-fact"><span class="waybill-label">SHOPPICK Parcel Scan Code</span><strong>{{ $shipment->parcel_code }}</strong></div>@endif
        @if($shipment->rider)<div class="waybill-fact"><span class="waybill-label">Assigned Rider</span><strong>{{ $shipment->rider->name }}</strong></div>@endif
    </section>
    <h2 class="waybill-section-title">Order Items</h2>
    <table class="waybill-items">
        <thead><tr><th>Product</th><th>Variant / SKU</th><th>Quantity</th></tr></thead>
        <tbody>
        @forelse($sellerOrder->items as $item)
            <tr><td>{{ $item->product_name }}</td><td>{{ $item->variant_label ?: ($item->sku ?: '—') }}</td><td>{{ $item->quantity }}</td></tr>
        @empty
            <tr><td class="waybill-no-items" colspan="3">No item details available.</td></tr>
        @endforelse
        </tbody>
    </table>
    <section class="waybill-payment">
        <div><span class="waybill-label">Payment Method</span><strong>{{ $paymentMethod }}</strong>@if($paymentStatus)<p>{{ $paymentStatus }}</p>@elseif($isCod)<p>Payment is due on delivery. See SHOPPICK Logistics for order-level collection.</p>@endif</div>
        @if($codAmount !== null)<div><span class="waybill-label">COD Amount</span><strong>₱{{ number_format($codAmount,2) }}</strong></div>@endif
        <div><span class="waybill-label">Package Reference</span><strong>{{ $shipment->shipment_number }}</strong></div>
    </section>
</article>
