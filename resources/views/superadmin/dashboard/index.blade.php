@extends('layouts.admin')

@section('title', 'Super Admin Dashboard')

@section('content')
<h1 class="mb-6 text-2xl font-bold text-navy-800">Platform Overview</h1>

<div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-5">
    <x-admin.stat-card label="Total Users" :value="$stats['total_users']" color="bg-brand-50 text-brand-600" icon="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    <x-admin.stat-card label="Buyers" :value="$stats['total_buyers']" color="bg-accent-50 text-accent-600" icon="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
    <x-admin.stat-card label="Admins" :value="$stats['total_admins']" color="bg-sun-50 text-sun-500" icon="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    <x-admin.stat-card label="Orders" :value="$stats['total_orders']" color="bg-brand-50 text-brand-600" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
    <x-admin.stat-card label="Total Sales" :value="'₱'.number_format($stats['total_sales'])" color="bg-leaf-50 text-leaf-500" icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h3 class="mb-1 text-sm font-bold uppercase tracking-wide text-navy-800">Sales (Last 14 Days)</h3>
        <p class="mb-4 text-xs text-slate-400">₱{{ number_format($stats['total_sales']) }} total completed</p>
        @if($salesByDay->isEmpty())<p class="text-sm text-slate-400">No sales yet.</p>
        @else
        <div class="flex h-44 items-end gap-1">
            @foreach($salesByDay as $date => $total)
                @php $h = max(4, ($total / max(1, $salesByDay->max())) * 100); @endphp
                <div class="flex flex-1 flex-col items-center gap-1" title="{{ $date }}">
                    <div class="w-full rounded-t bg-brand-400 hover:bg-brand-500 transition" style="height: {{ $h }}px"></div>
                    <span class="text-[9px] text-slate-400">{{ \Carbon\Carbon::parse($date)->format('d') }}</span>
                </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Order Status</h3>
        <div class="space-y-2.5">
            @foreach($orderStatusDist as $status => $count)
                @if($count > 0)
                    <div class="flex items-center justify-between text-sm">
                        <span class="capitalize text-slate-600">{{ $status }}</span>
                        <span class="font-bold text-navy-800">{{ $count }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Recent Orders</h3>
        <div class="space-y-3">
            @forelse($recentOrders as $o)
                <a href="{{ route('admin.orders.show', $o->id) }}" class="flex items-center justify-between rounded-xl px-2 py-1.5 hover:bg-slate-50">
                    <div>
                        <p class="font-mono text-xs font-semibold text-navy-800">{{ $o->order_number }}</p>
                        <p class="text-xs text-slate-500">{{ $o->buyer_name }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-navy-800">₱{{ number_format($o->total, 2) }}</p>
                        <x-admin.status-badge :status="$o->status" />
                    </div>
                </a>
            @empty
                <p class="text-sm text-slate-400">No orders yet.</p>
            @endforelse
        </div>
    </div>

    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">New Users (14 Days)</h3>
        @if($newUsersByDay->isEmpty())<p class="text-sm text-slate-400">No signups.</p>
        @else
        <div class="flex h-32 items-end gap-1">
            @foreach($newUsersByDay as $date => $count)
                @php $h = max(4, ($count / max(1, $newUsersByDay->max())) * 100); @endphp
                <div class="flex flex-1 flex-col items-center gap-1" title="{{ $date }}: {{ $count }}">
                    <div class="w-full rounded-t bg-accent-400 hover:bg-accent-500" style="height: {{ $h }}px"></div>
                </div>
            @endforeach
        </div>
        @endif
        <div class="mt-4 border-t border-slate-100 pt-4">
            <a href="{{ route('superadmin.users.index') }}" class="btn-ghost btn-sm w-full">Manage users →</a>
        </div>
    </div>

    <div class="card p-5">
        <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Best Selling</h3>
        <div class="space-y-3">
            @forelse($bestSelling as $p)
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                        @if($p->images->first())<img src="{{ asset('storage/'.$p->images->first()->path) }}" class="h-full w-full object-cover">@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-1 text-sm font-medium text-navy-800">{{ $p->name }}</p>
                        <p class="text-xs text-slate-500">{{ $p->sold_count }} sold</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No data.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
