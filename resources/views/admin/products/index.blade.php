@extends('layouts.admin')

@section('title', 'Products')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-navy-800">Products</h1>
    <a href="{{ route('admin.products.create') }}" class="btn-primary">+ Add Product</a>
</div>

<form method="GET" action="{{ route('admin.products.index') }}" class="mb-5 flex flex-wrap gap-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or SKU..." class="input !w-64">
    <select name="category_id" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All categories</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected((string)request('category_id') === (string)$cat->id)>{{ $cat->name }}</option>
            @foreach($cat->children as $child)
                <option value="{{ $child->id }}" @selected((string)request('category_id') === (string)$child->id)>&nbsp;&nbsp;{{ $child->name }}</option>
            @endforeach
        @endforeach
    </select>
    <select name="status" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All status</option>
        <option value="active" @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>
    <button type="submit" class="btn-primary">Filter</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[800px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">Product</th>
                    <th class="table-th">Category</th>
                    <th class="table-th">Price</th>
                    <th class="table-th">Stock</th>
                    <th class="table-th">Sold</th>
                    <th class="table-th">Status</th>
                    <th class="table-th text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $product)
                    <tr>
                        <td class="table-td">
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                    @if($product->getMainImageAttribute())<img src="{{ asset('storage/'.$product->getMainImageAttribute()) }}" class="h-full w-full object-cover">@endif
                                </div>
                                <div class="min-w-0">
                                    <p class="line-clamp-1 font-medium text-navy-800">{{ $product->name }}</p>
                                    <p class="text-xs text-slate-500">SKU: {{ $product->sku ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="table-td">{{ $product->category?->name }}</td>
                        <td class="table-td">
                            <p class="font-semibold text-navy-800">₱{{ number_format($product->salePrice(), 2) }}</p>
                            @if($product->hasDiscount())<p class="text-xs text-slate-400 line-through">₱{{ number_format($product->originalPrice(), 2) }}</p>@endif
                        </td>
                        <td class="table-td">
                            <span class="{{ $product->isOutOfStock() ? 'text-rose-600 font-semibold' : ($product->isLowStock() ? 'text-sun-500 font-semibold' : 'text-navy-700') }}">{{ $product->stock }}</span>
                        </td>
                        <td class="table-td">{{ number_format($product->sold_count) }}</td>
                        <td class="table-td">
                            <form method="POST" action="{{ route('admin.products.toggle', $product->id) }}">
                                @csrf
                                <button type="submit" class="badge {{ $product->is_active ? 'bg-leaf-100 text-leaf-500' : 'bg-slate-100 text-slate-500' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</button>
                            </form>
                            @if($product->is_featured)<span class="badge bg-accent-100 text-accent-600 ml-1">Featured</span>@endif
                        </td>
                        <td class="table-td">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('products.show', $product->slug) }}" class="p-2 text-slate-400 hover:text-brand-600" title="View" target="_blank"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm7-3a9 9 0 01-14 0M22 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></a>
                                <a href="{{ route('admin.products.edit', $product->id) }}" class="p-2 text-slate-400 hover:text-brand-600" title="Edit"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" onsubmit="return confirm('Delete this product?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 text-slate-400 hover:text-rose-600" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table-td py-10 text-center text-slate-400">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $products->links('components.pagination') }}</div>
@endsection
