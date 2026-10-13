@extends('layouts.seller')
@section('title','Waybills')
@section('content')
@include('seller.waybills.styles')
<div class="seller-waybills">
    @if(session('success'))<p class="waybill-alert waybill-alert-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="waybill-alert waybill-alert-error" role="alert">{{ $errors->first() }}</p>@endif
    <header class="waybills-header">
        <div><p class="waybills-eyebrow">Seller Fulfillment</p><h1>Waybills</h1><p>Prepare, print, and manage shipping labels for your orders.</p></div>
    </header>
    <section class="waybills-metrics" aria-label="Waybill summary">
        <article class="waybills-card waybills-metric"><span>Ready for Waybill</span><strong>{{ number_format($stats['ready']) }}</strong></article>
        <article class="waybills-card waybills-metric"><span>Generated</span><strong>{{ number_format($stats['generated']) }}</strong></article>
        <article class="waybills-card waybills-metric"><span>Ready for Pickup</span><strong>{{ number_format($stats['pickup']) }}</strong></article>
        <article class="waybills-card waybills-metric"><span>In Transit</span><strong>{{ number_format($stats['transit']) }}</strong></article>
    </section>
    <section class="waybills-card">
        <form class="waybills-filter" method="GET" action="{{ route('seller.waybills.index') }}">
            <label>Search<input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order, waybill, buyer, or parcel code"></label>
            <label>Shipment status<select name="status"><option value="">All statuses</option>@foreach(\App\Models\Shipment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
            <label>Waybill status<select name="waybill_status"><option value="">All</option><option value="ready" @selected(($filters['waybill_status'] ?? '')==='ready')>Ready to Generate</option><option value="generated" @selected(($filters['waybill_status'] ?? '')==='generated')>Generated</option></select></label>
            <label>From<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label>To<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <button class="btn-primary" type="submit">Filter</button>
        </form>
        @if($shipments->count())
            <div class="waybills-table-wrap"><table class="waybills-table">
                <thead><tr><th>Order</th><th>Waybill</th><th>Buyer</th><th>Items</th><th>Order Date</th><th>Delivery Status</th><th>Waybill Status</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($shipments as $shipment)
                    @php
                        $sellerOrder=$shipment->sellerOrder;
                        $buyer=data_get($shipment->delivery_address,'full_name') ?: $sellerOrder->order->buyer_name;
                        $units=$sellerOrder->items->sum('quantity');
                        $eligible=$sellerOrder->status==='ready_to_ship';
                    @endphp
                    <tr>
                        <td><a class="waybill-order-link" href="{{ route('seller.orders.show',$sellerOrder) }}">{{ $sellerOrder->seller_order_number }}</a></td>
                        <td><strong class="waybill-ref">{{ $shipment->shipment_number }}</strong>@if($shipment->parcel_code)<small class="waybill-subtext">Scan {{ $shipment->parcel_code }}</small>@endif</td>
                        <td>{{ $buyer }}</td><td>{{ $units }} {{ Str::plural('item',$units) }}</td>
                        <td>{{ $sellerOrder->created_at?->format('M d, Y') }}</td>
                        <td><span class="waybill-pill">{{ str($shipment->status)->headline() }}</span></td>
                        <td>@if($shipment->waybill_generated_at)<span class="waybill-pill waybill-pill-generated">Generated</span>@elseif($eligible)<span class="waybill-pill waybill-pill-ready">Ready</span>@else<span class="waybill-pill waybill-pill-pending">Not Ready</span>@endif</td>
                        <td><div class="waybill-actions">
                            @if($shipment->waybill_generated_at)
                                <a class="waybill-action" href="{{ route('seller.waybills.show',$shipment) }}">View</a>
                                <a class="waybill-action" href="{{ route('seller.waybills.print',$shipment) }}">Print</a>
                                <a class="waybill-action" href="{{ route('seller.waybills.pdf',$shipment) }}">PDF</a>
                            @elseif($eligible)
                                <form method="POST" action="{{ route('seller.waybills.generate',$sellerOrder) }}" data-waybill-generate>@csrf<button class="waybill-action waybill-action-primary" type="submit">Generate Waybill</button></form>
                            @else
                                <span class="waybill-subtext">Not ready for generation</span>
                            @endif
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="waybill-pagination">{{ $shipments->links() }}</div>
        @else
            <div class="waybill-empty"><h2>{{ $filters ? 'No shipments match these filters.' : 'No waybills yet' }}</h2><p>Orders ready for shipment will appear here.</p><a class="btn-primary inline-flex items-center rounded-lg px-4 py-2 text-sm font-semibold" href="{{ route('seller.orders.index') }}">View Orders</a></div>
        @endif
    </section>
</div>
<script>
document.addEventListener('submit',event=>{
    const form=event.target.closest('[data-waybill-generate]');
    if(!form)return;
    const button=form.querySelector('button[type="submit"]');
    button.disabled=true;
    button.textContent='Generating...';
});
</script>
@endsection
