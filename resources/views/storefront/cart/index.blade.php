@extends('layouts.storefront')

@section('title', 'Shopping Cart')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold text-navy-800">Shopping Cart</h1>

    @if($items->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <svg class="h-20 w-20 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-navy-800">Your cart is empty</h3>
            <p class="mt-1 text-sm text-slate-500">Looks like you haven't added anything yet.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-5">Start Shopping</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            {{-- Items --}}
            <div class="space-y-3">
                <div class="hidden items-center gap-4 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-500 shadow-sm md:flex">
                    <div class="flex-1">Item</div>
                    <div class="w-28 text-center">Price</div>
                    <div class="w-28 text-center">Quantity</div>
                    <div class="w-28 text-center">Subtotal</div>
                    <div class="w-10"></div>
                </div>
                @foreach($items->groupBy(fn ($item) => $item->product->store_id) as $storeItems)
                    @php($store = $storeItems->first()->product->store)
                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-sm">
                        <a href="{{ $store ? route('shops.show', $store->slug) : '#' }}" class="text-sm font-bold text-navy-800 hover:text-brand-600">
                            {{ $store?->name ?? 'SHOPPICK' }}
                        </a>
                    </div>
                @foreach($storeItems as $item)
                    <div class="card flex flex-wrap items-center gap-4 p-4">
                        <input type="checkbox" @checked($item->selected) onchange="toggleSelect('{{ route('cart.toggle', $item->id) }}', this)"
                               class="h-5 w-5 rounded border-slate-300 text-brand-500 focus:ring-brand-300">
                        <a href="{{ route('products.show', $item->product->slug) }}" class="flex flex-1 min-w-[160px] items-center gap-3">
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                @if($item->product->getMainImageAttribute())
                                    <img src="{{ asset('storage/'.$item->product->getMainImageAttribute()) }}" class="h-full w-full object-cover" alt="">
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="line-clamp-2 text-sm font-medium text-navy-800 hover:text-brand-600">{{ $item->product->name }}</p>
                                @if($item->variant)<p class="mt-0.5 text-xs text-slate-500">{{ $item->variant->getLabelAttribute() }}</p>@endif
                                <p class="mt-1 text-xs {{ $item->quantity > $item->availableStock() ? 'font-semibold text-rose-600' : 'text-leaf-600' }}">
                                    @if($item->quantity > $item->availableStock())
                                        Only {{ $item->availableStock() }} items are currently available.
                                    @elseif($item->availableStock() > 0)
                                        In stock
                                    @else
                                        Out of stock
                                    @endif
                                </p>
                            </div>
                        </a>
                        <div class="w-28 text-center text-sm text-navy-800">₱{{ number_format($item->unitPrice(), 2) }}</div>
                        <div class="w-28 text-center">
                            <div class="inline-flex items-center rounded-xl border border-slate-300">
                                <button type="button" onclick="updateQty('{{ route('cart.update', $item->id) }}', {{ $item->quantity - 1 }})" class="px-2 py-1 text-slate-500 hover:text-brand-600">−</button>
                                <span class="w-10 text-center text-sm font-semibold">{{ $item->quantity }}</span>
                                <button type="button" onclick="updateQty('{{ route('cart.update', $item->id) }}', {{ $item->quantity + 1 }})" class="px-2 py-1 text-slate-500 hover:text-brand-600">+</button>
                            </div>
                        </div>
                        <div class="w-28 text-center font-bold text-navy-800">₱{{ number_format($item->lineTotal(), 2) }}</div>
                        <div class="w-10">
                            <form method="POST" action="{{ route('cart.remove', $item->id) }}">
                                @csrf
                                <button type="submit" class="p-1 text-slate-400 hover:text-rose-500" title="Remove">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
                @endforeach
            </div>

            {{-- Summary --}}
            <div class="card h-fit p-5">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Order Summary</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-semibold text-navy-800">₱{{ number_format($subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-500">Shipping</span><span class="font-semibold text-navy-800">{{ $shipping > 0 ? '₱'.number_format($shipping, 2) : 'Free' }}</span></div>
                    <div class="border-t border-slate-100 pt-3 flex justify-between text-base font-bold"><span>Total</span><span>₱{{ number_format($subtotal + $shipping, 2) }}</span></div>
                </div>
                <a href="{{ route('checkout') }}" class="btn-accent mt-5 w-full">Proceed to Checkout</a>
                <a href="{{ route('products.index') }}" class="btn-ghost mt-2 w-full">Continue Shopping</a>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function updateQty(url, qty) {
        if (qty < 1) qty = 0;
        fetch(url, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({quantity: qty})
        }).then(r => r.json()).then(() => location.reload());
    }
    function toggleSelect(url, cb) {
        fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}})
            .then(() => location.reload());
    }
</script>
@endpush
