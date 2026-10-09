@extends('layouts.seller')
@section('title','Products')
@section('content')
@php
    $tabs = ['' => 'All Products', 'active' => 'Active', 'draft' => 'Draft', 'inactive' => 'Inactive', 'archived' => 'Archived'];
    $hasFilters = request()->filled('q') || request()->filled('category') || request()->filled('moderation') || request()->filled('status');
    $archivedTab = request('status') === 'archived';
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
    <div><p class="text-sm font-semibold text-brand-600">Catalog</p><h1 class="text-2xl font-extrabold">My Products</h1></div>
    <a class="btn-primary" href="{{ route('seller.products.create') }}">+ Add Product</a>
</div>
<div class="mt-6 flex gap-5 overflow-x-auto border-b">
    @foreach($tabs as $key => $label)
        <a href="{{ route('seller.products.index', array_merge(request()->only(['q','category','moderation']), ['status' => $key])) }}" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold {{ request('status', '') === $key ? 'border-brand-500 text-brand-700' : 'border-transparent text-slate-500' }}">{{ $label }} <span class="ml-1 text-xs">({{ $counts[$key] ?? 0 }})</span></a>
    @endforeach
</div>
<form method="GET" action="{{ route('seller.products.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
    @if(request()->filled('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="min-w-0 flex-1 basis-56"><label class="label" for="product-search">Search products</label><input id="product-search" class="input" name="q" maxlength="100" value="{{ request('q') }}" placeholder="Product name or SKU"></div>
    <div class="min-w-0 flex-1 basis-44"><label class="label" for="product-category">Category</label><select id="product-category" class="input" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="min-w-0 flex-1 basis-44"><label class="label" for="product-moderation">Moderation</label><select id="product-moderation" class="input" name="moderation">@foreach([''=>'All moderation states','clear'=>'Clear','review_required'=>'Review Required','under_review'=>'Under Moderation','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected(request('moderation','')===$value)>{{ $label }}</option>@endforeach</select></div>
    <button class="btn-outline" type="submit">Filter</button>
    @if($hasFilters)<a class="py-2 text-sm font-semibold text-brand-700 hover:underline" href="{{ route('seller.products.index') }}">Clear Filters</a>@endif
</form>
<div class="card mt-5 overflow-x-auto">
    <table class="w-full min-w-[940px] text-left text-sm">
        <thead><tr class="border-b text-xs uppercase text-slate-400"><th class="px-4 py-3">Product</th><th>Category</th><th>Price</th><th>Stock</th><th>{{ $archivedTab ? 'Archived date' : 'Sold' }}</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr class="border-b">
                <td class="px-4 py-3"><div class="flex items-center gap-3"><div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">@if($product->main_image)<img src="{{ asset('storage/'.$product->main_image) }}" class="h-full w-full object-cover" alt="">@else<svg class="h-6 w-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 16l4-4 4 4 4-5 4 5M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>@endif</div><div><p class="max-w-56 truncate font-semibold" title="{{ $product->name }}">{{ $product->name }}</p><p class="text-xs text-slate-400">{{ $product->sku ?: 'No SKU' }}</p></div></div></td>
                <td>{{ $product->category?->name ?? 'Uncategorized' }}</td><td>₱{{ number_format($product->salePrice(),2) }}</td><td>{{ $product->stock }}</td>
                <td>{{ $archivedTab ? $product->deleted_at?->format('M d, Y g:i A') : number_format($product->sold_count) }}</td>
                <td><x-admin.status-badge :status="$archivedTab ? 'archived' : strtolower(str_replace(' ','_',$product->sellerStatus()))"/>@unless($archivedTab)<p class="mt-1 max-w-48 truncate text-xs text-slate-500" title="{{ $product->sellerVisibilityReason() }}">{{ $product->sellerVisibilityReason() }}</p>@endunless</td>
                <td>
                    @if($archivedTab)
                        <a class="font-semibold text-brand-600" href="{{ route('seller.products.archived.show',$product) }}">View</a>
                        <form class="inline" method="POST" action="{{ route('seller.products.restore',$product) }}">@csrf<button class="ml-3 font-semibold text-leaf-500">Restore</button></form>
                        @if($product->order_items_count === 0)<form class="inline" method="POST" action="{{ route('seller.products.force-destroy',$product) }}" onsubmit="return confirm('Permanently delete this product? This cannot be undone.')">@csrf @method('DELETE')<button class="ml-3 text-rose-600">Delete Permanently</button></form>@endif
                    @else
                        <a class="font-semibold text-brand-600" href="{{ route('seller.products.edit',['product'=>$product,'list_context'=>request()->only(['q','category','status','moderation','page'])]) }}">Edit</a>
                        <form class="inline" method="POST" action="{{ route('seller.products.publication',$product) }}">@csrf<input type="hidden" name="action" value="{{ $product->is_active ? 'deactivate' : 'activate' }}"><button class="ml-3 font-semibold text-amber-600">{{ $product->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        <form class="inline" method="POST" action="{{ route('seller.products.destroy',$product) }}" onsubmit="return confirm('Archive this product?')">@csrf @method('DELETE')<button class="ml-3 text-rose-600">Archive</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-8 text-center">
                <p class="font-semibold">{{ request('moderation') === 'review_required' ? 'No Review Required products match your filters.' : ($hasFilters ? ($archivedTab && !request()->filled('q') && !request()->filled('category') && !request()->filled('moderation') ? 'No archived products' : 'No products match your filters.') : 'No products yet') }}</p>
                @if($hasFilters)<a href="{{ route('seller.products.index') }}" class="mt-3 inline-block font-semibold text-brand-700">Clear Filters</a>
                @else<a href="{{ route('seller.products.create') }}" class="btn-primary btn-sm mt-4">+ Add Product</a>@endif
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5"><p class="mb-3 text-sm text-slate-500">Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products</p>{{ $products->onEachSide(1)->links('seller.products.pagination') }}</div>
@endsection
