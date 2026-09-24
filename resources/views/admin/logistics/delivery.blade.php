@extends('layouts.admin')
@section('title', 'Delivery Details')
@section('content')
<a class="text-sm font-semibold text-brand-700" href="{{ route('admin.logistics.deliveries.index') }}">&larr; Deliveries</a>
<div class="mt-3 mb-6"><h1 class="text-2xl font-bold text-navy-900">Delivery Details</h1><p class="mt-1 text-sm text-slate-500">Read-only delivery record</p></div>
<section class="card p-5">
<div class="mb-5"><x-admin.status-badge :status="$shipment->status" /></div>
<dl class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@foreach([
'Tracking number' => $shipment->shipment_number,
'Parcel code' => $shipment->parcel_code,
'Order' => $shipment->order?->order_number,
'Order ID' => $shipment->order_id,
'Seller order' => $shipment->sellerOrder?->seller_order_number,
'Shop' => $shipment->store?->name,
'Seller' => $shipment->store?->user?->name,
'Buyer' => $shipment->order?->user?->name ?? $shipment->order?->buyer_name,
'Logistics provider' => null,
'Assigned delivery rider' => $shipment->rider?->name,
'Pickup rider' => $shipment->pickupRider?->name,
'Current facility' => $shipment->currentHub?->name,
'Failure reason' => $shipment->failure_reason
] as $label => $value)
<div><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium text-navy-900">{{ $value ?? 'Not recorded' }}</dd></div>
@endforeach
</dl>
</section>
<section class="card mt-6 p-5"><h2 class="mb-4 text-lg font-semibold text-navy-900">Delivery Timeline</h2>
<ol class="space-y-5 border-l border-slate-200 pl-5">
@forelse($shipment->events as $event)
<li><div class="flex flex-wrap items-center gap-3"><x-admin.status-badge :status="$event->status" /><time class="text-sm text-slate-500">{{ $event->created_at?->format('M j, Y H:i') }}</time></div>
<p class="mt-1 text-sm">{{ $event->note }}</p><p class="mt-1 text-xs text-slate-500">{{ $event->location }}@if($event->actor) &middot; {{ $event->actor->name }}@endif</p></li>
@empty
<li class="text-sm text-slate-500">No delivery events recorded yet.</li>
@endforelse
</ol></section>
<section class="card mt-6 p-5"><h2 class="mb-4 text-lg font-semibold text-navy-900">Recorded Timestamps</h2>
<dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
@foreach(['created_at','ready_at','assigned_at','pickup_accepted_at','picked_up_at','pickup_arrived_at','received_at','parcel_scanned_at','sorted_at','delivery_assigned_at','delivery_accepted_at','estimated_delivery_at','delivered_at','returned_at','updated_at'] as $field)
@if($shipment->$field)<div><dt class="text-sm text-slate-500">{{ ucwords(str_replace('_', ' ', $field)) }}</dt><dd class="mt-1 text-sm font-medium">{{ $shipment->$field->format('M j, Y H:i') }}</dd></div>@endif
@endforeach
</dl></section>
<section class="card mt-6 p-5">
<h2 class="mb-4 text-lg font-semibold text-navy-900">Proof of Delivery</h2>
@if($proof = $shipment->proofOfDelivery)
<dl class="grid gap-4 sm:grid-cols-2">
<div><dt class="text-sm text-slate-500">Recipient</dt><dd class="mt-1 font-medium">{{ $proof->recipient_name }}</dd></div>
<div><dt class="text-sm text-slate-500">Submitted by</dt><dd class="mt-1 font-medium">{{ $proof->submitter?->name ?? 'Not recorded' }}</dd></div>
<div><dt class="text-sm text-slate-500">Submitted at</dt><dd class="mt-1">{{ $proof->submitted_at?->format('M j, Y H:i') ?? 'Not recorded' }}</dd></div>
<div><dt class="text-sm text-slate-500">Review status</dt><dd class="mt-1"><x-admin.status-badge :status="$proof->status" /></dd></div>
</dl>
@if($proof->notes)<p class="mt-4 text-sm">{{ $proof->notes }}</p>@endif
@if($proof->photo_path)<a href="{{ route('admin.logistics.deliveries.proof', $shipment) }}" target="_blank" rel="noopener" class="btn-primary mt-4">View proof of delivery</a>@endif
@else
<p class="text-sm text-slate-500">No proof of delivery submitted yet.</p>
@endif
</section>
@endsection
