@extends('layouts.account')

@section('title', 'My Profile')

@section('account-content')
@php
    $sellerAction = $user->sellerAction();
@endphp
<div class="mb-6">
    <p class="text-sm font-semibold text-brand-600">Buyer Account</p>
    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-navy-900">Good to see you, {{ str($user->name)->before(' ') }}</h1>
    <p class="mt-2 text-sm text-slate-500">Manage your orders, account details, addresses, and seller application.</p>
</div>

<section class="relative mb-6 overflow-hidden rounded-2xl border border-brand-100 bg-brand-50/80 p-6 sm:p-7">
    <div class="relative z-10 max-w-2xl"><span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-xl text-accent-500 shadow-sm">✦</span><h2 class="mt-4 text-xl font-extrabold text-navy-900">{{ $sellerAction['label'] }}</h2><p class="mt-2 max-w-xl text-sm leading-6 text-slate-600">{{ $sellerAction['description'] }}</p><a class="btn-primary mt-5" href="{{ $sellerAction['url'] }}">{{ $sellerAction['label'] }}</a></div>
    <div class="pointer-events-none absolute -right-8 -top-10 h-36 w-36 rounded-full border-[18px] border-white/70"></div><div class="pointer-events-none absolute -bottom-16 right-24 h-32 w-32 rounded-full bg-accent-100/70"></div>
</section>

<div class="card p-6 sm:p-7">
    <div class="mb-6"><h2 class="text-xl font-extrabold text-navy-900">My Profile</h2><p class="mt-1 text-sm text-slate-500">Keep your personal information up to date.</p></div>

    <form id="profile-form" method="POST" action="{{ route('account.update') }}" enctype="multipart/form-data" class="mt-6">
        @csrf
        <div class="mb-7 flex flex-wrap items-center gap-4 border-b border-slate-100 pb-6">
            <div id="avatar-preview" class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-700 ring-8 ring-brand-50">
                @if(auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ $user->name }} profile photo" class="h-full w-full object-cover">
                @else
                    <span class="text-3xl font-extrabold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span>
                @endif
            </div>
            <div><p class="font-bold text-navy-900">Profile photo</p><p class="mt-1 text-sm text-slate-500">Use a JPG, PNG, GIF, or WEBP up to 2 MB.</p><label class="mt-3 inline-block cursor-pointer">
                <input id="avatar-input" type="file" name="avatar" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden">
                <span class="btn-outline btn-sm">{{ $user->avatar ? 'Change Photo' : 'Upload Photo' }}</span>
            </label><p id="avatar-file-name" class="mt-2 hidden text-xs font-medium text-brand-700"></p><p class="mt-2 text-xs text-slate-400">Selecting a photo uploads it to your account.</p></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" class="input">
            </div>
            <div>
                <label class="label">Email</label>
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="input">
            </div>
            <div>
                <label class="label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="input">
            </div>
            <div>
                <label class="label">Member Since</label>
                <input type="text" value="{{ auth()->user()->created_at->format('M d, Y') }}" class="input" disabled>
            </div>
        </div>

        <button type="submit" class="btn-primary mt-6">Save Changes</button>
    </form>
</div>

