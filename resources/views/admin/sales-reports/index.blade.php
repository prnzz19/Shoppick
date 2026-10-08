@extends('layouts.admin')
@section('title','Sales Reports')
@section('content')
@include('admin.sales-reports.ui.styles')
<div class="sales-workspace" data-admin-sales-reports data-filter-url="{{ route('admin.sales-reports.filter') }}">
    <header class="report-header">
        <div><p class="report-eyebrow">Seller Management / Sales Reports</p><h1 class="text-2xl font-bold text-navy-900">Sales Reports</h1><p class="report-subtitle">Monitor shop sales, orders, and performance.</p></div>
        <div data-sales-exports>@include('admin.sales-reports.exports')</div>
    </header>
    <form data-sales-form method="GET" action="{{ route('admin.sales-reports.index') }}" class="report-card report-filters">
        <div class="report-filter-heading"><h2 class="report-section-title">Filters</h2><div class="flex items-center gap-3"><span class="report-note">Updates automatically</span><a data-sales-clear data-sales-clear-control href="{{ route('admin.sales-reports.index') }}" class="text-sm text-brand-700" @if(count($filters['query'])===1 && $filters['range']==='all') hidden @endif>Clear Filters</a></div></div><div class="report-filter-grid">
        <label class="text-xs font-semibold text-slate-600">Shop<select name="shop" class="input mt-1"><option value="">All Shops</option>@foreach($shopOptions as $option)<option value="{{ $option->id }}" @selected(($filters['query']['shop'] ?? '')==$option->id)>{{ $option->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Date Range<select name="range" class="input mt-1">@foreach(\App\Services\ShopSalesReportService::RANGES as $key=>$label)<option value="{{ $key }}" @selected($filters['range']===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Shop Status<select name="shop_status" class="input mt-1"><option value="">All Shop Statuses</option>@foreach(['active'=>'Active','suspended'=>'Suspended'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['query']['shop_status'] ?? '')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Performance<select name="performance" class="input mt-1"><option value="">All Performance</option>@foreach(\App\Services\ShopSalesReportService::PERFORMANCE as $label)<option @selected(($filters['query']['performance'] ?? '')===$label)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Sort<select name="sort" class="input mt-1">@foreach(\App\Services\MarketplaceSalesReportService::SORTS as $key=>$label)<option value="{{ $key }}" @selected(($filters['query']['sort'] ?? 'sales_desc')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="report-search">Search<input class="input mt-1" name="q" maxlength="200" placeholder="Shop, seller, or email" value="{{ $filters['query']['q'] ?? '' }}"></label>
        <div data-sales-custom class="report-custom" @if($filters['range']!=='custom') hidden @endif>
            @foreach(['from'=>'From','to'=>'To'] as $name=>$label)<label class="text-xs font-semibold text-slate-600">{{ $label }}<input class="input mt-1" type="date" name="{{ $name }}" value="{{ $filters['query'][$name] ?? '' }}" @disabled($filters['range']!=='custom') @required($filters['range']==='custom')></label>@endforeach
        </div>
        <noscript><button class="btn-primary">Apply Filters</button></noscript>
        </div>
    </form>
    <div data-sales-summary>@include('admin.sales-reports.summary')</div>
    <div class="my-4 flex flex-wrap items-center gap-3"><div data-sales-period>@include('admin.sales-reports.period')</div><span data-sales-loading hidden role="status" class="text-sm text-brand-700">Updating reports…</span><span data-sales-error hidden role="alert" class="text-sm text-rose-700"></span><button data-sales-retry hidden type="button" class="btn-outline">Retry</button></div>
    <div data-sales-table tabindex="-1" role="region" aria-label="Shop sales results" aria-busy="false">@include('admin.sales-reports.table')</div>
    <div data-sales-pagination class="mt-4">@include('admin.sales-reports.pagination')</div>
</div>
@endsection
