@extends('layouts.admin')
@section('title', 'Inventory')
@section('content')
<div><p class="text-sm font-semibold text-brand-600">Multi-vendor oversight</p><h1 class="text-2xl font-bold text-navy-800">Inventory by Shop</h1><p class="mt-1 text-sm text-slate-500">Review stock and ownership across SHOPPICK stores.</p></div>

<form method="GET" action="{{ route('admin.inventory.index') }}" class="card mt-6 grid gap-3 p-4 md:grid-cols-[minmax(0,1fr)_minmax(220px,320px)_auto]">
    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search product, SKU, shop, or seller">
    <select class="input" name="shop"><option value="">All Shops</option>@foreach($shopOptions as $option)<option value="{{ $option->id }}" @selected((string)request('shop')===(string)$option->id)>{{ $option->name }}</option>@endforeach</select>
    <input type="hidden" name="scope" value="{{ request('scope') }}"><button class="btn-primary">Apply Filters</button>
</form>

@php $filterParams=['q'=>request('q'),'shop'=>request('shop')]; @endphp
<div class="mt-4 flex flex-wrap gap-2">
    <a href="{{ route('admin.inventory.index',$filterParams) }}" class="chip {{ !request('scope') ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">All</a>
    <a href="{{ route('admin.inventory.index',$filterParams+['scope'=>'low']) }}" class="chip {{ request('scope') === 'low' ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">Low Stock</a>
    <a href="{{ route('admin.inventory.index',$filterParams+['scope'=>'out']) }}" class="chip {{ request('scope') === 'out' ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">Out of Stock</a>
</div>

@php
    $shopIds=$shops->getCollection()->pluck('id');
    $autoExpand=request()->filled('shop') || request()->filled('q');
    $firstShopWithItems=$shops->getCollection()->first(fn($shop)=>$shop->products->isNotEmpty())?->id;
    $initialOpen=$shopIds->mapWithKeys(fn($id)=>[(string)$id=>$autoExpand || $id===$firstShopWithItems])->all();
@endphp
<div x-data="{ open: @js($initialOpen), setAll(value) { Object.keys(this.open).forEach(id => this.open[id] = value) } }">
<div class="mt-4 flex justify-end gap-2">
    <button type="button" @click="setAll(true)" class="btn-outline btn-sm">Expand All</button>
    <button type="button" @click="setAll(false)" class="btn-outline btn-sm">Collapse All</button>
</div>
<div class="mt-4 space-y-4">
@forelse($shops as $shop)
    <section class="card overflow-hidden">
        <header class="border-b border-slate-100 bg-gradient-to-r from-brand-50 to-white">
        <button type="button" @click="open[{{ $shop->id }}] = !open[{{ $shop->id }}]" :aria-expanded="open[{{ $shop->id }}].toString()" aria-controls="shop-inventory-{{ $shop->id }}" :aria-label="(open[{{ $shop->id }}] ? 'Collapse' : 'Expand') + ' {{ addslashes($shop->name) }} inventory'" class="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-4 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-brand-300">
            <div class="flex min-w-0 items-center gap-3"><svg class="h-5 w-5 shrink-0 text-brand-600 transition-transform duration-200" :class="open[{{ $shop->id }}] ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg><div><p class="text-xs font-bold uppercase tracking-wider text-brand-600">Shop</p><h2 class="truncate text-lg font-extrabold text-navy-900">{{ $shop->name }}</h2><p class="text-sm text-slate-500">Seller: {{ $shop->user->name }}</p></div></div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold"><span class="rounded-full bg-white px-3 py-1.5 text-navy-700 shadow-sm">{{ $shop->products_count }} {{ Str::plural('product',$shop->products_count) }}</span><span class="rounded-full bg-sun-100 px-3 py-1.5 text-sun-500">{{ $shop->low_stock_count }} low stock</span><span class="rounded-full bg-rose-100 px-3 py-1.5 text-rose-600">{{ $shop->out_of_stock_count }} out of stock</span></div>
        </button></header>
        <div id="shop-inventory-{{ $shop->id }}" x-show="open[{{ $shop->id }}]" x-transition.opacity.duration.180ms x-cloak>
        @if($shop->products->isEmpty())
            <div class="px-5 py-10 text-center text-sm text-slate-400">No inventory items yet.</div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[760px]"><thead class="bg-slate-50/70"><tr><th class="table-th">Product</th><th class="table-th">SKU</th><th class="table-th">Stock</th><th class="table-th">Sold</th><th class="table-th">Status</th><th class="table-th">Update Stock</th></tr></thead><tbody class="divide-y divide-slate-100">
            @foreach($shop->products as $product)<tr>
                <td class="table-td"><div class="flex items-center gap-3"><div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">@if($product->main_image)<img src="{{ asset('storage/'.$product->main_image) }}" class="h-full w-full object-cover" alt="">@endif</div><span class="line-clamp-1 font-medium text-navy-800">{{ $product->name }}</span></div></td>
                <td class="table-td text-xs text-slate-500">{{ $product->sku ?: '—' }}</td><td class="table-td"><span class="font-bold {{ $product->isOutOfStock() ? 'text-rose-600' : ($product->isLowStock() ? 'text-sun-500' : 'text-navy-800') }}">{{ $product->stock }}</span></td><td class="table-td">{{ number_format($product->sold_count) }}</td>
                <td class="table-td"><span class="badge {{ $product->isOutOfStock() ? 'bg-rose-100 text-rose-600' : ($product->isLowStock() ? 'bg-sun-100 text-sun-500' : 'bg-leaf-100 text-leaf-500') }}">{{ $product->isOutOfStock() ? 'Out of stock' : ($product->isLowStock() ? 'Low' : 'In stock') }}</span></td>
                <td class="table-td"><form method="POST" action="{{ route('admin.inventory.stock',$product) }}" class="flex gap-2">@csrf<input type="number" name="stock" value="{{ $product->stock }}" min="0" required class="input !w-24 !py-1.5"><button class="btn-outline btn-sm">Set</button></form></td>
            </tr>@endforeach
            </tbody></table></div>
        @endif
        </div>
    </section>
@empty
    <div class="card p-12 text-center"><p class="font-semibold text-navy-800">No inventory matches these filters.</p><p class="mt-1 text-sm text-slate-500">Try another shop, stock status, or search.</p></div>
@endforelse
</div>
</div>
<div class="mt-5">{{ $shops->links('components.pagination') }}</div>
@endsection