{{-- Recent orders --}}
<div class="card mt-6 overflow-hidden p-6 sm:p-7">
    <div class="mb-4 flex items-center justify-between">
        <div><h2 class="text-lg font-extrabold text-navy-900">Recent Orders</h2><p class="mt-1 text-sm text-slate-500">Your latest purchases at a glance.</p></div>
        <a href="{{ route('orders.index') }}" class="hidden text-sm font-bold text-brand-600 hover:text-brand-800 sm:inline">View All Orders <span class="transition group-hover:translate-x-1">→</span></a>
    </div>
    @php
        $statusBadges = [
                'pending' => 'bg-amber-50 text-amber-700',
                'confirmed' => 'bg-slate-100 text-slate-700',
                'processing' => 'bg-blue-50 text-blue-700',
                'packed' => 'bg-brand-50 text-brand-700',
                'ready_to_ship' => 'bg-cyan-50 text-cyan-700',
                'shipped' => 'bg-blue-50 text-blue-700',
                'delivered' => 'bg-leaf-50 text-leaf-700',
                'completed' => 'bg-leaf-50 text-leaf-700',
                'cancelled' => 'bg-rose-50 text-rose-700',
                'refunded' => 'bg-rose-50 text-rose-700',
        ];
    @endphp
    @forelse($orders as $order)
                @php
                    $firstItem = $order->items->first();
                    $shop = $order->sellerOrders->first()?->store;
                    $productImage = $firstItem?->product_image ?: $firstItem?->product?->main_image;
                    $shopName = $shop?->name ?: 'SHOPPICK Marketplace';
                    $shopInitial = strtoupper(substr($shopName, 0, 1));
                    $itemCount = $order->items->count();
                    $statusClass = $statusBadges[$order->status] ?? 'bg-slate-100 text-slate-700';
                @endphp
            <article class="group grid gap-4 rounded-2xl px-2 py-5 transition first:pt-2 hover:bg-brand-50/50 sm:grid-cols-[minmax(0,1.5fr)_minmax(150px,.7fr)_auto_auto_auto] sm:items-center sm:px-3">
                    <a href="{{ route('orders.show', $order->order_number) }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                            @if($productImage)
                                <img src="{{ asset('storage/'.$productImage) }}" alt="{{ $firstItem?->product_name ?: 'Ordered product' }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-slate-300"><svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.6" d="m4 16 4-4 4 4 4-5 4 5M5 20h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1Z"/></svg></div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-bold text-navy-900">{{ $firstItem?->product_name ?: 'Order items' }}</p>
                            <p class="mt-1 truncate font-mono text-xs text-slate-500">#{{ $order->order_number }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $order->created_at->format('M d, Y') }}@if($itemCount > 1) <span class="text-slate-300">·</span> +{{ $itemCount - 1 }} more {{ str('item')->plural($itemCount - 1) }}@endif</p>
                        </div>
                    </a>
                    <div class="flex items-center gap-2 sm:min-w-0">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-brand-50 text-sm font-extrabold text-brand-700">@if($shop?->logo)<img src="{{ asset('storage/'.$shop->logo) }}" alt="{{ $shopName }} logo" class="h-full w-full object-cover">@else{{ $shopInitial }}@endif</span>
                        <span class="truncate text-sm font-semibold text-slate-600">{{ $shopName }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 sm:block sm:min-w-[92px] sm:text-right"><span class="text-xs text-slate-400 sm:hidden">Total</span><span class="font-extrabold text-navy-900">₱{{ number_format($order->total, 2) }}</span></div>
                    <div class="flex items-center justify-between gap-3 sm:block sm:min-w-[112px] sm:text-center"><span class="text-xs text-slate-400 sm:hidden">Status</span><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ str($order->status)->replace('_',' ')->title() }}</span></div>
                    <a href="{{ route('orders.show', $order->order_number) }}" class="font-bold text-brand-600 transition hover:text-brand-800 sm:whitespace-nowrap sm:text-right">View Order <span class="inline-block transition group-hover:translate-x-0.5">→</span></a>
        </article>
    @empty
        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center"><p class="font-semibold text-navy-800">No orders yet</p><p class="mt-1 text-sm text-slate-500">When you place an order, it will appear here.</p><a href="{{ route('products.index') }}" class="btn-primary mt-4">Start Shopping</a></div>
    @endforelse
    @if($orders->isNotEmpty())<a href="{{ route('orders.index') }}" class="mt-4 inline-flex text-sm font-bold text-brand-600 sm:hidden">View All Orders →</a>@endif
</div>
@endsection

@push('scripts')
<script>
    const avatarInput = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarFileName = document.getElementById('avatar-file-name');
    const profileForm = document.getElementById('profile-form');

    avatarInput?.addEventListener('change', () => {
        const file = avatarInput.files?.[0];
        if (!file) return;

        avatarFileName.textContent = `${file.name} selected. Uploading...`;
        avatarFileName.classList.remove('hidden');

        const reader = new FileReader();
        reader.onload = event => {
            avatarPreview.innerHTML = `<img src="${event.target.result}" alt="Selected profile photo" class="h-full w-full object-cover">`;
        };
        reader.readAsDataURL(file);
        profileForm.submit();
    });
</script>
@endpush
