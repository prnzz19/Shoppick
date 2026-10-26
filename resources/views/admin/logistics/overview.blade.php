@extends('layouts.admin')
@section('title', 'Logistics Overview')
@section('content')
<div class="admin-logistics-overview">
<div class="overview-page-header mb-7 flex flex-wrap items-start justify-between gap-5"><div><h1 class="text-3xl font-extrabold tracking-tight text-navy-900">Logistics Overview</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Monitor deliveries and rider activity. Parcel operations remain with Logistics and Riders.</p></div><div class="overview-date-card"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1z"/></svg><div><strong>{{ now()->format('M j, Y') }}</strong><span>{{ now()->format('l, g:i A') }}</span></div></div></div>
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
@php($kpiIcons = ['Logistics Staff' => 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8', 'Riders' => 'M5 17h14l-1-5H6l-1 5m2 0v2m10-2v2', 'Active Shipments' => 'M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3m0 9v9', 'Out for Delivery' => 'M3 6h12v11H3V6zm12 4h4l3 4v3h-7', 'Delivered' => 'M12 21a9 9 0 100-18 9 9 0 000 18m-4-9 2.5 2.5L16 9', 'Failed / Returned' => 'M12 21a9 9 0 100-18 9 9 0 000 18m3-12-6 6m0-6 6 6'])
@php($kpiTones = ['Logistics Staff' => 'overview-kpi-tone overview-kpi-tone-teal', 'Riders' => 'overview-kpi-tone overview-kpi-tone-blue', 'Active Shipments' => 'overview-kpi-tone overview-kpi-tone-purple', 'Out for Delivery' => 'overview-kpi-tone overview-kpi-tone-orange', 'Delivered' => 'overview-kpi-tone overview-kpi-tone-green', 'Failed / Returned' => 'overview-kpi-tone overview-kpi-tone-red'])
@foreach($summary as $label => $count)
<div class="overview-kpi card flex items-center gap-4 rounded-2xl border-slate-200 p-4 shadow-sm"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $kpiTones[$label] ?? 'overview-kpi-tone overview-kpi-tone-teal' }}"><svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $kpiIcons[$label] ?? 'M12 4v16m8-8H4' }}"/></svg></span><div class="min-w-0"><p class="text-sm font-semibold leading-5 text-slate-500">{{ $label }}</p><p class="mt-1 text-3xl font-extrabold tracking-tight text-navy-900">{{ number_format($count) }}</p></div></div>
@endforeach
</div>
<section class="card mb-6 p-5">
<h2 class="text-xl font-extrabold tracking-tight text-navy-900">Delivery Status</h2>
<p class="mt-1 text-sm text-slate-500">Current shipment statuses. Delivered includes buyer-confirmed completed deliveries.</p>
<div class="mt-4 flex flex-wrap gap-3">
@php($statusKeys = array_values(array_unique(array_merge(['assigned','assigned_to_rider','delivered','delivery_failed','exception','in_transit','out_for_delivery','picked_up','ready_for_pickup','returned','sorted'], \App\Models\Shipment::STATUSES))))
@forelse($statusKeys as $status)
@php($statusCount = (int) ($statuses[$status] ?? 0))
<a class="overview-status inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 transition hover:-translate-y-0.5 hover:border-brand-400 hover:bg-brand-50 {{ $statusCount === 0 ? 'overview-status-zero' : '' }}" href="{{ route('admin.logistics.deliveries.index', ['status' => $status]) }}"><x-admin.status-badge :status="$status" /><span class="text-sm font-extrabold text-navy-900">{{ number_format($statusCount) }}</span></a>
@empty
<p class="text-sm text-slate-500">No deliveries recorded yet.</p>
@endforelse
</div>
</section>
<section class="card overflow-hidden rounded-2xl border-slate-200 shadow-sm">
<div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5"><div><h2 class="text-xl font-extrabold tracking-tight text-navy-900">Recent Logistics Activity</h2><p class="mt-1 text-sm text-slate-500">Latest shipment updates across the marketplace.</p></div><a class="inline-flex items-center gap-2 text-sm font-extrabold text-brand-700 hover:underline" href="{{ route('admin.logistics.deliveries.index') }}">View all deliveries <span aria-hidden="true">&rarr;</span></a></div>
@include('admin.logistics.delivery-table', ['compact' => true])
</section>
<style>
.admin-logistics-overview .overview-kpi{height:112px;min-height:112px;min-width:0;flex:1 1 0}
.admin-logistics-overview .overview-page-header{padding-top:2px}
.admin-logistics-overview .overview-date-card{display:flex;align-items:center;gap:11px;border:1px solid #dce4ec;border-radius:12px;background:#fff;padding:10px 14px;color:#17213f;box-shadow:0 2px 8px #0f172a0d}
.admin-logistics-overview .overview-date-card svg{color:#0f766e}.admin-logistics-overview .overview-date-card strong,.admin-logistics-overview .overview-date-card span{display:block}.admin-logistics-overview .overview-date-card strong{font-size:13px;font-weight:800}.admin-logistics-overview .overview-date-card span{margin-top:2px;color:#64748b;font-size:11px}
.admin-logistics-overview .overview-kpi-tone{display:flex;height:52px;width:52px;flex:0 0 52px;align-items:center;justify-content:center;border-radius:14px}.admin-logistics-overview .overview-kpi-tone svg{height:26px;width:26px}
.admin-logistics-overview .overview-kpi>div{min-width:0}.admin-logistics-overview .overview-kpi>div>p:first-child{font-size:14px}.admin-logistics-overview .overview-kpi>div>p:last-child{font-size:30px;line-height:1.1}
.admin-logistics-overview .overview-kpi-tone-teal{background:#ecfeff;color:#0f9d91}.admin-logistics-overview .overview-kpi-tone-blue{background:#eff6ff;color:#2563eb}.admin-logistics-overview .overview-kpi-tone-purple{background:#f5f3ff;color:#7c3aed}.admin-logistics-overview .overview-kpi-tone-orange{background:#fff7ed;color:#ea580c}.admin-logistics-overview .overview-kpi-tone-green{background:#ecfdf3;color:#16a34a}.admin-logistics-overview .overview-kpi-tone-red{background:#fff1f2;color:#dc2626}
.admin-logistics-overview .table-th{background:#f8fafc;padding:14px 16px;font-size:11px;letter-spacing:.08em}
.admin-logistics-overview .table-td{padding:15px 16px;vertical-align:middle;font-size:14px}
.admin-logistics-overview table{min-width:980px}
.admin-logistics-overview [data-status]{border-radius:999px;padding:.4rem .65rem;font-size:12px;font-weight:800;line-height:1.1}
.admin-logistics-overview [data-status="assigned_to_rider"],.admin-logistics-overview [data-status="in_transit"]{background:#eff6ff;color:#2563eb}
.admin-logistics-overview [data-status="delivered"],.admin-logistics-overview [data-status="completed"]{background:#ecfdf3;color:#15803d}
.admin-logistics-overview [data-status="delivery_failed"],.admin-logistics-overview [data-status="returned"]{background:#fff1f2;color:#dc2626}
.admin-logistics-overview [data-status="out_for_delivery"]{background:#fff7ed;color:#c2410c}
.admin-logistics-overview [data-status="picked_up"]{background:#f5f3ff;color:#7c3aed}
.admin-logistics-overview [data-status="ready_for_pickup"]{background:#f0fdfa;color:#0f766e}
.admin-logistics-overview [data-status="assigned"],.admin-logistics-overview [data-status="exception"],.admin-logistics-overview [data-status="sorted"],.admin-logistics-overview [data-status="pickup_assigned"],.admin-logistics-overview [data-status="pickup_accepted"],.admin-logistics-overview [data-status="at_sorting_center"],.admin-logistics-overview [data-status="pickup_scheduled"],.admin-logistics-overview [data-status="at_hub"],.admin-logistics-overview [data-status="hub_transfer"],.admin-logistics-overview [data-status="delivery_attempted"]{background:#f1f5f9;color:#475569}
.admin-logistics-overview .overview-status-zero{opacity:.62}.admin-logistics-overview .overview-status-zero:hover{opacity:1}
@media(max-width:640px){.admin-logistics-overview .table-th,.admin-logistics-overview .table-td{padding-left:12px;padding-right:12px}.admin-logistics-overview h1{font-size:2rem}.admin-logistics-overview .overview-date-card{width:100%;justify-content:flex-start}}
</style>
</div>
@endsection
