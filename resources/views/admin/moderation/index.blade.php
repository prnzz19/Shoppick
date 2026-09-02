@extends('layouts.admin')
@section('title','Moderation')
@section('content')
@php
    $prefix=request()->routeIs('superadmin.*')?'superadmin':'admin';
    $tabs=[''=>'All','pending_scan'=>'Pending Review','flagged'=>'Flagged Products','approved'=>'Approved','rejected'=>'Rejected','scan_failed'=>'Scan Failed'];
    $groupIds=$groupedScans->keys()->map(fn($id)=>(string)$id);
    $autoExpand=request()->filled('shop') || request()->filled('q') || in_array(request('status'),['pending_scan','flagged'],true);
    $priorityGroup=$groupedScans->first(fn($items)=>$items->contains(fn($scan)=>in_array($scan->status,['pending_scan','flagged'],true)));
    $firstOpenId=(string)($priorityGroup?->first()?->store_id ?? $groupedScans->first()?->first()?->store_id ?? 'unassigned');
    $initialOpen=$groupIds->mapWithKeys(fn($id)=>[$id=>$autoExpand || (string)$firstOpenId===$id])->all();
    $filterParams=['q'=>request('q'),'shop'=>request('shop')];
@endphp

<div><p class="text-sm font-semibold text-brand-600">Trust & Safety</p><h1 class="text-2xl font-extrabold">Product Image Moderation</h1><p class="text-sm text-slate-500">Automated detections require human review.</p></div>

<form method="GET" action="{{ route($prefix.'.moderation.index') }}" class="card mt-6 grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_minmax(220px,320px)_auto]">
    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search product, shop, or seller">
    <select class="input" name="shop"><option value="">All Shops</option>@foreach($shopOptions as $option)<option value="{{ $option->id }}" @selected((string)request('shop')===(string)$option->id)>{{ $option->name }}</option>@endforeach</select>
    <input type="hidden" name="status" value="{{ request('status') }}"><button class="btn-primary">Apply Filters</button>
</form>

<div class="mt-5 flex gap-5 overflow-x-auto border-b">@foreach($tabs as $key=>$label)<a href="{{ route($prefix.'.moderation.index',$filterParams+['status'=>$key]) }}" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold {{ request('status','')===$key?'border-brand-500 text-brand-700':'border-transparent text-slate-500' }}">{{ $label }}</a>@endforeach</div>

<div x-data="{ open: @js($initialOpen), setAll(value) { Object.keys(this.open).forEach(id => this.open[id] = value) } }">
    <div class="mt-4 flex justify-end gap-2"><button type="button" @click="setAll(true)" class="btn-outline btn-sm">Expand All</button><button type="button" @click="setAll(false)" class="btn-outline btn-sm">Collapse All</button></div>
    <div class="mt-4 space-y-4">
    @forelse($groupedScans as $storeId=>$items)
        @php $store=$items->first()->store; $summary=$summaries->get($storeId); @endphp
        <section class="card overflow-hidden">
            <header class="border-b border-slate-100 bg-gradient-to-r from-brand-50 to-white"><button type="button" @click="open[@js((string)$storeId)] = !open[@js((string)$storeId)]" :aria-expanded="open[@js((string)$storeId)].toString()" aria-controls="shop-moderation-{{ $storeId }}" class="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-4 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-300" aria-label="Toggle {{ $store?->name ?? 'unknown shop' }} moderation records">
                <div class="flex min-w-0 items-center gap-3"><svg class="h-5 w-5 shrink-0 text-brand-600 transition-transform duration-200" :class="open[@js((string)$storeId)] ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg><div><p class="text-xs font-bold uppercase tracking-wider text-brand-600">Shop</p><h2 class="truncate text-lg font-extrabold text-navy-900">{{ $store?->name ?? 'Unknown Shop' }}</h2><p class="text-sm text-slate-500">Seller: {{ $store?->user?->name ?? $items->first()->seller?->name ?? 'Unknown Seller' }}</p></div></div>
                @php $moderatedCount=(int)($summary?->product_count ?? $items->pluck('product_id')->unique()->count()); @endphp
                <div class="flex flex-wrap gap-2 text-xs font-semibold"><span class="rounded-full bg-white px-3 py-1.5 text-navy-700 shadow-sm">{{ $moderatedCount }} moderated {{ Str::plural('product',$moderatedCount) }}</span><span class="rounded-full bg-sun-100 px-3 py-1.5 text-sun-600">{{ $summary?->pending_count ?? 0 }} pending</span><span class="rounded-full bg-accent-100 px-3 py-1.5 text-accent-600">{{ $summary?->flagged_count ?? 0 }} flagged</span><span class="rounded-full bg-leaf-100 px-3 py-1.5 text-leaf-600">{{ $summary?->approved_count ?? 0 }} approved</span><span class="rounded-full bg-rose-100 px-3 py-1.5 text-rose-600">{{ $summary?->rejected_count ?? 0 }} rejected</span></div>
            </button></header>
            <div id="shop-moderation-{{ $storeId }}" x-show="open[@js((string)$storeId)]" x-transition.opacity.duration.180ms x-cloak class="overflow-x-auto"><table class="w-full min-w-[780px] text-left text-sm"><thead><tr class="border-b"><th class="p-4">Image</th><th>Product</th><th>Detection</th><th>Confidence</th><th>Priority</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
                @foreach($items as $scan)<tr class="border-b"><td class="p-4"><img src="{{ $scan->image->url }}" class="h-14 w-14 rounded-lg object-cover" alt=""></td><td>{{ $scan->product->name }}</td><td>{{ ucwords(str_replace('_',' ',$scan->detected_category??'No detection')) }}</td><td>{{ $scan->confidence!==null?number_format($scan->confidence*100,1).'%':'—' }}</td><td>{{ ucfirst($scan->risk_level) }}</td><td>{{ $scan->created_at->format('M d, Y') }}</td><td><x-admin.status-badge :status="$scan->status"/></td><td><a class="font-semibold text-brand-600" href="{{ route($prefix.'.moderation.show',$scan) }}">Review</a></td></tr>@endforeach
            </tbody></table></div>
        </section>
    @empty
        <div class="card p-12 text-center"><p class="font-semibold text-navy-800">No moderation scans match these filters.</p><p class="mt-1 text-sm text-slate-500">Try another shop, status, or search.</p></div>
    @endforelse
    </div>
</div>
<div class="mt-5">{{ $scans->links() }}</div>
@endsection
