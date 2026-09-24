@extends('layouts.admin')
@section('title', 'Rider Activity')
@section('content')
<a href="{{ route('admin.logistics.riders.index') }}" class="text-sm font-semibold text-brand-700">&larr; Riders</a>
<h1 class="mt-3 text-2xl font-bold text-navy-900">{{ $rider->name }}</h1><p class="mt-1 text-sm text-slate-500">{{ $rider->email }}</p>
<div class="my-6 grid gap-4 sm:grid-cols-3">
<div class="card p-5"><p class="mb-2 text-sm text-slate-500">Status</p><x-admin.status-badge :status="$rider->is_active ? ($rider->riderProfile?->account_status ?? 'active') : 'inactive'" /></div>
<div class="card p-5"><p class="text-sm text-slate-500">Active Deliveries</p><p class="mt-2 text-2xl font-bold">{{ $rider->current_assignments }}</p></div>
<div class="card p-5"><p class="text-sm text-slate-500">Completed Deliveries</p><p class="mt-2 text-2xl font-bold">{{ $rider->completed_deliveries }}</p></div>
</div>
<section class="card mb-6 p-5">
<h2 class="mb-2 text-lg font-semibold text-navy-900">Current Delivery</h2>
@if($currentDelivery)
<a class="font-semibold text-brand-700" href="{{ route('admin.logistics.deliveries.show', $currentDelivery) }}">{{ $currentDelivery->shipment_number }}</a>
<x-admin.status-badge :status="$currentDelivery->status" />
@else
<p class="text-sm text-slate-500">No current delivery assigned.</p>
@endif
<p class="mt-3 text-sm text-slate-500">Last activity: {{ $rider->last_activity_at ? \Illuminate\Support\Carbon::parse($rider->last_activity_at)->format('M j, Y H:i') : 'No activity recorded' }}</p>
<p class="mt-1 text-sm text-slate-500">Availability: {{ ucwords(str_replace('_', ' ', $rider->riderProfile?->availability ?? 'Not recorded')) }}</p>
</section>
<section class="card overflow-hidden"><h2 class="p-5 text-lg font-semibold text-navy-900">Assigned Shipment Activity</h2>@include('admin.logistics.delivery-table', ['compact' => true])</section>
<div class="mt-4">{{ $shipments->links() }}</div>
<section class="card mt-6 p-5">
<h2 class="mb-4 text-lg font-semibold text-navy-900">Delivery Activity</h2>
<p class="mb-4 text-sm text-slate-500">Recorded shipment events performed by this rider.</p>
<ol class="space-y-4">
@forelse($events as $event)
<li class="border-l-2 border-brand-100 pl-4">
<div class="flex flex-wrap items-center gap-3"><x-admin.status-badge :status="$event->status" /><time class="text-sm text-slate-500">{{ $event->created_at?->format('M j, Y H:i') }}</time></div>
@if($event->shipment)<a class="mt-1 inline-block text-sm font-semibold text-brand-700" href="{{ route('admin.logistics.deliveries.show', $event->shipment) }}">{{ $event->shipment->shipment_number }}</a>@endif
<p class="mt-1 text-sm">{{ $event->note }}</p>
</li>
@empty
<li class="text-sm text-slate-500">No delivery activity recorded.</li>
@endforelse
</ol>
<div class="mt-4">{{ $events->links() }}</div>
</section>
@endsection
