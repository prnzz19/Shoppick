@extends('layouts.storefront')

@section('title', 'My Orders')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold text-navy-800">My Orders</h1>

    {{-- Tabs --}}
    <div class="mb-6 flex gap-2 overflow-x-auto border-b border-slate-200 pb-0">
        @php
            $tabs = ['all' => 'All', 'to_pay' => 'To Pay', 'to_ship' => 'To Ship', 'to_receive' => 'To Receive', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('orders.index', ['tab' => $key]) }}" class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $tab === $key ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-navy-800' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($orders->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <svg class="h-16 w-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-navy-800">No orders here yet</h3>
            <p class="mt-1 text-sm text-slate-500">When you place an order, it will appear here.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Start Shopping</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <a href="{{ route('orders.show', $order->order_number) }}" class="card block p-4 transition hover:shadow-md">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-mono font-semibold text-navy-800">{{ $order->order_number }}</span>
                            <span class="text-slate-400">•</span>
                            <span class="text-slate-500">{{ $order->created_at->format('M d, Y') }}</span>
                        </div>
                        @php $badge = ['pending' => ['bg-sun-100','text-sun-500'], 'confirmed'=>['bg-brand-100','text-brand-700'],'processing'=>['bg-brand-100','text-brand-700'],'packed'=>['bg-brand-100','text-brand-700'],'shipped'=>['bg-brand-100','text-brand-700'],'delivered'=>['bg-brand-100','text-brand-700'],'completed'=>['bg-leaf-100','text-leaf-500'],'cancelled'=>['bg-rose-100','text-rose-600'],'refunded'=>['bg-rose-100','text-rose-600']]; @endphp
                        <span class="badge {{ ($badge[$order->status] ?? ['bg-slate-100','text-slate-500'])[0] }} {{ ($badge[$order->status] ?? ['bg-slate-100','text-slate-500'])[1] }}">{{ ucfirst($order->status) }}</span>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex -space-x-2">
                            @foreach($order->items->take(4) as $item)
                                <div class="h-11 w-11 overflow-hidden rounded-lg border-2 border-white bg-slate-100">
                                    @if($item->product_image)<img src="{{ asset('storage/'.$item->product_image) }}" class="h-full w-full object-cover">@endif
                                </div>
                            @endforeach
                            @if($order->items->count() > 4)<span class="flex h-11 w-11 items-center justify-center rounded-lg border-2 border-white bg-slate-100 text-xs font-semibold text-slate-500">+{{ $order->items->count() - 4 }}</span>@endif
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500">{{ $order->items->count() }} item(s)</p>
                            <p class="font-bold text-navy-800">₱{{ number_format($order->total, 2) }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $orders->links('components.pagination') }}</div>
    @endif
</div>
@endsection
