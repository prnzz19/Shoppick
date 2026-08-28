@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="mb-6 text-2xl font-bold text-navy-800">Dashboard</h1>

<div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
    <x-admin.stat-card label="Products" :value="$stats['products']" color="bg-brand-50 text-brand-600" icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
    <x-admin.stat-card label="Categories" :value="$stats['categories']" color="bg-accent-50 text-accent-600" icon="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
    <x-admin.stat-card label="Orders" :value="$stats['orders']" color="bg-brand-50 text-brand-600" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" />
    <x-admin.stat-card label="Pending" :value="$stats['pending_orders']" color="bg-sun-50 text-sun-500" icon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    <x-admin.stat-card label="Low Stock" :value="$lowStock->count()" color="bg-rose-50 text-rose-600" icon="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
    <x-admin.stat-card label="Revenue" :value="'₱'.number_format($stats['revenue'])" color="bg-leaf-50 text-leaf-500" icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Sales by day --}}
    <div class="card p-5 lg:col-span-2">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Sales (Last 7 Days)</h3>
        @if($salesByDay->isEmpty())
            <p class="text-sm text-slate-400">No sales yet.</p>
        @else
            <div class="flex h-48 items-end gap-2">
                @foreach($salesByDay as $date => $total)
                    @php $max = max(1, $salesByDay->max()); $h = max(4, ($total / $max) * 100); @endphp
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <span class="text-[10px] font-semibold text-navy-700">₱{{ number_format($total) }}</span>
                        <div class="w-full rounded-t-lg bg-brand-500 hover:bg-brand-600 transition" style="height: {{ $h }}px"></div>
                        <span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($date)->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Order status --}}
    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Order Status</h3>
        <div class="space-y-3">
            @foreach($statusCounts as $status => $count)
                <div class="flex items-center justify-between text-sm">
                    <span class="capitalize text-slate-600">{{ $status }}</span>
                    <span class="font-bold text-navy-800">{{ $count }}</span>
                </div>
            @endforeach
        </div>
        <a href="{{ route('admin.orders.index') }}" class="btn-ghost btn-sm mt-4 w-full">View all orders</a>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    {{-- Recent orders --}}
    <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-bold uppercase tracking-wide text-navy-800">Recent Orders</h3>
            <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-brand-600">View all</a>
        </div>
        @if($recentOrders->isEmpty())
            <p class="text-sm text-slate-400">No orders yet.</p>
        @else
            <div class="space-y-3">
                @foreach($recentOrders as $order)
                    <a href="{{ route('admin.orders.show', $order->id) }}" class="flex items-center justify-between rounded-xl px-2 py-2 hover:bg-slate-50">
                        <div>
                            <p class="font-mono text-sm font-semibold text-navy-800">{{ $order->order_number }}</p>
                            <p class="text-xs text-slate-500">{{ $order->buyer_name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-navy-800">₱{{ number_format($order->total, 2) }}</p>
                            <x-admin.status-badge :status="$order->status" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Low stock --}}
    <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-bold uppercase tracking-wide text-navy-800">Low Stock Alerts</h3>
            <a href="{{ route('admin.inventory.index', ['scope' => 'low']) }}" class="text-sm font-semibold text-brand-600">Manage</a>
        </div>
        @if($lowStock->isEmpty())
            <p class="text-sm text-slate-400">All products are well stocked.</p>
        @else
            <div class="space-y-3">
                @foreach($lowStock as $product)
                    <a href="{{ route('admin.products.edit', $product->id) }}" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-slate-50">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if($product->getMainImageAttribute())<img src="{{ asset('storage/'.$product->getMainImageAttribute()) }}" class="h-full w-full object-cover">@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 text-sm font-medium text-navy-800">{{ $product->name }}</p>
                            <p class="text-xs text-slate-500">{{ $product->stock }} left</p>
                        </div>
                        <span class="badge {{ $product->isOutOfStock() ? 'bg-rose-100 text-rose-600' : 'bg-sun-100 text-sun-500' }}">{{ $product->isOutOfStock() ? 'Out' : 'Low' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
