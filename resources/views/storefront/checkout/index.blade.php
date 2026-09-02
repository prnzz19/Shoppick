@extends('layouts.storefront')

@section('title', 'Checkout')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold text-navy-800">Checkout</h1>

    @if($items->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <p class="text-slate-500">Your cart is empty.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Shop Now</a>
        </div>
    @else
    <form method="POST" action="{{ route('checkout.store') }}" id="checkout-form">
        @csrf
        <input type="hidden" name="checkout_mode" value="{{ $checkoutMode }}">
        <input type="hidden" name="address_id" id="selected-address" value="{{ $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id ?? '' }}">
        <input type="hidden" name="payment_method" id="selected-payment" value="cod">
        <input type="hidden" name="voucher_code" id="voucher-code-input" value="{{ session('applied_voucher') ?? '' }}">

        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <div class="space-y-6">
                {{-- Addresses --}}
                <div class="card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-navy-800">Delivery Address</h3>
                        <a href="{{ route('account.addresses') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">Manage addresses</a>
                    </div>

                    @if($addresses->isEmpty())
                        <a href="{{ route('account.addresses') }}" class="btn-outline btn-sm">Add a delivery address</a>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($addresses as $addr)
                                <label class="relative flex cursor-pointer items-start gap-3 rounded-xl border-2 p-3 {{ $addr->is_default ? 'border-brand-400 bg-brand-50' : 'border-slate-200 hover:border-brand-200' }}" onclick="selectAddress({{ $addr->id }}, this)">
                                    <input type="radio" name="address-radio" class="mt-1 h-4 w-4 text-brand-500" @checked($addr->is_default)>
                                    <div>
                                        <p class="text-sm font-semibold text-navy-800">{{ $addr->full_name }} @if($addr->label)<span class="badge bg-brand-100 text-brand-600 ml-1">{{ $addr->label }}</span>@endif</p>
                                        <p class="text-xs text-slate-500">{{ $addr->phone }}</p>
                                        <p class="mt-1 text-xs text-slate-600">{{ $addr->address_line }}, {{ $addr->barangay }}, {{ $addr->city }}, {{ $addr->province }} {{ $addr->postal_code }}</p>
                                        @if($addr->is_default)<span class="badge bg-leaf-100 text-leaf-500 mt-1">Default</span>@endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Payment method --}}
                <div class="card p-5">
                    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Payment Method</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($paymentMethods as $key => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3 border-slate-200 hover:border-brand-200 payment-option" data-method="{{ $key }}" onclick="selectPayment('{{ $key }}', this)">
                                <input type="radio" name="payment-radio" class="h-4 w-4 text-brand-500" @checked($key === 'cod')>
                                <span class="text-sm font-semibold text-navy-800">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Note: Online payments are simulated for this demo. No card data is stored.</p>
                </div>

                {{-- Note --}}
                <div class="card p-5">
                    <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-navy-800">Order Note (optional)</h3>
                    <textarea name="note" rows="2" class="input" placeholder="Add delivery instructions..."></textarea>
                </div>
            </div>

            {{-- Summary --}}
            <div class="card h-fit p-5">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Order Summary</h3>
                <div class="max-h-52 space-y-3 overflow-y-auto">
                    @foreach($items as $item)
                        <div class="flex items-center gap-3 text-sm">
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                @if($item->product->getMainImageAttribute())<img src="{{ asset('storage/'.$item->product->getMainImageAttribute()) }}" class="h-full w-full object-cover">@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-1 text-xs text-navy-800">{{ $item->product->name }}</p>
                                <p class="text-xs text-slate-500">x{{ $item->quantity }}</p>
                            </div>
                            <span class="font-semibold">₱{{ number_format($item->lineTotal(), 2) }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Voucher --}}
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <div class="flex gap-2">
                        <input type="text" id="voucher-code" placeholder="Enter voucher code" value="{{ session('applied_voucher') ?? '' }}" class="input !py-2 text-sm">
                        <button type="button" onclick="applyVoucher()" class="btn-outline btn-sm shrink-0">Apply</button>
                    </div>
                    @if(session('applied_voucher'))
                        <p class="mt-2 text-xs text-leaf-500">Voucher applied: <strong>{{ session('applied_voucher') }}</strong> (−₱{{ number_format(session('voucher_discount'), 2) }})</p>
                    @endif
                    @error('voucher_code')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>₱{{ number_format($totals, 2) }}</span></div>
                    @if(session('voucher_discount') || (session('applied_voucher')))
                        <div class="flex justify-between"><span class="text-slate-500">Voucher</span><span class="text-leaf-500">−₱{{ number_format(session('voucher_discount') ?? 0, 2) }}</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-slate-500">Shipping</span><span>{{ $shipping > 0 ? '₱'.number_format($shipping, 2) : 'Free' }}</span></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold"><span>Total</span><span>₱{{ number_format(($totals - (session('voucher_discount') ?? 0)) + $shipping, 2) }}</span></div>
                </div>

                <button type="submit" class="btn-accent mt-5 w-full" @if($addresses->isEmpty()) disabled title="Add a delivery address first" @endif>Place Order</button>
            </div>
        </div>
    </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function selectAddress(id, el) {
        document.getElementById('selected-address').value = id;
        el.closest('div.grid').querySelectorAll('label').forEach(l => {
            l.classList.remove('border-brand-400', 'bg-brand-50'); l.classList.add('border-slate-200');
        });
        el.classList.add('border-brand-400', 'bg-brand-50'); el.classList.remove('border-slate-200');
        el.querySelector('input').checked = true;
    }
    function selectPayment(method, el) {
        document.getElementById('selected-payment').value = method;
        document.querySelectorAll('.payment-option').forEach(l => {
            l.classList.remove('border-brand-400', 'bg-brand-50'); l.classList.add('border-slate-200');
        });
        el.classList.add('border-brand-400', 'bg-brand-50'); el.classList.remove('border-slate-200');
        el.querySelector('input').checked = true;
    }
    function applyVoucher() {
        const code = document.getElementById('voucher-code').value.trim();
        if (!code) return;
        const form = new FormData();
        form.append('voucher_code', code);
        form.append('checkout_mode', '{{ $checkoutMode }}');
        fetch('{{ route("checkout.voucher") }}', {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: form, redirect: 'follow'})
            .then(r => { window.location.href = r.url; });
    }
</script>
@endpush
