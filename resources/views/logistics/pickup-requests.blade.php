@extends('layouts.logistics')
@section('title', 'Pickup Requests')
@section('content')
<div class="log-page-head">
    <div>
        <p class="log-eyebrow">Seller collection queue</p>
        <h1>Pickup Requests</h1>
        <p>Automatic dispatch uses the Seller pickup location, provider ownership, Rider eligibility, workload, and fresh location data when coordinates are available.</p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-2 text-xs font-bold text-brand-700"><span class="h-2 w-2 rounded-full bg-brand-500"></span> Provider-scoped operations</span>
</div>

<div class="log-kpis mt-5 grid-cols-2 lg:grid-cols-4">
    <div class="log-kpi"><b>{{ $summary['waiting'] }}</b><p>Waiting for Assignment</p></div>
    <div class="log-kpi"><b>{{ $summary['assigned'] }}</b><p>Pickup Rider Assigned</p></div>
    <div class="log-kpi"><b>{{ $summary['picked_up'] }}</b><p>Picked Up</p></div>
    <div class="log-kpi border-orange-200 bg-orange-50"><b>{{ $summary['exceptions'] }}</b><p>Exceptions Requiring Attention</p></div>
</div>

<form class="log-filters" method="GET">
    <input class="input min-w-0 flex-1" name="q" value="{{ $data['q'] ?? '' }}" placeholder="Parcel code, shipment, or shop" aria-label="Search pickup requests">
    <select class="input" name="status" aria-label="Filter pickup status">
        <option value="">All pickup stages</option>
        <option value="ready_for_pickup" @selected(($data['status'] ?? '') === 'ready_for_pickup')>Waiting for assignment</option>
        <option value="pickup_assigned" @selected(($data['status'] ?? '') === 'pickup_assigned')>Assigned</option>
        <option value="pickup_accepted" @selected(($data['status'] ?? '') === 'pickup_accepted')>Accepted by Rider</option>
        <option value="picked_up" @selected(($data['status'] ?? '') === 'picked_up')>Picked up</option>
    </select>
    <button class="btn-primary" type="submit">Search</button>
    @if(($data['q'] ?? null) || ($data['status'] ?? null))<a class="btn-outline" href="{{ route('logistics.pickup-requests') }}">Clear</a>@endif
</form>

