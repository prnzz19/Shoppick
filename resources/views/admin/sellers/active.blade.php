@extends('layouts.admin')
@section('title','Sellers')
@section('content')
<div data-admin-sellers data-filter-url="{{ route('admin.sellers.filter') }}">
<h1 class="text-2xl font-bold text-navy-900">Sellers</h1>
<p class="mt-1 text-sm text-slate-500">Monitor seller performance. Sales and orders include completed seller orders only.</p>
<div data-sellers-tabs>@include('admin.sellers.partials.tabs')</div>
<div data-sellers-summary>@include('admin.sellers.partials.summary')</div>
<form data-sellers-form method="GET" action="{{ route('admin.sellers.index') }}" class="card my-4 space-y-3 p-4">
<input type="hidden" name="tab" value="{{ $archived ? 'archived' : '' }}">
<label class="block"><span class="sr-only">Search sellers</span><input name="q" maxlength="200" class="input" value="{{ request('q') }}" placeholder="Search seller, email, or shop..."></label>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
<label class="text-xs font-semibold text-slate-600">Shop<select name="shop" class="input mt-1"><option value="">All Shops</option>@foreach($shops as $shop)<option value="{{ $shop->id }}" @selected(request('shop') == $shop->id)>{{ $shop->name }}</option>@endforeach</select></label>
<label class="text-xs font-semibold text-slate-600">Sales Date<select id="seller-sales-range" name="range" class="input mt-1">@foreach($ranges as $key=>$label)<option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>@endforeach</select></label>
<label class="text-xs font-semibold text-slate-600">Seller Status<select name="status" class="input mt-1"><option value="">All Sellers</option>@foreach(['approved','suspended'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
<label class="text-xs font-semibold text-slate-600">Sort<select name="sort" class="input mt-1">@foreach($sorts as $key=>$label)<option value="{{ $key }}" @selected((request('sort') ?: 'newest') === $key)>{{ $label }}</option>@endforeach</select></label>
</div>
<div id="seller-custom-dates" @if($range !== 'custom') hidden @endif><div class="flex flex-wrap gap-3">
<label class="text-xs font-semibold text-slate-600">From<input type="date" name="from" value="{{ request('from') }}" class="input mt-1"></label>
<label class="text-xs font-semibold text-slate-600">To<input type="date" name="to" value="{{ request('to') }}" class="input mt-1"></label>
</div></div>
<div class="flex flex-wrap items-end gap-3">
<label class="text-xs font-semibold text-slate-600">Shop Status<select name="shop_status" class="input mt-1"><option value="">All Shop Statuses</option>@foreach(['active','suspended'] as $status)<option value="{{ $status }}" @selected(request('shop_status') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
<label class="text-xs font-semibold text-slate-600">Sales<select name="sales" class="input mt-1"><option value="">All Sales</option><option value="with" @selected(request('sales') === 'with')>With Sales</option><option value="none" @selected(request('sales') === 'none')>No Sales</option></select></label>
<noscript><button class="btn-primary">Apply filters</button></noscript>
<a data-sellers-clear data-sellers-clear-control class="ml-auto text-sm font-semibold text-brand-700" href="{{ route('admin.sellers.index',$archived ? ['tab'=>'archived'] : []) }}" @if(!$hasFilters) hidden @endif>Clear Filters</a>
</div>
<div data-sellers-period>@include('admin.sellers.partials.period')</div>
<div class="text-sm" aria-live="polite" aria-atomic="true">
    <p data-sellers-loading hidden class="text-slate-500">Updating sellers…</p>
    <p data-sellers-error hidden role="alert" class="text-rose-600"></p>
    <button data-sellers-retry type="button" hidden class="mt-1 text-brand-700 font-semibold">Retry</button>
</div>
@foreach($errors->all() as $error)<p class="text-sm text-rose-600">{{ $error }}</p>@endforeach
</form>
<div data-sellers-table aria-label="Seller results" tabindex="-1" aria-busy="false">@include('admin.sellers.partials.table')</div>
<div data-sellers-pagination>@include('admin.sellers.partials.pagination')</div>
</div>
@endsection
