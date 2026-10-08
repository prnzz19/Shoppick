@extends('layouts.admin')
@section('title', $rider->name.' · Rider Details')
@section('content')
@include('admin.logistics.rider.styles')
@php($accountStatus = $rider->is_active ? ($rider->riderProfile?->account_status ?? 'active') : 'inactive')
<div class="rider-details" data-rider-details>
<a href="{{ route('admin.logistics.riders.index') }}" class="text-sm font-semibold text-brand-700">&larr; Riders</a>
<header class="rider-header rd-card">
    <span class="rd-avatar" aria-hidden="true">@if($rider->avatar)<img src="{{ $rider->avatar_url }}" alt="">@else{{ mb_strtoupper(mb_substr($rider->name,0,1)) }}@endif</span>
    <div class="min-w-0 flex-1"><p class="rd-eyebrow">Logistics Management / Rider Details</p><h1>{{ $rider->name }}</h1><p class="rd-muted rd-email">{{ $rider->email }}</p></div>
    @include('admin.logistics.rider.badge',['status'=>$accountStatus])
</header>
<div class="rd-metrics">
    @foreach(['Status'=>[$accountStatus,'Account status','status'],'Active Deliveries'=>[$rider->current_assignments,'Current pickup / delivery work','truck'],'Completed Deliveries'=>[$rider->completed_deliveries,'Delivered and completed','check'],'Total Assigned'=>[$totalAssigned,'Distinct pickup / delivery shipments','box']] as $label=>[$value,$note,$icon])
    <section class="rd-card rd-metric"><div class="rd-metric-heading"><span class="rd-icon">@include('admin.logistics.rider.icon')</span><h2 class="rd-eyebrow">{{ $label }}</h2></div><p class="rd-value">{{ $label==='Status' ? ucwords(str_replace('_',' ',$value)) : number_format($value) }}</p><p class="rd-muted">{{ $note }}</p></section>
    @endforeach
</div>
<div class="rd-columns">
    <section class="rd-card rd-current" aria-labelledby="current-delivery-heading">
        <div class="rd-section-heading"><h2 id="current-delivery-heading">Current Delivery</h2>@if($currentDelivery)@include('admin.logistics.rider.badge',['status'=>$currentDelivery->status])@endif</div>
        @if($currentDelivery)
            <p class="rd-tracking">{{ $currentDelivery->shipment_number }}</p>
            @if($currentDelivery->parcel_code)<p class="rd-muted">{{ $currentDelivery->parcel_code }}</p>@endif
            <dl class="rd-info mt-4"><div><dt>Order</dt><dd>{{ $currentDelivery->order?->order_number ?? 'Not recorded' }}</dd></div><div><dt>Shop</dt><dd>{{ $currentDelivery->store?->name ?? 'Not recorded' }}</dd></div><div><dt>Last Update</dt><dd>{{ $currentDelivery->updated_at?->format('M j, Y H:i') ?? 'Not recorded' }}</dd></div></dl>
            <a class="rd-action mt-4" href="{{ route('admin.logistics.deliveries.show',$currentDelivery) }}">View Delivery <span aria-hidden="true">→</span></a>
        @else
            <div class="rd-empty"><strong>No active delivery</strong><p class="rd-muted mt-1">This rider currently has no assigned active delivery.</p>@if($rider->current_assignments)<p class="rd-muted mt-1">Current pickup work appears in Assigned Shipment Activity below.</p>@endif</div>
        @endif
    </section>
    <section class="rd-card" aria-labelledby="rider-info-heading">
        <h2 id="rider-info-heading">Rider Information</h2>
        <dl class="rd-info mt-4">
            <div><dt>Rider ID</dt><dd>#{{ $rider->id }}</dd></div>
            @if($rider->phone)<div><dt>Phone</dt><dd>{{ $rider->phone }}</dd></div>@endif
            @if($rider->riderProfile?->vehicle_type)<div><dt>Vehicle</dt><dd>{{ ucwords(str_replace('_',' ',$rider->riderProfile->vehicle_type)) }}</dd></div>@endif
            @if($rider->riderProfile?->plate_number)<div><dt>Plate Number</dt><dd>{{ $rider->riderProfile->plate_number }}</dd></div>@endif
            <div><dt>Availability</dt><dd>{{ ucwords(str_replace('_',' ',$rider->riderProfile?->availability ?? 'Not recorded')) }}</dd></div>
            <div><dt>Last Activity</dt><dd>{{ $rider->last_activity_at ? \Illuminate\Support\Carbon::parse($rider->last_activity_at)->format('M j, Y H:i') : 'No activity recorded' }}</dd></div>
        </dl>
    </section>
</div>
<section class="rd-card rd-section" aria-labelledby="assigned-heading">
    <div class="rd-section-heading"><div><h2 id="assigned-heading">Assigned Shipment Activity</h2><p class="rd-muted mt-1">Pickup and delivery assignments for this rider</p></div><span class="rd-muted">{{ number_format($totalAssigned) }} total assigned</span></div>
    <form data-rider-filters action="{{ route('admin.logistics.riders.show',$rider) }}" method="GET" class="rd-filters">
        <label class="flex-1">Search shipments<input class="input" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tracking, order or shop"></label>
        <label>Status<select class="input" name="status"><option value="">All Statuses</option>@foreach(\App\Models\Shipment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
        <input type="hidden" name="activity_page" value="{{ $events->currentPage() }}">
        <a data-rider-clear href="{{ route('admin.logistics.riders.show',['rider'=>$rider->id,'activity_page'=>$events->currentPage()]) }}" class="text-sm font-semibold text-brand-700" @if(empty($filters['q']) && empty($filters['status'])) hidden @endif>Clear Filters</a>
        <noscript><button class="btn-primary">Apply Filters</button></noscript>
    </form>
    <div class="rd-feedback"><span data-rider-loading role="status" hidden>Updating rider activity…</span><span data-rider-error role="alert" hidden></span><button data-rider-retry class="rd-action" type="button" hidden>Retry</button></div>
    <div data-rider-shipments tabindex="-1" role="region" aria-label="Assigned shipment results">@include('admin.logistics.rider.shipments')</div>
</section>
<section class="rd-card rd-section" aria-labelledby="activity-heading"><div class="rd-section-heading"><div><h2 id="activity-heading">Delivery Activity</h2><p class="rd-muted mt-1">Recorded shipment events performed by this rider · Latest first</p></div></div><div data-rider-activity tabindex="-1" role="region" aria-label="Rider delivery activity">@include('admin.logistics.rider.activity')</div></section>
</div>
@endsection
@push('scripts')
@include('admin.logistics.rider.script')
@endpush
