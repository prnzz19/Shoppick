@extends('layouts.admin')

@section('title', 'Products')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-navy-800">Products</h1>
    <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Add Product</a>
</div>

<form method="GET" action="{{ route('admin.products.index') }}" class="mb-5 flex flex-wrap gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or SKU..." class="input !w-64">
    <select name="shop_id" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All shops</option>
        @foreach($shops as $shop)<option value="{{ $shop->id }}" @selected((string)request('shop_id') === (string)$shop->id)>{{ $shop->name }}</option>@endforeach
        <option value="unassigned" @selected(request('shop_id') === 'unassigned')>Unassigned / Legacy Products</option>
    </select>
    <select name="category_id" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All categories</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected((string)request('category_id') === (string)$cat->id)>{{ $cat->name }}</option>
            @foreach($cat->children as $child)<option value="{{ $child->id }}" @selected((string)request('category_id') === (string)$child->id)>&nbsp;&nbsp;{{ $child->name }}</option>@endforeach
        @endforeach
    </select>
    <select name="status" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All status</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        <option value="pending" @selected(request('status') === 'pending')>Pending moderation</option>
        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
        <option value="low_stock" @selected(request('status') === 'low_stock')>Low stock</option>
        <option value="out_of_stock" @selected(request('status') === 'out_of_stock')>Out of stock</option>
        <option value="archived" @selected(request('status') === 'archived')>Archived</option>
    </select>
    <button type="submit" class="btn-primary">Filter</button>
</form>

@if($productGroups->isNotEmpty())
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Grouped by Seller Shop · {{ $products->total() }} matching product(s)</p>
        <div class="flex gap-3 text-xs font-semibold">
            <button type="button" class="text-brand-600 hover:text-brand-700" @click="$dispatch('products-expand-all')">Expand All</button>
            <button type="button" class="text-slate-500 hover:text-navy-800" @click="$dispatch('products-collapse-all')">Collapse All</button>
        </div>
    </div>

    <div class="space-y-4">
        @foreach($productGroups as $group)
            @php($panelId = 'shop-products-'.($group->store?->id ?? 'unassigned'))
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" @products-expand-all.window="open=true" @products-collapse-all.window="open=false">
                <div class="flex flex-wrap items-center gap-3 border-l-4 px-4 py-4 transition" :class="open ? 'border-brand-500 bg-brand-50/40' : 'border-transparent bg-white'">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="open=!open" :aria-expanded="open.toString()" aria-controls="{{ $panelId }}">
                        <svg class="h-5 w-5 shrink-0 text-brand-600 transition" :class="open && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-bold text-navy-800">{{ $group->store?->name ?? 'Unassigned / Legacy Products' }}</h2>
                            <p class="truncate text-xs text-slate-500">{{ $group->store ? 'Seller: '.($group->store->user?->name ?? 'Unknown Seller') : 'Products without a Shop relationship' }}</p>
                        </div>
                    </button>
                    @if($group->stats)
                        <div class="flex flex-wrap gap-1.5 text-xs">
                            <span class="badge bg-slate-100 text-slate-600">Products: {{ $group->stats->total_count }}</span>
                            <span class="badge bg-leaf-100 text-leaf-500">Active: {{ $group->stats->active_count }}</span>
                            <span class="badge bg-slate-100 text-slate-600">Inactive: {{ $group->stats->inactive_count }}</span>
                            @if($group->stats->low_stock_count)<span class="badge bg-accent-100 text-accent-600">Low Stock: {{ $group->stats->low_stock_count }}</span>@endif
                            @if($group->stats->out_of_stock_count)<span class="badge bg-rose-100 text-rose-600">Out of Stock: {{ $group->stats->out_of_stock_count }}</span>@endif
                        </div>
                    @endif
                    @if($group->store && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermissionTo('view_shops')))
                        <a href="{{ route('admin.shops.show', $group->store) }}" class="btn-outline btn-sm">View Shop</a>
                    @endif
                </div>
                <div id="{{ $panelId }}" x-show="open" x-cloak>
                    <div class="overflow-x-auto border-t border-slate-100">
                        <table class="w-full min-w-[800px]">
                            <thead class="bg-slate-50"><tr><th class="table-th">Product</th><th class="table-th">Category</th><th class="table-th">Price</th><th class="table-th">Stock</th><th class="table-th">Sold</th><th class="table-th">Status</th><th class="table-th text-right">Actions</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">@foreach($group->products as $product)@include('admin.products._row', ['product' => $product])@endforeach</tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@else
    <div class="card py-12 text-center text-slate-400">No products found.</div>
@endif

<div class="mt-4">{{ $products->links('components.pagination') }}</div>
@endsection
