@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Reports</h1>
</div>

<form method="GET" action="{{ route('admin.reports.index') }}" class="mb-6 flex flex-wrap items-end gap-3 card p-4">
    <div>
        <label class="label">From</label>
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input">
    </div>
    <div>
        <label class="label">To</label>
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input">
    </div>
    <div>
        <label class="label">Report</label>
        <select name="report" class="input">
            @foreach(['sales' => 'Sales', 'inventory' => 'Inventory', 'users' => 'Users & Roles'] as $key => $label)
                <option value="{{ $key }}" @selected($reportType === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn-primary">Generate</button>
</form>

@if($reportType === 'sales')
    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <x-admin.stat-card label="Sales Total" :value="'₱'.number_format($salesTotal)" color="bg-brand-50 text-brand-600" icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        <x-admin.stat-card label="Orders" :value="$ordersCount" color="bg-accent-50 text-accent-600" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        <x-admin.stat-card label="Avg Order" :value="'₱'.number_format($avgOrder)" color="bg-sun-50 text-sun-500" icon="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        <x-admin.stat-card label="Products Sold" :value="$productsSold" color="bg-leaf-50 text-leaf-500" icon="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        <x-admin.stat-card label="New Users" :value="$newUsers" color="bg-brand-50 text-brand-600" icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </div>

    {{-- Charts --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Sales by Day</h3>
            @if($salesByDay->isEmpty())<p class="text-sm text-slate-400">No sales in period.</p>
            @else
            <div class="flex h-44 items-end gap-1">
                @foreach($salesByDay as $date => $total)
                    @php $h = max(4, ($total / max(1, $salesByDay->max())) * 100); @endphp
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $date }}: ₱{{ number_format($total) }}">
                        <div class="w-full rounded-t bg-brand-400 hover:bg-brand-500" style="height: {{ $h }}px"></div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Orders by Day</h3>
            @if($ordersByDay->isEmpty())<p class="text-sm text-slate-400">No orders in period.</p>
            @else
            <div class="flex h-44 items-end gap-1">
                @foreach($ordersByDay as $date => $count)
                    @php $h = max(4, ($count / max(1, $ordersByDay->max())) * 100); @endphp
                    <div class="flex flex-1 flex-col items-center gap-1" title="{{ $date }}: {{ $count }}">
                        <div class="w-full rounded-t bg-accent-400 hover:bg-accent-500" style="height: {{ $h }}px"></div>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Top Products</h3>
            <div class="space-y-3">
                @forelse($topProducts as $p)
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if($p->images->first())<img src="{{ asset('storage/'.$p->images->first()->path) }}" class="h-full w-full object-cover">@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 text-sm font-medium text-navy-800">{{ $p->name }}</p>
                            <p class="text-xs text-slate-500">{{ $p->sold_count }} sold · stock {{ $p->stock }}</p>
                        </div>
                        <span class="text-sm font-bold text-navy-800">₱{{ number_format($p->price, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No product data.</p>
                @endforelse
            </div>
        </div>
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Top Categories</h3>
            <div class="space-y-3">
                @forelse($topCategories as $c)
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <p class="font-medium text-navy-800">{{ $c['name'] }}</p>
                            <p class="text-xs text-slate-500">{{ $c['products'] }} products · {{ $c['stock'] }} in stock</p>
                        </div>
                        <span class="font-bold text-navy-800">{{ $c['sold'] }} sold</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No category data.</p>
                @endforelse
            </div>
        </div>
    </div>
@endif

@if($reportType === 'inventory')
    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Low Stock / Out of Stock</h3>
        <div class="space-y-3">
            @forelse($lowStockProducts as $p)
                <a href="{{ route('admin.products.edit', $p->id) }}" class="flex items-center justify-between rounded-xl px-2 py-2 hover:bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if($p->getMainImageAttribute())<img src="{{ asset('storage/'.$p->getMainImageAttribute()) }}" class="h-full w-full object-cover">@endif
                        </div>
                        <span class="line-clamp-1 font-medium text-navy-800">{{ $p->name }}</span>
                    </div>
                    <span class="badge {{ $p->isOutOfStock() ? 'bg-rose-100 text-rose-600' : 'bg-sun-100 text-sun-500' }}">{{ $p->stock }} left</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">All products are well stocked.</p>
            @endforelse
        </div>
    </div>
@endif

@if($reportType === 'users')
    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Users by Role</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach($usersByRole as $slug => $count)
                <div class="rounded-2xl bg-slate-50 p-5 text-center">
                    <p class="text-3xl font-extrabold text-navy-800">{{ $count }}</p>
                    <p class="mt-1 text-sm capitalize text-slate-500">{{ str_replace('_', ' ', $slug) }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
