@extends('layouts.seller')
@section('title','Promotions & Vouchers')
@section('content')
@php
    $defaultScope = old('application_scope', 'entire_shop');
    $defaultType = old('type', 'fixed');
    $createPicker = [
        'scope' => $defaultScope,
        'pickerId' => 'create',
        'selectedProducts' => $selectedProducts->map(fn ($product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'image' => null,
            'category' => null,
        ])->values(),
        'categories' => $categories->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values(),
        'endpoint' => route('seller.marketing.products'),
        'type' => $defaultType,
        'title' => old('title', ''),
        'code' => old('code', ''),
        'discountValue' => old('value', ''),
        'minPurchase' => old('min_purchase', '0'),
        'maxDiscount' => old('max_discount', ''),
        'usageLimit' => old('usage_limit', ''),
        'perUserLimit' => old('per_user_limit', '1'),
        'startsAt' => old('starts_at', ''),
        'endsAt' => old('ends_at', ''),
        'status' => old('status', 'active'),
    ];
    $activeVouchers = $vouchers->filter(fn ($voucher) => $voucher->status === 'active' && $voucher->isActive())->count();
    $scheduledVouchers = $vouchers->filter(fn ($voucher) => $voucher->starts_at && $voucher->starts_at->isFuture())->count();
    $expiredVouchers = $vouchers->filter(fn ($voucher) => $voucher->ends_at && $voucher->ends_at->isPast())->count();
    $totalRedemptions = $vouchers->sum('usages_count');
