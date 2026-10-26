@extends('layouts.logistics')

@section('title', 'Hub Transfers')

@section('content')
@php
    $labels = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'in_transit' => 'In Transit',
        'arrived' => 'Awaiting Scan',
        'completed' => 'Completed',
        'exception' => 'Exception',
    ];
    $tripPage = $trips instanceof \Illuminate\Contracts\Pagination\Paginator;
    $pendingTransfers = $transfers instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $transfers->getCollection()->filter(fn ($transfer) => $transfer->status === 'pending' && ! $transfer->tripItem)
        : collect();
    $hasCreateCandidates = $transferableShipments->isNotEmpty() || $pendingTransfers->isNotEmpty();
    $visibleTransfers = $transfers instanceof \Illuminate\Contracts\Pagination\Paginator
        ? $transfers->getCollection()->filter(fn ($transfer) => ! $transfer->tripItem)
        : collect();
    $hasRows = ($tripPage && $trips->count() > 0) || $visibleTransfers->isNotEmpty();
@endphp

<div class="hub-transfer-page">
    <header class="log-page-head">
        <div>
            <p class="log-eyebrow">LOGISTICS OPERATIONS</p>
            <h1>Hub Transfers</h1>
            <p>Manage parcel transfers between Sorting Centers.</p>
        </div>
        <a class="btn-outline" href="{{ route('logistics.sorting') }}">Back to Sorting</a>
    </header>

    <section class="log-kpis hub-transfer-summary" aria-label="Transfer status summary">
        <div class="log-kpi"><span class="hub-transfer-kpi-icon">&rarr;</span><div><b>{{ $summary->get('pending', 0) }}</b><p>Pending</p></div></div>
        <div class="log-kpi"><span class="hub-transfer-kpi-icon hub-transfer-kpi-icon--transit">&bull;</span><div><b>{{ $summary->get('in_transit', 0) }}</b><p>In Transit</p></div></div>
        <div class="log-kpi"><span class="hub-transfer-kpi-icon hub-transfer-kpi-icon--scan">&#10003;</span><div><b>{{ $summary->get('arrived', 0) }}</b><p>Awaiting Scan</p></div></div>
    </section>

    <section class="log-card transfer-create-card" aria-labelledby="create-transfer-heading">
        <div class="log-card-head">
            <div>
                <h2 id="create-transfer-heading">Create Transfer Trip</h2>
                <p>Select verified parcels from one origin route. One parcel creates a single transfer; multiple parcels share one manifest.</p>
            </div>
            <span class="transfer-rule-note">Provider-owned hubs only</span>
        </div>

        <form method="POST" action="{{ route('logistics.hub-transfers.store') }}" class="transfer-create-form" data-transfer-create>
            @csrf
            <fieldset class="transfer-choice-fieldset">
                <legend>Eligible sorted parcels</legend>
                <p class="transfer-help">Only parcels recorded at an origin hub, scanned, and sorted are listed.</p>
                <div class="transfer-choice-list">
                    @foreach($transferableShipments as $shipment)
                        <label class="transfer-choice" data-transfer-choice data-kind="shipment">
                            <input type="checkbox" name="shipment_ids[]" value="{{ $shipment->id }}">
                            <span>
                                <strong>{{ $shipment->parcel_code ?: $shipment->shipment_number }}</strong>
                                <small>{{ $shipment->currentHub?->name ?: 'Origin unavailable' }} &rarr; choose destination</small>
                                <small>{{ $shipment->store?->name ?: 'Shop unavailable' }}</small>
                            </span>
                        </label>
                    @endforeach
                    @foreach($pendingTransfers as $transfer)
                        <label class="transfer-choice" data-transfer-choice data-kind="transfer">
                            <input type="checkbox" name="transfer_ids[]" value="{{ $transfer->id }}">
                            <span>
                                <strong>{{ $transfer->shipment?->parcel_code ?: $transfer->shipment?->shipment_number }}</strong>
                                <small>{{ $transfer->originHub?->name }} &rarr; {{ $transfer->destinationHub?->name }}</small>
                                <small>Pending transfer ready to group</small>
                            </span>
                        </label>
                    @endforeach
                </div>
                @if(! $hasCreateCandidates)
                    <div class="transfer-empty-choice">
                        <strong>No eligible parcels are waiting.</strong>
                        <span>Complete parcel verification and sorting first.</span>
                        <a class="btn-outline btn-sm" href="{{ route('logistics.sorting') }}">Open Sorting</a>
                    </div>
                @endif
            </fieldset>

            <div class="transfer-create-controls">
                <label class="transfer-field">
                    <span>Destination Sorting Center</span>
                    <select class="input" name="destination_hub_id" data-transfer-destination @disabled(!$hasCreateCandidates)>
                        <option value="">Choose an active destination</option>
                        @foreach($hubs as $hub)
                            <option value="{{ $hub->id }}">{{ $hub->name }} ({{ $hub->code }})</option>
                        @endforeach
                    </select>
                    <small data-destination-help>Required for newly sorted parcels; existing pending transfers keep their approved route.</small>
                </label>
                <button class="btn-primary" type="submit" data-transfer-submit disabled>Create Transfer Trip</button>
            </div>
        </form>
    </section>

    <form method="GET" action="{{ route('logistics.hub-transfers') }}" class="log-card log-filters transfer-filters" aria-label="Filter transfer trips">
        <input class="input" type="search" name="q" value="{{ $data['q'] ?? '' }}" placeholder="Search trip, parcel, or tracking number" aria-label="Search transfers">
        <select class="input" name="status" aria-label="Filter transfer status">
            <option value="">All statuses</option>
            @foreach($labels as $value => $label)
                <option value="{{ $value }}" @selected(($data['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn-primary btn-sm" type="submit">Apply Filters</button>
        @if(($data['q'] ?? null) || ($data['status'] ?? null))
            <a class="btn-outline btn-sm" href="{{ route('logistics.hub-transfers') }}">Clear</a>
        @endif
    </form>

    <section class="log-card transfer-list-card" aria-labelledby="transfer-list-heading">
        <div class="log-card-head">
            <div>
                <h2 id="transfer-list-heading">Transfer Trips</h2>
                <p>Progressive actions appear only when the current status allows them.</p>
            </div>
            <span class="transfer-rule-note">Transport Drivers only</span>
        </div>
        <div class="transfer-list-wrap">
            <table class="log-table transfer-list-table">
                <thead><tr><th>Trip reference</th><th>Route</th><th>Parcels</th><th>Driver / vehicle</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                @if($tripPage)
                    @foreach($trips as $trip)
                        <tr>
                            <td><strong>{{ $trip->trip_reference }}</strong><small>Transfer Trip</small></td>
                            <td>{{ $trip->originHub?->name }} <span aria-hidden="true">&rarr;</span> {{ $trip->destinationHub?->name }}</td>
                            <td><strong>{{ $trip->items->count() }}</strong><small>manifest parcels</small></td>
                            <td>{{ $trip->driver?->name ?: 'Unassigned' }}<small>{{ $trip->vehicle?->code ?: 'No vehicle' }}</small></td>
                            <td><x-admin.status-badge :status="$trip->status" /><small>{{ $labels[$trip->status] ?? ucfirst($trip->status) }}</small></td>
                            <td>
                                <details class="transfer-details">
                                    <summary>View details</summary>
                                    <div class="transfer-detail-panel">
                                        <h3>Manifest</h3>
                                        <ul class="transfer-manifest">
                                            @foreach($trip->items as $item)
                                                <li><span>{{ $item->shipment?->parcel_code ?: $item->shipment?->shipment_number }}</span><small>{{ ucfirst($item->status) }}</small></li>
                                            @endforeach
                                        </ul>
                                        @if($trip->status === 'pending')
                                            <form method="POST" action="{{ route('logistics.hub-transfer-trips.assign', $trip) }}" class="transfer-action-form">@csrf @method('PUT')<label><span>Transport Driver</span><select class="input input-sm" name="transport_driver_id" required><option value="">Choose driver</option>@foreach($transportDrivers as $profile)<option value="{{ $profile->user_id }}">{{ $profile->user?->name }}</option>@endforeach</select></label><label><span>Vehicle</span><select class="input input-sm" name="vehicle_id"><option value="">No vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->code }}</option>@endforeach</select></label><button class="btn-primary btn-sm">Assign</button></form>
                                        @elseif($trip->status === 'assigned')
                                            <form method="POST" action="{{ route('logistics.hub-transfer-trips.dispatch', $trip) }}">@csrf<button class="btn-primary btn-sm">Dispatch Trip</button></form>
                                        @elseif($trip->status === 'in_transit')
                                            <form method="POST" action="{{ route('logistics.hub-transfer-trips.arrive', $trip) }}">@csrf<button class="btn-primary btn-sm">Record Arrival</button></form>
                                        @elseif($trip->status === 'arrived')
                                            <p class="transfer-detail-note">Arrival is recorded. Verify every parcel separately.</p>
                                            @foreach($trip->items->whereNotIn('status', ['received', 'exception']) as $item)
                                                <form method="POST" action="{{ route('logistics.hub-transfer-trips.receive', [$trip, $item->id]) }}" class="transfer-receipt-form">@csrf<input class="input input-sm" name="parcel_code" required placeholder="{{ $item->shipment?->parcel_code ?: 'Parcel code' }}" aria-label="Parcel code"><button class="btn-primary btn-sm">Verify Receipt</button></form>
                                            @endforeach
                                        @elseif($trip->status === 'completed')
                                            <p class="transfer-success">All manifest parcels resolved. Destination sorting may continue.</p>
                                        @endif
                                        @if(in_array($trip->status, ['pending', 'assigned', 'in_transit', 'arrived'], true))
                                            <form method="POST" action="{{ route('logistics.hub-transfer-trips.exception', $trip) }}" class="transfer-exception-form">@csrf<input class="input input-sm" name="reason" required maxlength="500" placeholder="Exception reason"><button class="btn-outline btn-sm">Report Exception</button></form>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                @endif
                @foreach($transfers as $transfer)
                    @if(! $transfer->tripItem)
                        <tr>
                            <td><strong>{{ $transfer->transfer_reference }}</strong><small>Single parcel transfer</small></td>
                            <td>{{ $transfer->originHub?->name }} <span aria-hidden="true">&rarr;</span> {{ $transfer->destinationHub?->name }}</td>
                            <td><strong>1</strong><small>{{ $transfer->shipment?->parcel_code ?: $transfer->shipment?->shipment_number }}</small></td>
                            <td>{{ $transfer->driver?->name ?: 'Unassigned' }}<small>{{ $transfer->vehicle?->code ?: 'No vehicle' }}</small></td>
                            <td><x-admin.status-badge :status="$transfer->status" /><small>{{ $labels[$transfer->status] ?? ucfirst($transfer->status) }}</small></td>
                            <td>
                                <details class="transfer-details">
                                    <summary>View details</summary>
                                    <div class="transfer-detail-panel">
                                        <h3>Parcel manifest</h3>
                                        <p class="transfer-detail-note">{{ $transfer->shipment?->parcel_code ?: $transfer->shipment?->shipment_number }}</p>
                                        @if($transfer->status === 'pending')
                                            <form method="POST" action="{{ route('logistics.hub-transfers.assign', $transfer) }}" class="transfer-action-form">@csrf @method('PUT')<label><span>Transport Driver</span><select class="input input-sm" name="transfer_driver_id" required><option value="">Choose driver</option>@foreach($drivers as $profile)<option value="{{ $profile->user_id }}">{{ $profile->user?->name }}</option>@endforeach</select></label><label><span>Vehicle</span><select class="input input-sm" name="vehicle_id"><option value="">No vehicle</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->code }}</option>@endforeach</select></label><button class="btn-primary btn-sm">Assign</button></form>
                                        @elseif($transfer->status === 'assigned')
                                            <form method="POST" action="{{ route('logistics.hub-transfers.dispatch', $transfer) }}">@csrf<button class="btn-primary btn-sm">Dispatch Transfer</button></form>
                                        @elseif($transfer->status === 'in_transit')
                                            <form method="POST" action="{{ route('logistics.hub-transfers.arrive', $transfer) }}">@csrf<button class="btn-primary btn-sm">Record Arrival</button></form>
                                        @elseif($transfer->status === 'arrived')
                                            <p class="transfer-detail-note">Verify the parcel code before destination sorting.</p>
                                            <form method="POST" action="{{ route('logistics.hub-transfers.receive', $transfer) }}" class="transfer-receipt-form">@csrf<input class="input input-sm" name="parcel_code" required placeholder="Scan parcel code" aria-label="Parcel code"><button class="btn-primary btn-sm">Verify Receipt</button></form>
                                        @elseif($transfer->status === 'completed')
                                            <p class="transfer-success">Destination receipt verified. Continue with destination sorting.</p>
                                        @endif
                                        @if(in_array($transfer->status, ['pending', 'assigned', 'in_transit', 'arrived'], true))
                                            <form method="POST" action="{{ route('logistics.hub-transfers.exception', $transfer) }}" class="transfer-exception-form">@csrf<input class="input input-sm" name="reason" required maxlength="500" placeholder="Exception reason"><button class="btn-outline btn-sm">Report Exception</button></form>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endif
                @endforeach
                @if(! $hasRows)
                    <tr><td colspan="6" class="log-empty"><strong>No Transfer Trips found.</strong><span>Create one from eligible sorted parcels or return to Sorting to prepare a parcel.</span><a class="btn-outline btn-sm log-empty-action" href="{{ route('logistics.sorting') }}">Open Sorting</a></td></tr>
                @endif
                </tbody>
            </table>
        </div>
        <div class="transfer-pagination">
            @if($tripPage)<span>{{ $trips->links() }}</span>@endif
            @if($transfers->hasPages())<span>{{ $transfers->links() }}</span>@endif
        </div>
    </section>
