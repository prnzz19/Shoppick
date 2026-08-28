@extends('layouts.admin')

@section('title', 'Order '.$order->order_number)

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-navy-800">Order {{ $order->order_number }}</h1>
        <p class="text-sm text-slate-500">Placed {{ $order->created_at->format('F d, Y h:i A') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <x-admin.status-badge :status="$order->status" />
        <a href="{{ route('admin.orders.index') }}" class="btn-ghost btn-sm">← Back</a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        {{-- Items --}}
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Items</h3>
            <div class="space-y-3">
                @foreach($order->items as $item)
                    <div class="flex items-center gap-3 border-b border-slate-50 pb-3 last:border-0">
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            @if($item->product_image)<img src="{{ asset('storage/'.$item->product_image) }}" class="h-full w-full object-cover">@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-1 text-sm font-medium text-navy-800">{{ $item->product_name }}</p>
                            @if($item->variant_label)<p class="text-xs text-slate-500">{{ $item->variant_label }}</p>@endif
                        </div>
                        <div class="text-right text-sm">
                            <p class="text-slate-500">₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}</p>
                            <p class="font-bold text-navy-800">₱{{ number_format($item->total, 2) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>₱{{ number_format($order->subtotal, 2) }}</span></div>
                @if($order->voucher_discount > 0)<div class="flex justify-between"><span class="text-slate-500">Voucher</span><span class="text-leaf-500">−₱{{ number_format($order->voucher_discount, 2) }}</span></div>@endif
                <div class="flex justify-between"><span class="text-slate-500">Shipping</span><span>{{ $order->shipping_fee > 0 ? '₱'.number_format($order->shipping_fee, 2) : 'Free' }}</span></div>
                <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold"><span>Total</span><span>₱{{ number_format($order->total, 2) }}</span></div>
            </div>
        </div>

        {{-- Shipping --}}
        @if($order->shipping_address)
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-navy-800">Shipping Address</h3>
            <p class="text-sm text-navy-800">{{ $order->buyer_name }} · {{ $order->buyer_phone }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ $order->shipping_address['address_line'] ?? '' }}, {{ $order->shipping_address['barangay'] ?? '' }}, {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['province'] ?? '' }} {{ $order->shipping_address['postal_code'] ?? '' }}</p>
            @if($order->note)<p class="mt-2 text-xs text-slate-500">Note: {{ $order->note }}</p>@endif
        </div>
        @endif
    </div>

    <div class="space-y-6">
        {{-- Update status --}}
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Update Status</h3>
            <form method="POST" action="{{ route('admin.orders.status', $order->id) }}" class="space-y-3">
                @csrf
                <select name="status" class="input">
                    @foreach(\App\Models\Order::STATUSES as $status)
                        <option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <input type="text" name="reason" placeholder="Reason (for cancel/refund)" class="input">
                <button type="submit" class="btn-accent w-full">Update Status</button>
            </form>
        </div>

        {{-- Payment --}}
        <div class="card p-5">
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-navy-800">Payment</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Method</span><span class="capitalize">{{ $order->payment_method }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Status</span><x-admin.status-badge :status="$order->payment_status" /></div>
                @foreach($order->payments as $payment)
                    <div class="rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
                        Ref: {{ $payment->reference ?? '—' }}<br>
                        {{ $payment->created_at->format('M d, Y h:i A') }}
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
