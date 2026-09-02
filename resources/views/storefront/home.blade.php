@extends('layouts.storefront')

@section('title', "Home")

@section('content')
    {{-- Hero --}}
    <section class="hero-gradient relative overflow-hidden">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:py-14">
            <div class="grid items-center gap-8 lg:grid-cols-2">
                <div class="text-white">
                    <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur">
                        <span class="h-2 w-2 rounded-full bg-sun-300 animate-pulse"></span> Daily Deals Live
                    </span>
                    <h1 class="mt-4 text-3xl font-extrabold leading-tight sm:text-5xl">
                        Shop Smarter,<br>
                        <span class="text-sun-300">Waddle</span> Your Way to Deals!
                    </h1>
                    <p class="mt-3 max-w-md text-sm text-slate-100 sm:text-base">
                        Discover thousands of products with exclusive SHOPPICK discounts. Fresh arrivals, flash sales, and happy shopping every day.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('products.index') }}" class="btn bg-sun-400 text-navy-900 hover:bg-sun-300">Shop Now</a>
                        <a href="{{ route('products.index', ['discount' => 1]) }}" class="btn border border-white/40 bg-white/10 text-white hover:bg-white/20 backdrop-blur">View Flash Deals</a>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <div class="mx-auto max-w-md rounded-3xl bg-white/10 p-6 text-center shadow-sm backdrop-blur sm:p-8">
                        <x-shoppick.logo class="mx-auto h-32 w-32 transition duration-500 hover:scale-[1.03] sm:h-40 sm:w-40" />
                        <p class="mt-6 text-2xl font-semibold tracking-[0.01em] text-white sm:text-[28px] lg:text-4xl" style="font-family: 'Fredoka', sans-serif;">Too cute to scroll past! 🐾</p>
                        <p class="mt-3 text-sm font-normal leading-relaxed text-slate-100/90 sm:text-base lg:text-lg">Find your next favorite pick on SHOPPICK.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="mx-auto max-w-7xl px-4 py-10">
        <x-section-heading title="Shop by Category" subtitle="What are you looking for today?" link="{{ route('products.index') }}" linkText="All categories" />
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-9">
            @foreach($categories as $cat)
                <a href="{{ route('products.category', $cat->id) }}" class="group flex flex-col items-center gap-2 rounded-2xl border border-slate-100 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full transition group-hover:scale-105">
                        <x-category-visual :category="$cat" />
                    </span>
                    <span class="text-center text-xs font-medium text-navy-700">{{ $cat->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Latest products --}}
    @if($latestProducts->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 pb-10">
        <x-section-heading title="Latest Products" subtitle="Fresh picks from SHOPPICK sellers" link="{{ route('products.index', ['sort' => 'latest']) }}" linkText="View all" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach($latestProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    {{-- Flash deals --}}
    @if($flashDeals->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 pb-10">
        <div class="card p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="tag-gradient rounded-xl px-3 py-1.5 text-sm font-bold text-white">⚡ Flash Deals</span>
                    <div id="flash-deal-timer" class="flex items-center gap-1 text-sm font-semibold text-accent-600">
                        <span>Ends in</span>
                        <span class="rounded-lg bg-accent-100 px-2 py-0.5 tabular-nums" data-timer-h></span>:
                        <span class="rounded-lg bg-accent-100 px-2 py-0.5 tabular-nums" data-timer-m></span>:
                        <span class="rounded-lg bg-accent-100 px-2 py-0.5 tabular-nums" data-timer-s></span>
                    </div>
                </div>
                <a href="{{ route('products.index', ['discount' => 1]) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">View all →</a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                @foreach($flashDeals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- Voucher strip --}}
    @if($vouchers->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 pb-10">
        <x-section-heading title="Grab Vouchers" subtitle="Save more on your next order" />
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($vouchers as $voucher)
                <div class="card overflow-hidden">
                    <div class="flex items-stretch">
                        <div class="tag-gradient flex flex-col items-center justify-center px-4 text-white">
                            <span class="text-xl font-extrabold">{{ $voucher->type === 'percent' ? $voucher->value.'%' : '₱'.number_format($voucher->value) }}</span>
                            <span class="text-[10px] uppercase">OFF</span>
                        </div>
                        <div class="flex flex-1 flex-col justify-center p-3">
                            <p class="text-sm font-semibold text-navy-800">{{ $voucher->title }}</p>
                            <p class="text-xs text-slate-500">Code: <span class="font-mono font-bold text-brand-600">{{ $voucher->code }}</span></p>
                            @if($voucher->min_purchase > 0)<p class="text-[11px] text-slate-400">Min spend ₱{{ number_format($voucher->min_purchase) }}</p>@endif
                            <button type="button" onclick="copyVoucher('{{ $voucher->code }}')" class="mt-1.5 self-start rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-600 hover:bg-brand-100">Copy Code</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Featured --}}
    @if($featured->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 pb-10">
        <x-section-heading title="Featured Picks" subtitle="Handpicked favorites for you" link="{{ route('products.index', ['sort' => 'popular']) }}" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
            @foreach($featured as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
    @endif

    {{-- Popular --}}
    @if($popular->isNotEmpty())
    <section class="bg-brand-50/60 py-10">
        <div class="mx-auto max-w-7xl px-4">
            <x-section-heading title="🔥 Popular Right Now" subtitle="Best-sellers loved by thousands" link="{{ route('products.index', ['sort' => 'popular']) }}" />
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach($popular as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>
    @endif

@endsection

@push('scripts')
<script>
    function copyVoucher(code) {
        navigator.clipboard.writeText(code).then(() => window.showToast('Voucher code copied!', 'success'));
    }
    (function startTimer() {
        const h = document.querySelector('[data-timer-h]');
        const m = document.querySelector('[data-timer-m]');
        const s = document.querySelector('[data-timer-s]');
        if (!h || !m || !s) return;
        const target = new Date(Date.now() + (4 * 3600 + 32 * 60) * 1000);
        function tick() {
            const diff = Math.max(0, target - Date.now());
            const hrs = Math.floor(diff / 3600000);
            const mins = Math.floor((diff % 3600000) / 60000);
            const secs = Math.floor((diff % 60000) / 1000);
            h.textContent = String(hrs).padStart(2, '0');
            m.textContent = String(mins).padStart(2, '0');
            s.textContent = String(secs).padStart(2, '0');
        }
        tick();
        setInterval(tick, 1000);
    })();
</script>
@endpush