</div>

<script>
(() => {
    const form = document.querySelector('[data-transfer-create]');
    if (!form) return;
    const choices = [...form.querySelectorAll('[data-transfer-choice]')];
    const destination = form.querySelector('[data-transfer-destination]');
    const submit = form.querySelector('[data-transfer-submit]');
    const help = form.querySelector('[data-destination-help]');
    const sync = () => {
        const checked = choices.filter((choice) => choice.querySelector('input')?.checked);
        const kinds = new Set(checked.map((choice) => choice.dataset.kind));
        choices.forEach((choice) => {
            const input = choice.querySelector('input');
            if (!input) return;
            input.disabled = kinds.size > 0 && !kinds.has(choice.dataset.kind);
            choice.classList.toggle('is-selected', input.checked);
        });
        const transferMode = kinds.has('transfer');
        destination.disabled = !checked.length || transferMode;
        destination.required = checked.length > 0 && !transferMode;
        if (help) help.textContent = transferMode ? 'The existing transfer route is preserved for this manifest.' : 'Required for newly sorted parcels; existing pending transfers keep their approved route.';
        submit.disabled = checked.length === 0 || (!transferMode && !destination.value);
        submit.textContent = transferMode || checked.length > 1 ? 'Create Transfer Trip' : 'Create Transfer';
    };
    choices.forEach((choice) => choice.addEventListener('change', sync));
    destination?.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
