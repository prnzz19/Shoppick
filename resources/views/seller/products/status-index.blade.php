@extends('layouts.seller')
@section('title','Products')
@section('content')
@php
    $tabs = ['' => 'All Products', 'active' => 'Active', 'draft' => 'Draft', 'inactive' => 'Inactive', 'archived' => 'Archived'];
    $archivedTab = request('status') === 'archived';
@endphp
<div class="flex items-end justify-between">
    <div><p class="text-sm font-semibold text-brand-600">Catalog</p><h1 class="text-2xl font-extrabold">My Products</h1></div>
    <a class="btn-primary" href="{{ route('seller.products.create') }}">+ Add Product</a>
</div>
<div class="mt-6 flex gap-5 overflow-x-auto border-b">
    @foreach($tabs as $key => $label)
        <a href="{{ route('seller.products.index', ['status' => $key]) }}" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold {{ request('status', '') === $key ? 'border-brand-500 text-brand-700' : 'border-transparent text-slate-500' }}">{{ $label }}</a>
    @endforeach
</div>
<form class="mt-5 grid gap-3 sm:grid-cols-3">
    @if(request()->filled('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <input class="input" name="q" value="{{ request('q') }}" placeholder="Search products">
    <select class="input" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')==$category->id)>{{ $category->name }}</option>@endforeach</select>
    <button class="btn-outline">Filter</button>
</form>
<div class="card mt-5 overflow-x-auto">
    <table class="w-full min-w-[940px] text-left text-sm">
        <thead><tr class="border-b text-xs uppercase text-slate-400"><th class="p-4">Product</th><th>Category</th><th>Price</th><th>Stock</th><th>{{ $archivedTab ? 'Archived date' : 'Sold' }}</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($products as $product)
            <tr class="border-b">
                <td class="p-4"><div class="flex items-center gap-3"><div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">@if($product->main_image)<img src="{{ asset('storage/'.$product->main_image) }}" class="h-full w-full object-cover" alt="">@else<svg class="h-6 w-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 16l4-4 4 4 4-5 4 5M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>@endif</div><div><p class="font-semibold">{{ $product->name }}</p><p class="text-xs text-slate-400">{{ $product->sku ?: 'No SKU' }}</p></div></div></td>
                <td>{{ $product->category?->name ?? 'Uncategorized' }}</td><td>₱{{ number_format($product->salePrice(),2) }}</td><td>{{ $product->stock }}</td>
                <td>{{ $archivedTab ? $product->deleted_at?->format('M d, Y g:i A') : number_format($product->sold_count) }}</td>
                <td><x-admin.status-badge :status="$archivedTab ? 'archived' : strtolower(str_replace(' ','_',$product->sellerStatus()))"/>@unless($archivedTab)<p class="mt-1 max-w-56 text-xs text-slate-500">{{ $product->sellerVisibilityReason() }}</p>@endunless</td>
                <td>
                    @if($archivedTab)
                        <a class="font-semibold text-brand-600" href="{{ route('seller.products.archived.show',$product) }}">View</a>
                        <form class="inline" method="POST" action="{{ route('seller.products.restore',$product) }}">@csrf<button class="ml-3 font-semibold text-leaf-500">Restore</button></form>
                        @if($product->order_items_count === 0)<form class="inline" method="POST" action="{{ route('seller.products.force-destroy',$product) }}" onsubmit="return confirm('Permanently delete this product? This cannot be undone.')">@csrf @method('DELETE')<button class="ml-3 text-rose-600">Delete Permanently</button></form>@endif
                    @else
                        <a class="font-semibold text-brand-600" href="{{ route('seller.products.edit',$product) }}">Edit</a>
                        <form class="inline" method="POST" action="{{ route('seller.products.publication',$product) }}">@csrf<input type="hidden" name="action" value="{{ $product->is_active ? 'deactivate' : 'activate' }}"><button class="ml-3 font-semibold text-amber-600">{{ $product->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        <form class="inline" method="POST" action="{{ route('seller.products.destroy',$product) }}" onsubmit="return confirm('Archive this product?')">@csrf @method('DELETE')<button class="ml-3 text-rose-600">Archive</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="p-12 text-center"><p class="font-semibold">{{ $archivedTab ? 'No archived products' : 'No products found' }}</p>@unless($archivedTab)<a href="{{ route('seller.products.create') }}" class="btn-primary btn-sm mt-4">+ Add Product</a>@endunless</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $products->links() }}</div>
@endsection
