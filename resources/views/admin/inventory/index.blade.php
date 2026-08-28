@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-navy-800">Inventory</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.inventory.index') }}" class="chip {{ !request('scope') ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">All</a>
        <a href="{{ route('admin.inventory.index', ['scope' => 'low']) }}" class="chip {{ request('scope') === 'low' ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">Low Stock</a>
        <a href="{{ route('admin.inventory.index', ['scope' => 'out']) }}" class="chip {{ request('scope') === 'out' ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">Out of Stock</a>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-th">Product</th>
                    <th class="table-th">SKU</th>
                    <th class="table-th">Stock</th>
                    <th class="table-th">Sold</th>
                    <th class="table-th">Status</th>
                    <th class="table-th">Update Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $product)
                    <tr>
                        <td class="table-td">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                    @if($product->getMainImageAttribute())<img src="{{ asset('storage/'.$product->getMainImageAttribute()) }}" class="h-full w-full object-cover">@endif
                                </div>
                                <span class="line-clamp-1 font-medium text-navy-800">{{ $product->name }}</span>
                            </div>
                        </td>
                        <td class="table-td text-xs text-slate-500">{{ $product->sku ?? '—' }}</td>
                        <td class="table-td">
                            <span class="font-bold {{ $product->isOutOfStock() ? 'text-rose-600' : ($product->isLowStock() ? 'text-sun-500' : 'text-navy-800') }}">{{ $product->stock }}</span>
                        </td>
                        <td class="table-td">{{ number_format($product->sold_count) }}</td>
                        <td class="table-td">
                            <span class="badge {{ $product->isOutOfStock() ? 'bg-rose-100 text-rose-600' : ($product->isLowStock() ? 'bg-sun-100 text-sun-500' : 'bg-leaf-100 text-leaf-500') }}">{{ $product->isOutOfStock() ? 'Out of stock' : ($product->isLowStock() ? 'Low' : 'In stock') }}</span>
                        </td>
                        <td class="table-td">
                            <form method="POST" action="{{ route('admin.inventory.stock', $product->id) }}" class="flex gap-2">
                                @csrf
                                <input type="number" name="stock" value="{{ $product->stock }}" min="0" class="input !w-24 !py-1.5">
                                <button type="submit" class="btn-outline btn-sm">Set</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table-td py-10 text-center text-slate-400">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $products->links('components.pagination') }}</div>
@endsection
