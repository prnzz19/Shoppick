@extends('layouts.admin')
@section('title',$shop->name.' · Sales Report')
@section('content')
@include('admin.sales-reports.ui.styles')
<div class="sales-workspace" data-shop-sales-report>
    <a class="text-sm text-brand-700" href="{{ route('admin.sales-reports.index',array_intersect_key($filters['query'],array_flip(['range','from','to']))) }}">&larr; Sales Reports</a>
    <header class="report-header report-card mt-4">
        <div class="report-identity">
            <span class="report-avatar large" aria-hidden="true">@if($shop->logo)<img src="{{ asset('storage/'.$shop->logo) }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr($shop->name,0,1)) }}@endif</span>
            <div><p class="report-eyebrow">Shop Sales Report</p><h1>{{ $shop->name }}</h1><p class="report-subtitle">Seller: {{ $shop->user?->name ?? 'Unavailable seller' }}</p><div class="mt-2">@include('admin.sales-reports.ui.badge',['label'=>ucfirst($shop->status)]) <span class="report-note ml-2">Report Period: {{ $period }}</span></div></div>
        </div>
        @include('admin.sales-reports.ui.export',['exportRoute'=>'admin.shops.sales-report','exportParams'=>[$shop]+$filters['query']])
    </header>
    <form data-shop-report-filters method="GET" class="report-card report-filters" action="{{ route('admin.shops.sales-report',$shop) }}">
        <div class="report-filter-heading"><h2 class="report-section-title">Report filters</h2><span class="report-note">Choose your reporting period</span></div><div class="report-filter-grid report-shop-filters">
        <label class="text-xs font-semibold text-slate-600">Sales Date<select class="input mt-1" name="range">@foreach(\App\Services\ShopSalesReportService::RANGES as $key=>$label)<option value="{{ $key }}" @selected($filters['range']===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Order Status<select class="input mt-1" name="status"><option value="">All Statuses</option>@foreach(\App\Models\SellerOrder::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['query']['status'] ?? '')===$status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Search Orders<input class="input mt-1" name="q" maxlength="200" value="{{ $filters['query']['q'] ?? '' }}" placeholder="Order number or buyer"></label>
        <div data-report-custom class="report-custom" @if($filters['range']!=='custom') hidden @endif>
            <label class="text-xs font-semibold text-slate-600">From<input class="input mt-1" type="date" name="from" value="{{ $filters['query']['from'] ?? '' }}"></label>
            <label class="text-xs font-semibold text-slate-600">To<input class="input mt-1" type="date" name="to" value="{{ $filters['query']['to'] ?? '' }}"></label>
        </div>
        <button class="btn-primary self-end" type="submit">Apply</button>
@if(count(array_diff_key($filters['query'],['range'=>true])) || $filters['range']!=='all')<a class="text-sm text-brand-700" href="{{ route('admin.shops.sales-report',$shop) }}">Clear Filters</a>@endif
        @foreach($errors->all() as $error)<p class="w-full text-sm text-rose-600">{{ $error }}</p>@endforeach
        </div>
    </form>
    @include('admin.shops.sales-report.dashboard')
</div>
@endsection
@push('scripts')
<script>
const reportForm = document.querySelector('[data-shop-report-filters]');
function updateReportDates() {
    const custom = reportForm.elements.range.value === 'custom';
    document.querySelector('[data-report-custom]').hidden = !custom;
    ['from','to'].forEach(name => { reportForm.elements[name].disabled = !custom; reportForm.elements[name].required = custom; });
}
reportForm.elements.range.addEventListener('change', updateReportDates);
updateReportDates();
</script>
@endpush
