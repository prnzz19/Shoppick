@extends('layouts.logistics')
@section('title','Incoming Parcels')
@section('content')
@php
    $selectedArrival = $data['arrival'] ?? null;
    $selectedRider = $data['rider_id'] ?? null;
    $selectedHub = $data['hub_id'] ?? null;
@endphp
<div class="incoming-page">
    <div class="log-page-head incoming-page-head">
        <div>
            <p class="log-eyebrow">LOGISTICS OPERATIONS</p>
            <h1>Incoming Parcels</h1>
            <p>Track pickup arrivals and verify each parcel before it enters the Sorting Center workflow.</p>
        </div>
        <div class="incoming-page-note"><span class="incoming-note-dot"></span><span>Provider-scoped queue</span></div>
    </div>

    <section class="log-kpis incoming-kpis" aria-label="Incoming parcel summary">
        <article class="log-kpi incoming-kpi"><span class="incoming-kpi-icon incoming-kpi-icon--total" aria-hidden="true">#</span><div><b>{{ number_format($summary['expected']) }}</b><p>Expected Parcels</p><small>Picked up or received</small></div></article>
        <article class="log-kpi incoming-kpi"><span class="incoming-kpi-icon incoming-kpi-icon--transit" aria-hidden="true">&#8594;</span><div><b>{{ number_format($summary['in_transit']) }}</b><p>In Transit</p><small>Awaiting Rider arrival</small></div></article>
        <article class="log-kpi incoming-kpi"><span class="incoming-kpi-icon incoming-kpi-icon--ready" aria-hidden="true">&#10003;</span><div><b>{{ number_format($summary['ready']) }}</b><p>Ready for Arrival Verification</p><small>Rider has arrived</small></div></article>
        <article class="log-kpi incoming-kpi"><span class="incoming-kpi-icon incoming-kpi-icon--received" aria-hidden="true">&#10004;</span><div><b>{{ number_format($summary['received']) }}</b><p>Received Parcels</p><small>At Sorting Center</small></div></article>
    </section>

    <form method="GET" action="{{ route('logistics.incoming') }}" class="log-card log-filters incoming-filters" aria-label="Filter incoming parcels">
        <label class="incoming-filter-field incoming-filter-search"><span>Search</span><input class="input" type="search" name="q" value="{{ $data['q'] ?? '' }}" placeholder="Parcel, tracking, shop, or order" autocomplete="off"></label>
        <label class="incoming-filter-field"><span>Arrival status</span><select class="input" name="arrival"><option value="">All arrival states</option><option value="in_transit" @selected($selectedArrival === 'in_transit')>In transit</option><option value="ready" @selected($selectedArrival === 'ready')>Ready for verification</option><option value="received" @selected($selectedArrival === 'received')>Received</option></select></label>
        <label class="incoming-filter-field"><span>Pickup Rider</span><select class="input" name="rider_id"><option value="">All Pickup Riders</option>@foreach($riders as $rider)<option value="{{ $rider->user_id }}" @selected((string) $selectedRider === (string) $rider->user_id)>{{ $rider->user?->name ?? 'Rider #'.$rider->user_id }}</option>@endforeach</select></label>
        <label class="incoming-filter-field"><span>Sorting Center / Hub</span><select class="input" name="hub_id"><option value="">All Hubs</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((string) $selectedHub === (string) $hub->id)>{{ $hub->name }}</option>@endforeach</select></label>
        <div class="incoming-filter-actions"><button class="btn-primary" type="submit">Apply filters</button><a class="btn-outline" href="{{ route('logistics.incoming') }}">Clear</a></div>
    </form>

    <div class="incoming-status-guide" aria-label="Arrival status guide"><span><i class="incoming-status-dot incoming-status-dot--transit"></i>In transit: waiting for Rider arrival</span><span><i class="incoming-status-dot incoming-status-dot--ready"></i>Ready: arrival recorded by Rider</span><span><i class="incoming-status-dot incoming-status-dot--received"></i>Received: staff can confirm the parcel code</span></div>

    <section class="log-card incoming-queue" aria-labelledby="incoming-queue-title">
        <div class="incoming-queue-head"><div><h2 id="incoming-queue-title">Parcel arrival queue</h2><p>{{ number_format($shipments->total()) }} matching parcel{{ $shipments->total() === 1 ? '' : 's' }}</p></div><span class="incoming-queue-scope">SHOPPICK Logistics</span></div>
        <div class="incoming-table-wrap">
            <table class="log-table incoming-table"><thead><tr><th>Parcel / Order</th><th>Seller / Shop</th><th>Pickup Rider</th><th>Hub</th><th>Status</th><th>Arrival / Scan</th><th>Action</th></tr></thead><tbody>
            @forelse($shipments as $shipment)
                @php
                    $pickupRider = $shipment->pickupRider;
                    $profile = $pickupRider?->riderProfile;
                    $vehicle = $profile?->vehicle;
                    $vehicleLabel = $vehicle?->code ?: ($profile?->vehicle_type ?: 'Vehicle not recorded');
                    $vehiclePlate = $vehicle?->plate_number ?: $profile?->plate_number;
                    $hub = $shipment->currentHub ?: $shipment->destinationHub;
                    $statusLabel = $shipment->status === 'at_sorting_center' ? 'At Sorting Center' : 'Picked Up';
                    $arrivalLabel = $shipment->received_at ? 'Received '.$shipment->received_at->format('M d, Y g:i A') : ($shipment->pickup_arrived_at ? 'Ready for verification' : 'En route');
                @endphp
                <tr>
                    <td><strong class="incoming-code">{{ $shipment->parcel_code ?: $shipment->shipment_number }}</strong><small>{{ $shipment->shipment_number }}{{ $shipment->order?->order_number ? ' | '.$shipment->order->order_number : '' }}</small><details class="incoming-details"><summary>View details</summary><div class="incoming-details-grid"><span>Pickup</span><b>{{ $shipment->picked_up_at?->format('M d, Y g:i A') ?: 'Not recorded' }}</b><span>Arrival</span><b>{{ $shipment->pickup_arrived_at?->format('M d, Y g:i A') ?: 'Not recorded' }}</b><span>Received</span><b>{{ $shipment->received_at?->format('M d, Y g:i A') ?: 'Not recorded' }}</b><span>Scan</span><b>{{ $shipment->parcel_scanned_at?->format('M d, Y g:i A') ?: 'Pending' }}</b></div>@if($shipment->events->isNotEmpty())<ul class="incoming-event-list">@foreach($shipment->events->take(4) as $event)<li><b>{{ str($event->status)->headline() }}</b><span>{{ $event->created_at?->format('M d, Y g:i A') }}</span></li>@endforeach</ul>@endif</details></td>
                    <td><strong>{{ $shipment->store?->name ?: 'Shop unavailable' }}</strong><small>Seller shipment</small></td>
                    <td><strong>{{ $pickupRider?->name ?: 'Not assigned' }}</strong><small>{{ $vehicleLabel }}{{ $vehiclePlate ? ' | '. $vehiclePlate : '' }}</small></td>
                    <td><strong>{{ $hub?->name ?: 'Not recorded' }}</strong><small>{{ $hub?->province ?: 'Sorting Center pending' }}</small></td>
                    <td><span class="incoming-status incoming-status--{{ $shipment->status === 'at_sorting_center' ? 'received' : 'transit' }}">{{ $statusLabel }}</span></td>
                    <td><strong>{{ $arrivalLabel }}</strong><small>{{ $shipment->parcel_scanned_at ? 'Parcel code confirmed' : ($shipment->received_at ? 'Awaiting parcel code' : 'Arrival evidence pending') }}</small></td>
                    <td class="incoming-action-cell">@if($shipment->status === 'picked_up' && $shipment->pickup_arrived_at)<form method="POST" action="{{ route('logistics.incoming.receive',$shipment) }}">@csrf<button class="btn-primary incoming-action" type="submit">Receive Parcel</button></form>@elseif($shipment->status === 'picked_up')<span class="incoming-action-note">Waiting for Rider arrival</span>@elseif($shipment->status === 'at_sorting_center' && !$shipment->parcel_scanned_at)<form method="POST" action="{{ route('logistics.incoming.scan',$shipment) }}" class="incoming-scan-form">@csrf<label class="sr-only" for="parcel-code-{{ $shipment->id }}">Confirm parcel code for {{ $shipment->parcel_code ?: $shipment->shipment_number }}</label><input id="parcel-code-{{ $shipment->id }}" class="input" name="parcel_code" placeholder="Enter code" required><button class="btn-primary incoming-action" type="submit">Confirm code</button></form>@elseif($shipment->parcel_scanned_at)<span class="incoming-confirmed">&#10003; Code confirmed</span>@else<span class="incoming-action-note">Awaiting next workflow step</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="incoming-empty"><strong>No incoming parcels match these filters.</strong><span>Try clearing a filter or wait for a Pickup Rider to record arrival.</span></td></tr>
            @endforelse
            </tbody></table>
        </div>
        <div class="incoming-mobile-list">
            @forelse($shipments as $shipment)
                @php
                    $pickupRider = $shipment->pickupRider;
                    $profile = $pickupRider?->riderProfile;
                    $vehicle = $profile?->vehicle;
                    $vehicleLabel = $vehicle?->code ?: ($profile?->vehicle_type ?: 'Vehicle not recorded');
                    $vehiclePlate = $vehicle?->plate_number ?: $profile?->plate_number;
                    $hub = $shipment->currentHub ?: $shipment->destinationHub;
                    $statusLabel = $shipment->status === 'at_sorting_center' ? 'At Sorting Center' : 'Picked Up';
                    $arrivalLabel = $shipment->received_at ? 'Received '.$shipment->received_at->format('M d, Y g:i A') : ($shipment->pickup_arrived_at ? 'Ready for verification' : 'En route');
                @endphp
                <article class="incoming-mobile-card"><div class="incoming-mobile-head"><div><strong class="incoming-code">{{ $shipment->parcel_code ?: $shipment->shipment_number }}</strong><small>{{ $shipment->store?->name ?: 'Shop unavailable' }}</small></div><span class="incoming-status incoming-status--{{ $shipment->status === 'at_sorting_center' ? 'received' : 'transit' }}">{{ $statusLabel }}</span></div><dl class="incoming-mobile-details"><div><dt>Pickup Rider</dt><dd>{{ $pickupRider?->name ?: 'Not assigned' }}</dd></div><div><dt>Vehicle</dt><dd>{{ $vehicleLabel }}{{ $vehiclePlate ? ' | '. $vehiclePlate : '' }}</dd></div><div><dt>Hub</dt><dd>{{ $hub?->name ?: 'Not recorded' }}</dd></div><div><dt>Arrival</dt><dd>{{ $arrivalLabel }}</dd></div><div><dt>Scan</dt><dd>{{ $shipment->parcel_scanned_at ? 'Code confirmed' : ($shipment->received_at ? 'Awaiting code' : 'Arrival pending') }}</dd></div></dl><details class="incoming-details"><summary>View parcel history</summary>@if($shipment->events->isNotEmpty())<ul class="incoming-event-list">@foreach($shipment->events->take(4) as $event)<li><b>{{ str($event->status)->headline() }}</b><span>{{ $event->created_at?->format('M d, Y g:i A') }}</span></li>@endforeach</ul>@else<p>No parcel events recorded.</p>@endif</details><div class="incoming-mobile-action">@if($shipment->status === 'picked_up' && $shipment->pickup_arrived_at)<form method="POST" action="{{ route('logistics.incoming.receive',$shipment) }}">@csrf<button class="btn-primary incoming-action" type="submit">Receive Parcel</button></form>@elseif($shipment->status === 'picked_up')<span class="incoming-action-note">Waiting for Rider arrival</span>@elseif($shipment->status === 'at_sorting_center' && !$shipment->parcel_scanned_at)<form method="POST" action="{{ route('logistics.incoming.scan',$shipment) }}" class="incoming-scan-form">@csrf<label class="sr-only" for="mobile-parcel-code-{{ $shipment->id }}">Confirm parcel code</label><input id="mobile-parcel-code-{{ $shipment->id }}" class="input" name="parcel_code" placeholder="Enter parcel code" required><button class="btn-primary incoming-action" type="submit">Confirm code</button></form>@elseif($shipment->parcel_scanned_at)<span class="incoming-confirmed">&#10003; Code confirmed</span>@else<span class="incoming-action-note">Awaiting next workflow step</span>@endif</div></article>
            @empty
                <p class="incoming-empty"><strong>No incoming parcels match these filters.</strong><span>Try clearing a filter or wait for Rider arrival.</span></p>
            @endforelse
        </div>
        @if($shipments->hasPages())<div class="incoming-pagination">{{ $shipments->links() }}</div>@endif
    </section>
</div>
@endsection
