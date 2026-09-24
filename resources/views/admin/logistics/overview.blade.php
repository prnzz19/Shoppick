@extends('layouts.admin')
@section('title', 'Logistics Overview')
@section('content')
<div class="mb-6"><h1 class="text-2xl font-bold text-navy-900">Logistics Overview</h1><p class="mt-1 text-sm text-slate-500">Monitor deliveries and rider activity. Parcel operations remain with Logistics and Riders.</p></div>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
@foreach($summary as $label => $count)
<div class="card p-5"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold text-navy-900">{{ number_format($count) }}</p></div>
@endforeach
</div>
<section class="card mb-6 p-5">
<h2 class="text-lg font-semibold text-navy-900">Delivery Status</h2>
<p class="mt-1 text-sm text-slate-500">Current shipment statuses. Delivered includes buyer-confirmed completed deliveries.</p>
<div class="mt-4 flex flex-wrap gap-3">
@forelse($statuses as $status => $count)
<a class="inline-flex items-center gap-3 rounded-lg border border-slate-200 p-3 hover:border-brand-500" href="{{ route('admin.logistics.deliveries.index', ['status' => $status]) }}"><x-admin.status-badge :status="$status" /><span class="text-sm font-semibold">{{ number_format($count) }}</span></a>
@empty
<p class="text-sm text-slate-500">No deliveries recorded yet.</p>
@endforelse
</div>
</section>
<section class="card overflow-hidden">
<div class="flex items-center justify-between gap-4 p-5"><h2 class="text-lg font-semibold text-navy-900">Recent Logistics Activity</h2><a class="text-sm font-semibold text-brand-700" href="{{ route('admin.logistics.deliveries.index') }}">View all deliveries &rarr;</a></div>
@include('admin.logistics.delivery-table', ['compact' => true])
</section>
@endsection