@endphp
<div class="space-y-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-sm font-semibold text-brand-600">Marketing</p>
            <h1 class="mt-1 text-2xl font-extrabold text-navy-900">Promotions &amp; Vouchers</h1>
            <p class="mt-1 text-sm text-slate-500">Create and manage shop promotions.</p>
        </div>
        <div class="grid w-full max-w-2xl gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Active</p>
                <p class="mt-2 text-2xl font-extrabold text-navy-900">{{ $activeVouchers }}</p>
            </div>
            <div class="card p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Scheduled</p>
                <p class="mt-2 text-2xl font-extrabold text-navy-900">{{ $scheduledVouchers }}</p>
            </div>
            <div class="card p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Expired</p>
                <p class="mt-2 text-2xl font-extrabold text-navy-900">{{ $expiredVouchers }}</p>
            </div>
            <div class="card p-3">
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Redemptions</p>
                <p class="mt-2 text-2xl font-extrabold text-navy-900">{{ $totalRedemptions }}</p>
            </div>
        </div>
    </div>

    <div x-data="voucherProductPicker(@js($createPicker))" class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(300px,0.9fr)]">
        <form method="POST" action="{{ route('seller.marketing.vouchers.store') }}" class="card h-fit space-y-5 p-5 sm:p-6">
            @csrf
            <div>
                <span class="badge bg-brand-50 text-brand-700">Shop Voucher</span>
                <h2 class="mt-2 text-xl font-bold text-navy-900">Create Voucher</h2>
                <p class="mt-1 text-xs text-slate-500">Applies only to {{ auth()->user()->store->name }}.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="label">Voucher Name</label>
                    <input class="input" name="title" x-model="title" value="{{ old('title') }}" required>
                </div>

                <div class="md:col-span-2">
                    <label class="label">Voucher Code</label>
                    <input class="input uppercase" name="code" x-model="code" value="{{ old('code') }}" required>
                </div>

                <div class="md:col-span-2">
                    <label class="label">Voucher Type</label>
                    <select class="input" name="type" x-model="type">
                        <option value="fixed">Fixed Amount Discount</option>
                        <option value="percent">Percentage Discount</option>
                        <option value="free_shipping">Free Shipping</option>
                    </select>
                </div>

                <div x-show="type !== 'free_shipping'" class="md:col-span-1">
                    <label class="label" x-text="type === 'percent' ? 'Discount Percentage' : 'Discount Amount'"></label>
                    <input class="input" type="number" step=".01" min="0" name="value" x-model="discountValue" value="{{ old('value') }}">
                </div>

                <div>
                    <label class="label">Minimum Spend</label>
                    <input class="input" type="number" step=".01" min="0" name="min_purchase" x-model="minPurchase" value="{{ old('min_purchase', 0) }}">
                </div>

                <div class="md:col-span-1">
                    <label class="label" x-text="type === 'free_shipping' ? 'Maximum Shipping Discount' : 'Maximum Discount (optional)'"></label>
                    <input class="input" type="number" step=".01" min="0" name="max_discount" x-model="maxDiscount" value="{{ old('max_discount') }}">
                    <p x-show="type === 'free_shipping'" class="mt-1 text-xs text-slate-400">The Buyer pays any shipping amount above this limit.</p>
                </div>

                <div>
                    <label class="label">Total Usage Limit</label>
                    <input class="input" type="number" min="1" name="usage_limit" x-model="usageLimit" value="{{ old('usage_limit') }}">
                </div>

                <div>
                    <label class="label">Per Buyer</label>
                    <input class="input" type="number" min="1" name="per_user_limit" x-model="perUserLimit" value="{{ old('per_user_limit', 1) }}" required>
                </div>

                <div>
                    <label class="label">Start Date</label>
                    <input class="input" type="datetime-local" name="starts_at" x-model="startsAt" value="{{ old('starts_at') }}">
                </div>

                <div>
                    <label class="label">End Date</label>
                    <input class="input" type="datetime-local" name="ends_at" x-model="endsAt" value="{{ old('ends_at') }}">
                </div>

                <div class="md:col-span-2">
                    <label class="label">Applies To</label>
                    <select class="input" name="application_scope" :value="scope" @change="setScope($event.target.value)">
                        <option value="entire_shop">Entire Shop</option>
                        <option value="selected_products">Selected Products</option>
                    </select>
                </div>

                @include('seller.marketing.product-picker')

                <div class="md:col-span-2">
                    <label class="label">Status</label>
                    <select class="input" name="status" x-model="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <button class="btn-primary w-full">Create Voucher</button>
        </form>

        <aside class="card h-fit p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-navy-900">Voucher Summary</h3>
                <span class="badge bg-slate-100 text-slate-600" x-text="status === 'active' ? 'Active' : 'Inactive'">Active</span>
            </div>

            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Voucher Type</dt>
                    <dd class="font-semibold text-navy-900" x-text="type === 'fixed' ? 'Fixed Amount Discount' : (type === 'percent' ? 'Percentage Discount' : 'Free Shipping')"></dd>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Discount</dt>
                    <dd class="font-semibold text-navy-900" x-text="type === 'percent' ? (discountValue ? discountValue + '%' : '—') : (type === 'free_shipping' ? 'Shipping Credit' : (discountValue ? '₱' + Number(discountValue || 0).toFixed(2) : '—'))"></dd>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Minimum Spend</dt>
                    <dd class="font-semibold text-navy-900" x-text="minPurchase ? '₱' + Number(minPurchase || 0).toFixed(2) : '₱0.00'"></dd>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Scope</dt>
                    <dd class="font-semibold text-navy-900" x-text="scope === 'entire_shop' ? 'Entire Shop' : 'Selected Products'"></dd>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Usage Limit</dt>
                    <dd class="font-semibold text-navy-900" x-text="usageLimit ? usageLimit : 'Unlimited'"></dd>
                </div>
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <dt class="text-slate-500">Per Buyer</dt>
                    <dd class="font-semibold text-navy-900" x-text="perUserLimit || '1'"></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500">Validity</dt>
                    <dd class="font-semibold text-navy-900" x-text="(startsAt || 'Start') + ' – ' + (endsAt || 'End')"></dd>
                </div>
            </dl>
        </aside>
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-navy-900">Existing Vouchers</h2>
            <span class="text-sm text-slate-500">{{ $vouchers->total() }} total</span>
        </div>

        <div class="space-y-3">
            @forelse($vouchers as $voucher)
                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <b class="text-lg text-navy-900">{{ $voucher->code }}</b>
                                <x-admin.status-badge :status="$voucher->isActive() ? 'active' : ($voucher->ends_at?->isPast() ? 'expired' : $voucher->status)"/>
                            </div>
                            <p class="mt-1 font-semibold text-navy-800">{{ $voucher->title }}</p>
                            <p class="text-sm text-slate-500">{{ $voucher->type === 'free_shipping' ? 'Free Shipping up to ₱' . number_format($voucher->max_discount ?? 0, 2) : ($voucher->type === 'percent' ? $voucher->value . '% OFF' : '₱' . number_format($voucher->value, 2) . ' OFF') }} · Min ₱{{ number_format($voucher->min_purchase, 2) }}</p>
                            <p class="mt-1 text-xs text-slate-400">Used {{ $voucher->usages_count }}{{ $voucher->usage_limit ? ' / ' . $voucher->usage_limit : '' }} · Per Buyer {{ $voucher->per_user_limit }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('seller.marketing.vouchers.toggle', $voucher) }}">
                                @csrf
                                <button class="btn-outline btn-sm">{{ $voucher->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </div>
                    </div>

                    <details class="mt-4 border-t pt-3">
                        <summary class="cursor-pointer text-sm font-semibold text-brand-600">Edit / View Usage</summary>
                        @php
                            $editPicker = [
                                'scope' => $voucher->application_scope ?? 'entire_shop',
                                'pickerId' => 'edit-' . $voucher->id,
                                'selectedProducts' => $voucher->products->map(fn ($product) => [
                                    'id' => $product->id,
                                    'name' => $product->name,
                                    'sku' => $product->sku,
                                    'image' => $product->primaryImage?->url,
                                    'category' => $product->category?->name,
                                ])->values(),
                                'categories' => $createPicker['categories'],
                                'endpoint' => $createPicker['endpoint'],
                            ];
                        @endphp
                        <form method="POST" action="{{ route('seller.marketing.vouchers.update', $voucher) }}" class="mt-4 grid gap-3 sm:grid-cols-2" x-data="voucherProductPicker(@js($editPicker))">
                            @csrf
                            @method('PUT')
                            <input class="input" name="title" value="{{ $voucher->title }}" required>
                            <input class="input uppercase" name="code" value="{{ $voucher->code }}" required>
                            <select class="input" name="type">
                                @foreach(['fixed' => 'Fixed Amount', 'percent' => 'Percentage', 'free_shipping' => 'Free Shipping'] as $key => $label)
                                    <option value="{{ $key }}" @selected($voucher->type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input class="input" type="number" step=".01" min="0" name="value" value="{{ $voucher->value }}">
                            <input class="input" type="number" step=".01" min="0" name="min_purchase" value="{{ $voucher->min_purchase }}">
                            <input class="input" type="number" step=".01" min="0" name="max_discount" value="{{ $voucher->max_discount }}">
                            <input class="input" type="number" min="1" name="usage_limit" value="{{ $voucher->usage_limit }}">
                            <input class="input" type="number" min="1" name="per_user_limit" value="{{ $voucher->per_user_limit }}" required>
                            <input class="input" type="datetime-local" name="starts_at" value="{{ $voucher->starts_at?->format('Y-m-d\TH:i') }}">
                            <input class="input" type="datetime-local" name="ends_at" value="{{ $voucher->ends_at?->format('Y-m-d\TH:i') }}">
                            <select class="input" name="application_scope" :value="scope" @change="setScope($event.target.value)">
                                <option value="entire_shop" @selected($voucher->application_scope === 'entire_shop')>Entire Shop</option>
                                <option value="selected_products" @selected($voucher->application_scope === 'selected_products')>Selected Products</option>
                            </select>
                            @include('seller.marketing.product-picker')
                            <input type="hidden" name="status" value="{{ $voucher->status }}">
                            <button class="btn-primary sm:col-span-2">Save Changes</button>
                        </form>

                        <div class="mt-4">
                            <p class="text-xs font-bold uppercase text-slate-400">Usage History</p>
                            @forelse($voucher->usages->take(8) as $usage)
                                <p class="mt-2 text-xs text-slate-600">Buyer #{{ $usage->user_id }} · {{ $usage->order?->order_number }} · ₱{{ number_format($usage->discount_amount + $usage->shipping_discount_amount, 2) }} · {{ ($usage->used_at ?? $usage->created_at)->format('M d, Y') }}</p>
                            @empty
                                <p class="mt-2 text-xs text-slate-400">Not used yet.</p>
                            @endforelse
                        </div>
                    </details>
                </article>
            @empty
                <div class="card p-10 text-center">
                    <p class="font-semibold text-navy-900">No vouchers</p>
                    <p class="text-sm text-slate-500">Create your first store voucher.</p>
                </div>
            @endforelse
        </div>

        {{ $vouchers->links() }}
    </div>
</div>
@endsection
