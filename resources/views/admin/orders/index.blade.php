@extends('layouts.admin')

@section('title', 'Orders')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Orders</h1>
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.orders.index', request()->except(['status','page'])) }}" class="chip {{ !request('status') ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">All ({{ $allOrdersCount }})</a>
        @foreach($statusCounts as $status => $count)
            <a href="{{ route('admin.orders.index', array_merge(request()->except(['status','page']), ['status'=>$status])) }}" class="chip {{ request('status') === $status ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}">{{ ucwords(str_replace('_',' ',$status)) }} ({{ $count }})</a>
        @endforeach
    </div>
</div>

<form method="GET" action="{{ route('admin.orders.index') }}" class="mb-5 flex flex-wrap gap-3">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search order #, buyer, or email..." class="input !w-72">
    <select name="shop_id" class="input !w-auto" onchange="this.form.submit()">
        <option value="">All shops</option>
        @foreach($shops as $shop)<option value="{{ $shop->id }}" @selected((string)request('shop_id') === (string)$shop->id)>{{ $shop->name }}</option>@endforeach
        <option value="unassigned" @selected(request('shop_id') === 'unassigned')>Unassigned / Legacy Orders</option>
    </select>
    <button type="submit" class="btn-primary">Search</button>
</form>

@if($orderGroups->isNotEmpty())
    <div class="mb-3 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Grouped by Seller Shop · {{ $orders->total() }} matching Seller Order(s)</p>
        <div class="flex gap-3 text-xs font-semibold"><button type="button" class="text-brand-600 hover:text-brand-700" @click="$dispatch('orders-expand-all')">Expand All</button><button type="button" class="text-slate-500 hover:text-navy-800" @click="$dispatch('orders-collapse-all')">Collapse All</button></div>
    </div>
    <div class="space-y-4">
        @foreach($orderGroups as $group)
            @php($panelId = 'shop-orders-'.($group->store?->id ?? 'unassigned'))
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" x-data="{open:{{ $loop->first ? 'true' : 'false' }}}" @orders-expand-all.window="open=true" @orders-collapse-all.window="open=false">
                <div class="flex flex-wrap items-center gap-3 border-l-4 px-4 py-4 transition" :class="open ? 'border-brand-500 bg-brand-50/40' : 'border-transparent bg-white'">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="open=!open" :aria-expanded="open.toString()" aria-controls="{{ $panelId }}">
                        <svg class="h-5 w-5 shrink-0 text-brand-600 transition" :class="open && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <div class="min-w-0"><h2 class="truncate font-bold text-navy-800">{{ $group->store?->name ?? 'Unassigned / Legacy Orders' }}</h2><p class="truncate text-xs text-slate-500">{{ $group->store ? 'Seller: '.($group->store->user?->name ?? 'Unknown Seller') : 'Seller Orders without a valid Shop relationship' }}</p></div>
                    </button>
                    @if($group->stats)<div class="flex flex-wrap gap-1.5 text-xs"><span class="badge bg-slate-100 text-slate-600">Orders: {{ $group->stats->total_count }}</span>@if($group->stats->pending_count)<span class="badge bg-sun-100 text-sun-500">Pending: {{ $group->stats->pending_count }}</span>@endif @if($group->stats->processing_count)<span class="badge bg-brand-100 text-brand-700">Processing: {{ $group->stats->processing_count }}</span>@endif @if($group->stats->delivered_count)<span class="badge bg-brand-100 text-brand-700">Delivered: {{ $group->stats->delivered_count }}</span>@endif @if($group->stats->completed_count)<span class="badge bg-leaf-100 text-leaf-500">Completed: {{ $group->stats->completed_count }}</span>@endif</div>@endif
                    @if($group->store && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermissionTo('view_shops')))<a href="{{ route('admin.shops.show',$group->store) }}" class="btn-outline btn-sm">View Shop</a>@endif
                </div>
                <div id="{{ $panelId }}" x-show="open" x-cloak>
                    <div class="overflow-x-auto border-t border-slate-100">
                        <table class="w-full min-w-[820px]">
                            <thead class="bg-slate-50"><tr><th class="table-th">Order</th><th class="table-th">Buyer</th><th class="table-th">Date</th><th class="table-th">Shop Total</th><th class="table-th">Payment</th><th class="table-th">Status</th><th class="table-th text-right">Action</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($group->orders as $sellerOrder)
                                    @php($effectiveStatus = in_array($sellerOrder->order->status,['cancelled','refunded'],true) ? $sellerOrder->order->status : $sellerOrder->status)
                                    <tr>
                                        <td class="table-td"><p class="font-mono font-semibold text-navy-800">{{ $sellerOrder->order->order_number }}</p><p class="text-xs text-slate-400">{{ $sellerOrder->seller_order_number }} · {{ $sellerOrder->items->sum('quantity') }} item(s)</p></td>
                                        <td class="table-td">{{ $sellerOrder->order->buyer_name }}<p class="text-xs text-slate-400">{{ $sellerOrder->order->user?->email }}</p></td>
                                        <td class="table-td">{{ $sellerOrder->created_at->format('M d, Y') }}</td>
                                        <td class="table-td font-bold">₱{{ number_format($sellerOrder->subtotal + $sellerOrder->shipping_fee - $sellerOrder->discount,2) }}</td>
                                        <td class="table-td"><span class="capitalize text-slate-600">{{ str_replace('_',' ',$sellerOrder->order->payment_method) }}</span><br><x-admin.status-badge :status="$sellerOrder->order->payment_status"/></td>
                                        <td class="table-td"><x-admin.status-badge :status="$effectiveStatus"/></td>
                                        <td class="table-td text-right"><a href="{{ route('admin.orders.show',$sellerOrder->order_id) }}" class="btn-outline btn-sm">View</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@else
    <div class="card py-12 text-center text-slate-400">No orders found.</div>
@endif
<div class="mt-4">{{ $orders->links('components.pagination') }}</div>
@endsection