<div class="log-card mt-4 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
        <div><h2 class="text-sm font-black text-navy-900">Pickup dispatch queue</h2><p class="mt-1 text-xs text-slate-500">Normal cases are assigned automatically. Manual assignment remains available for authorized exceptions.</p></div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $shipments->total() }} parcels</span>
    </div>
    <div class="hidden overflow-x-auto md:block">
        <table class="log-table min-w-[980px]">
            <thead><tr><th>Parcel</th><th>Seller pickup</th><th>Items / ready</th><th>Status</th><th>Assignment</th><th>Confirmation</th></tr></thead>
            <tbody>
            @forelse($shipments as $shipment)
                @php
                    $assignmentEvent = $shipment->events->firstWhere('event_type', 'automatic_pickup_assignment') ?: $shipment->events->firstWhere('event_type', 'pickup_assigned');
                    $automatic = $assignmentEvent?->event_type === 'automatic_pickup_assignment';
                    $distance = data_get($assignmentEvent?->metadata, 'distance_km');
                @endphp
                <tr>
                    <td><strong class="block break-all text-brand-700">{{ $shipment->parcel_code ?: $shipment->shipment_number }}</strong><small>{{ $shipment->order?->order_number ?: 'Shipment '.$shipment->shipment_number }}</small></td>
                    <td><strong class="block">{{ $shipment->store?->name ?: 'Seller shop unavailable' }}</strong><small class="block max-w-xs break-words">{{ data_get($shipment->pickup_address, 'address', 'Pickup address unavailable') }}</small></td>
                    <td><strong class="block">{{ $shipment->sellerOrder?->items?->sum('quantity') ?: 0 }} items</strong><small>{{ $shipment->ready_at?->format('M d, g:i A') ?: 'Ready time unavailable' }}</small></td>
                    <td><x-admin.status-badge :status="$shipment->status" /></td>
                    <td>
                        @if($shipment->pickupRider)
                            <strong class="block">{{ $shipment->pickupRider->name }}</strong>
                            <small class="block">{{ $automatic ? ($distance !== null ? 'Automatic' : 'Automatic fallback · GPS unavailable') : 'Manual exception' }}@if($distance !== null) · {{ number_format((float) $distance, 2) }} km @endif</small>
                        @else
                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">Waiting for an available nearby Pickup Rider.</span>
                            <details class="mt-2"><summary class="cursor-pointer text-xs font-bold text-brand-700">Manual exception assignment</summary><form method="POST" action="{{ route('logistics.pickup.assign', $shipment) }}" class="mt-2 flex flex-wrap gap-2">@csrf<select class="input min-w-[180px] flex-1" name="rider_id" required><option value="">Eligible Rider</option>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->name }} · {{ $rider->riderProfile?->vehicle_type ?: 'Rider' }}</option>@endforeach</select><button class="btn-outline" type="submit">Assign manually</button></form></details>
                        @endif
                    </td>
                    <td>@if($shipment->status === 'picked_up')<span class="font-bold text-emerald-700">Pickup confirmed</span>@elseif($shipment->status === 'pickup_accepted')<span class="font-bold text-brand-700">Rider accepted</span>@else<span class="text-slate-500">Awaiting Rider confirmation</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="log-empty">No Pickup Requests found for this provider.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="space-y-3 p-3 md:hidden">
        @forelse($shipments as $shipment)
            @php
                $assignmentEvent = $shipment->events->firstWhere('event_type', 'automatic_pickup_assignment') ?: $shipment->events->firstWhere('event_type', 'pickup_assigned');
                $automatic = $assignmentEvent?->event_type === 'automatic_pickup_assignment';
                $distance = data_get($assignmentEvent?->metadata, 'distance_km');
            @endphp
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3"><div class="min-w-0"><strong class="block break-all text-brand-700">{{ $shipment->parcel_code ?: $shipment->shipment_number }}</strong><small class="mt-1 block text-slate-500">{{ $shipment->store?->name ?: 'Seller shop unavailable' }}</small></div><x-admin.status-badge :status="$shipment->status" /></div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-xs"><div><dt class="text-slate-400">Pickup location</dt><dd class="mt-1 break-words text-slate-700">{{ data_get($shipment->pickup_address, 'address', 'Unavailable') }}</dd></div><div><dt class="text-slate-400">Ready</dt><dd class="mt-1 text-slate-700">{{ $shipment->ready_at?->format('M d, g:i A') ?: 'Unavailable' }}</dd></div><div><dt class="text-slate-400">Assignment</dt><dd class="mt-1 text-slate-700">{{ $shipment->pickupRider?->name ?: 'Waiting' }} @if($shipment->pickupRider)<span class="block text-[10px] text-slate-500">{{ $distance !== null ? number_format((float) $distance, 2).' km · '.($automatic ? 'Automatic' : 'Manual') : ($automatic ? 'Automatic fallback · GPS unavailable' : 'Manual exception') }}</span>@endif</dd></div><div><dt class="text-slate-400">Confirmation</dt><dd class="mt-1 font-semibold {{ $shipment->status === 'picked_up' ? 'text-emerald-700' : 'text-slate-600' }}">{{ $shipment->status === 'picked_up' ? 'Pickup confirmed' : 'Awaiting Rider confirmation' }}</dd></div></dl>
                @if(!$shipment->pickupRider)<details class="mt-4"><summary class="cursor-pointer text-xs font-bold text-brand-700">Manual exception assignment</summary><form method="POST" action="{{ route('logistics.pickup.assign', $shipment) }}" class="mt-2 grid gap-2">@csrf<select class="input" name="rider_id" required><option value="">Eligible Rider</option>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->name }}</option>@endforeach</select><button class="btn-outline" type="submit">Assign manually</button></form></details>@endif
            </article>
        @empty
            <div class="log-empty">No Pickup Requests found for this provider.</div>
        @endforelse
    </div>
    <div class="border-t border-slate-100 px-4 py-3">{{ $shipments->links() }}</div>
</div>
@endsection
