@extends('layouts.storefront')

@section('title', 'Order '.$order->order_number)

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-navy-800">Order {{ $order->order_number }}</h1>
            <p class="text-sm text-slate-500">Placed on {{ $order->created_at->format('F d, Y h:i A') }}</p>
        </div>
        @php $badge = ['pending' => ['bg-sun-100','text-sun-500'], 'confirmed'=>['bg-brand-100','text-brand-700'],'processing'=>['bg-brand-100','text-brand-700'],'packed'=>['bg-brand-100','text-brand-700'],'shipped'=>['bg-brand-100','text-brand-700'],'delivered'=>['bg-brand-100','text-brand-700'],'completed'=>['bg-leaf-100','text-leaf-500'],'cancelled'=>['bg-rose-100','text-rose-600'],'refunded'=>['bg-rose-100','text-rose-600']]; @endphp
        <span class="badge {{ ($badge[$order->status] ?? ['bg-slate-100','text-slate-500'])[0] }} {{ ($badge[$order->status] ?? ['bg-slate-100','text-slate-500'])[1] }}">{{ ucfirst($order->status) }}</span>
    </div>

    {{-- Timeline --}}
    @php
        $sequence = ['pending','confirmed','processing','packed','shipped','delivered','completed'];
        $idx = array_search($order->status, $sequence);
        $showSteps = $order->status !== 'cancelled' && $order->status !== 'refunded';
    @endphp
    @if($showSteps)
    <div class="card mb-6 p-5">
        <ol class="flex items-center">
            @foreach($sequence as $i => $step)
                @php $done = $idx !== false && $i <= $idx; $isLast = $loop->last; @endphp
                <li class="flex flex-1 {{ $isLast ? '' : '' }} items-center">
                    <div class="flex flex-col items-center">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $done ? 'bg-brand-500 text-white' : 'bg-slate-100 text-slate-400' }}">
                            @if($done)<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>@else{{ $i + 1 }}@endif
                        </span>
                        <span class="mt-1 text-[11px] text-slate-500">{{ ucfirst($step) }}</span>
                    </div>
                    @if(!$isLast)<div class="mx-2 h-0.5 flex-1 rounded {{ $i < $idx ? 'bg-brand-400' : 'bg-slate-200' }}"></div>@endif
                </li>
            @endforeach
        </ol>
    </div>
    @endif

    {{-- Status actions --}}
    @if($order->canBeCancelled())
        <div class="card mb-6 flex flex-wrap items-center gap-3 border-rose-100 bg-rose-50/50 p-4">
            <p class="text-sm text-slate-600">You can still cancel this order.</p>
            <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}" class="ml-auto" onsubmit="return confirm('Cancel this order?')">
                @csrf
                <input type="hidden" name="reason" value="Cancelled by buyer">
                <button type="submit" class="btn-danger btn-sm">Cancel Order</button>
            </form>
        </div>
    @endif
    @if(in_array($order->status, ['shipped', 'delivered']))
        <form method="POST" action="{{ route('orders.confirm', $order->order_number) }}" class="card mb-6 flex items-center justify-between gap-3 border-brand-100 bg-brand-50/50 p-4">
            @csrf
            <p class="text-sm text-slate-700">Did you receive your order?</p>
            <button type="submit" class="btn-primary btn-sm">Confirm Received</button>
        </form>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-1">
        <h2 class="text-sm font-bold uppercase tracking-wide text-navy-800">Items</h2>
    </div>

    {{-- Items --}}
    <div class="space-y-3">
        @foreach($order->items as $item)
            <div class="card flex flex-wrap items-center gap-4 p-4">
                <a href="{{ route('products.show', $item->product?->slug ?? '#') }}" class="flex flex-1 min-w-[200px] items-center gap-3">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                        @if($item->product_image)<img src="{{ asset('storage/'.$item->product_image) }}" class="h-full w-full object-cover">@endif
                    </div>
                    <div class="min-w-0">
                        <p class="line-clamp-2 text-sm font-medium text-navy-800">{{ $item->product_name }}</p>
                        @if($item->variant_label)<p class="text-xs text-slate-500">{{ $item->variant_label }}</p>@endif
                    </div>
                </a>
                <div class="text-right">
                    <p class="text-sm text-slate-500">₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}</p>
                    <p class="font-bold text-navy-800">₱{{ number_format($item->total, 2) }}</p>
                </div>
                @if($order->status === 'completed')
                    <div>
                        @php $reviewed = $order->reviews->where('product_id', $item->product_id)->isNotEmpty(); @endphp
                        @if($reviewed)
                            <span class="badge bg-leaf-100 text-leaf-500">Reviewed</span>
                        @else
                            <a href="{{ route('review.create', [$order->order_number, $item->product_id]) }}" class="btn-accent btn-sm">Write Review</a>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Totals --}}
    <div class="card mt-6 p-5">
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-navy-800">Order Summary</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>₱{{ number_format($order->subtotal, 2) }}</span></div>
            @if($order->voucher_discount > 0)<div class="flex justify-between"><span class="text-slate-500">Voucher ({{ $order->voucher?->code }})</span><span class="text-leaf-500">−₱{{ number_format($order->voucher_discount, 2) }}</span></div>@endif
            <div class="flex justify-between"><span class="text-slate-500">Shipping</span><span>{{ $order->shipping_fee > 0 ? '₱'.number_format($order->shipping_fee, 2) : 'Free' }}</span></div>
            <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold"><span>Total</span><span>₱{{ number_format($order->total, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Payment Method</span><span class="capitalize">{{ str_replace('_',' ', $order->payment_method) }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Payment Status</span><span class="capitalize font-semibold {{ $order->payment_status === 'paid' ? 'text-leaf-500' : 'text-sun-500' }}">{{ $order->payment_status }}</span></div>
        </div>
    </div>

    {{-- Shipping address --}}
    @if($order->shipping_address)
    <div class="card mt-4 p-5">
        <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-navy-800">Shipping Address</h3>
        <p class="text-sm text-navy-800">{{ $order->buyer_name }}</p>
        <p class="text-sm text-slate-600">{{ $order->buyer_phone }}</p>
        <p class="mt-1 text-sm text-slate-600">{{ $order->shipping_address['address_line'] ?? '' }}, {{ $order->shipping_address['barangay'] ?? '' }}, {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['province'] ?? '' }} {{ $order->shipping_address['postal_code'] ?? '' }}</p>
        @if($order->note)<p class="mt-2 text-xs text-slate-500">Note: {{ $order->note }}</p>@endif
    </div>
    @endif
</div>
@endsection
