<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Account') · SHOPPICK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen flex flex-col bg-slate-50">
    <x-storefront.header :sticky="false" />
    <x-storefront.mobile-nav />

    <main class="mx-auto flex-1 w-full max-w-7xl px-4 py-6 pb-20 md:px-6 md:py-8 md:pb-10">
        @if(session('success'))<div class="mb-4"><div class="alert-success" data-flash-success>{{ session('success') }}</div></div>@endif
        @if(session('error'))<div class="mb-4"><div class="alert-error" data-flash-error>{{ session('error') }}</div></div>@endif
        @if($errors->any())<div class="mb-4"><div class="alert-error"><p class="mb-1 font-semibold">Please fix the following:</p><ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>@endif

        <div x-data="{ accountMenu: false }" class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
            <div class="lg:hidden">
                <button type="button" @click="accountMenu = !accountMenu" class="flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm" :aria-expanded="accountMenu.toString()">
                    <span class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-brand-100 font-bold text-brand-700">@if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" alt="" class="h-full w-full object-cover">@else{{ strtoupper(substr(auth()->user()->name,0,1)) }}@endif</span><span><strong class="block text-sm text-navy-900">My Account</strong><span class="text-xs text-slate-500">{{ auth()->user()->name }}</span></span></span>
                    <svg class="h-5 w-5 text-slate-400 transition" :class="accountMenu ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>
            {{-- Sidebar --}}
            <aside x-show="accountMenu || window.innerWidth >= 1024" x-cloak class="card h-fit p-3 lg:block">
                <div class="mb-4 flex items-center gap-3 border-b border-slate-100 px-2 pb-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-700 ring-4 ring-brand-50">
                        @if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover">@else<span class="text-lg font-bold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span>@endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-navy-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        <span class="mt-1 inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700">{{ auth()->user()->hasRole('seller') ? 'Buyer + Seller' : 'Buyer' }}</span>
                    </div>
                </div>
                <nav class="space-y-1 text-sm" aria-label="Buyer account">
                    @php
                        $links = [
                            'account.profile' => ['Buyer Dashboard', route('account.profile')],
                            'orders.index' => ['My Orders', route('orders.index')],
                            'account.addresses' => ['Addresses', route('account.addresses')],
                            'wishlist.index' => ['Wishlist', route('wishlist.index')],
                            'notifications.index' => ['Notifications', route('notifications.index')],
                            'account.password' => ['Change Password', route('account.password')],
                        ];
                    @endphp
                    @foreach($links as $route => [$label, $url])
                        <a href="{{ $url }}" @click="accountMenu = false" class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-semibold transition {{ request()->routeIs($route) ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-brand-50/70 hover:text-brand-800' }}">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ request()->routeIs($route) ? 'bg-white text-brand-600' : 'text-slate-400' }}">
                                @if($route === 'account.profile')<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z"/></svg>@elseif($route === 'orders.index')<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"/></svg>@elseif($route === 'account.addresses')<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" d="M12 21s7-4.7 7-11a7 7 0 1 0-14 0c0 6.3 7 11 7 11Z"/><circle cx="12" cy="10" r="2.2"/></svg>@elseif($route === 'wishlist.index')<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" d="m12 20-7.2-7.2A4.8 4.8 0 0 1 12 6.4a4.8 4.8 0 0 1 7.2 6.4L12 20Z"/></svg>@elseif($route === 'notifications.index')<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 12h4"/></svg>@else<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="1.8" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.5 7.5 0 0 1-.1 1.2l2 1.5-2 3.4-2.4-1a7.8 7.8 0 0 1-2.1 1.2l-.3 2.6h-4l-.3-2.6a7.8 7.8 0 0 1-2.1-1.2l-2.4 1-2-3.4 2-1.5A7.5 7.5 0 0 1 5.6 12c0-.4 0-.8.1-1.2l-2-1.5 2-3.4 2.4 1a7.8 7.8 0 0 1 2.1-1.2l.3-2.6h4l.3 2.6a7.8 7.8 0 0 1 2.1 1.2l2.4-1 2 3.4-2 1.5c.1.4.2.8.2 1.2Z"/></svg>@endif
                            </span><span>{{ $label }}</span>
                        </a>
                    @endforeach
                    @php
                        $sellerAction = auth()->user()->sellerAction();
                    @endphp
                    <a class="mt-2 flex items-center gap-3 rounded-xl border border-brand-100 bg-brand-50/60 px-3 py-2.5 font-semibold text-brand-800 hover:bg-brand-100" href="{{ $sellerAction['url'] }}"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-accent-500">+</span><span>{{ $sellerAction['label'] }}</span></a>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 pt-1">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left font-semibold text-rose-600 hover:bg-rose-50"><span class="text-base">↗</span>Log out</button>
                    </form>
                </nav>
            </aside>

            {{-- Content --}}
            <div>
                @yield('account-content')
            </div>
        </div>
    </main>

    <x-storefront.footer />
    <div id="toast-container" class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 space-y-2"></div>
    @stack('scripts')
</body>
</html>
