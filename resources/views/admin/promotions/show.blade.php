@extends('layouts.admin')

@section('title', 'Platform Voucher '.$voucher->code)

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-sm font-semibold text-brand-600">Promotions · Platform Vouchers</p>
        <h1 class="text-2xl font-bold text-navy-800">{{ $voucher->title }}</h1>
        <p class="font-mono text-sm font-semibold text-slate-500">{{ $voucher->code }}</p>
    </div>
    <a href="{{ route('admin.promotions.index') }}" class="btn-ghost">← Back to Platform Vouchers</a>
</div>

<div class="grid gap-6 lg:grid-cols-[320px_1fr]">
    <div class="card h-fit p-5">
        <h2 class="mb-4 font-bold text-navy-800">Voucher Details</h2>
        <dl class="space-y-3 text-sm">
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Source</dt><dd class="font-semibold">SHOPPICK Platform</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Type</dt><dd class="font-semibold">{{ $voucher->type === 'free_shipping' ? 'FREE SHIPPING' : str($voucher->type)->headline() }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Scope</dt><dd class="font-semibold">All Shops</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Minimum Spend</dt><dd>₱{{ number_format($voucher->min_purchase, 2) }}</dd></div>
            @if($voucher->type !== 'free_shipping')<div><dt class="text-xs uppercase tracking-wide text-slate-400">Discount Value</dt><dd>{{ $voucher->type === 'percent' ? $voucher->value.'%' : '₱'.number_format($voucher->value, 2) }}</dd></div>@endif
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">{{ $voucher->type === 'free_shipping' ? 'Maximum Shipping Discount' : 'Maximum Discount' }}</dt><dd>{{ $voucher->max_discount === null ? 'Unlimited' : '₱'.number_format($voucher->max_discount, 2) }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Usage</dt><dd>{{ $voucher->used_count }} / {{ $voucher->usage_limit ?? 'Unlimited' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Per Buyer</dt><dd>{{ $voucher->per_user_limit ?? 'Unlimited' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Validity</dt><dd>{{ $voucher->starts_at?->format('M d, Y') ?? 'Immediately' }} – {{ $voucher->ends_at?->format('M d, Y') ?? 'No expiry' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Created By</dt><dd>{{ $voucher->creator?->name ?? 'SHOPPICK Admin' }}</dd></div>
        </dl>
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-slate-100 p-5">
            <h2 class="font-bold text-navy-800">Usage History</h2>
            <p class="text-sm text-slate-500">Immutable order usage and platform-funded discount amounts.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px]">
                <thead class="bg-slate-50"><tr><th class="table-th">Buyer</th><th class="table-th">Order</th><th class="table-th">Product Discount</th><th class="table-th">Shipping Support</th><th class="table-th">Used At</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($usages as $usage)
                    <tr><td class="table-td"><p class="font-semibold">{{ $usage->user?->name ?? 'Deleted buyer' }}</p><p class="text-xs text-slate-400">{{ $usage->user?->email }}</p></td><td class="table-td">@if($usage->order)<a class="font-semibold text-brand-600 hover:underline" href="{{ route('admin.orders.show', $usage->order) }}">{{ $usage->order->order_number }}</a>@else — @endif</td><td class="table-td">₱{{ number_format($usage->discount_amount, 2) }}</td><td class="table-td">₱{{ number_format($usage->shipping_discount_amount, 2) }}</td><td class="table-td">{{ ($usage->used_at ?? $usage->created_at)?->format('M d, Y g:i A') }}</td></tr>
                    @empty
                    <tr><td colspan="5" class="table-td py-10 text-center text-slate-400">This voucher has not been used yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($usages->hasPages())<div class="border-t p-4">{{ $usages->links('components.pagination') }}</div>@endif
    </div>
</div>
@endsection
