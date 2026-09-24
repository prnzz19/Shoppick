@extends('layouts.admin')
@section('title', 'Deliveries')
@section('content')
<h1 class="text-2xl font-bold text-navy-900">Deliveries</h1>
<p class="mt-1 mb-6 text-sm text-slate-500">Read-only shipment monitoring. Provider details are not recorded by the current shipment system.</p>
<form method="GET" action="{{ route('admin.logistics.deliveries.index') }}" class="card mb-6 flex flex-wrap items-end gap-4 p-5">
<div class="min-w-0 flex-1"><label for="delivery-search" class="mb-1 block text-sm font-medium">Tracking or order</label><input id="delivery-search" name="q" value="{{ request('q') }}" maxlength="100" class="input w-full" placeholder="Tracking code or order number"></div>
<div><label for="delivery-status" class="mb-1 block text-sm font-medium">Status</label><select id="delivery-status" name="status" class="input"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<button class="btn-primary" type="submit">Filter</button><a href="{{ route('admin.logistics.deliveries.index') }}" class="btn-secondary">Clear</a>
</form>
<div class="card overflow-hidden">@include('admin.logistics.delivery-table', ['compact' => false])</div>
<div class="mt-4">{{ $shipments->links() }}</div>
@endsection
