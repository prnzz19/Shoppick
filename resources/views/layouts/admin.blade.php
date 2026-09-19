<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · Admin · SHOPPICK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="min-h-screen bg-slate-100">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-navy-900 lg:flex">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-5 py-5">
                <x-shoppick.logo class="h-9 w-9" />
                <span class="text-lg font-extrabold tracking-tight"><span class="text-brand-400">SHOP</span><span class="text-accent-400">PICK</span></span>
            </a>

            @php
                $common = [
                    ['admin.dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6-8h4m-4 8h4V8h-4v12z', null],
                    ['admin.users.index', 'Users', 'M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'users'],
                    ['admin.shops.index', 'Shops', 'M3 10l2-6h14l2 6M5 10v10h14V10M9 20v-6h6v6', null],
                    ['admin.sellers.applications.index', 'Applications', 'M5 13l4 4L19 7', 'applications'],
                    ['admin.products.index', 'Products', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', null],
                    ['admin.categories.index', 'Categories', 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', null],
                    ['admin.inventory.index', 'Inventory', 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z', 'inventory'],
                    ['admin.orders.index', 'Orders', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'orders'],
                    ['admin.promotions.index', 'Promotions', 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z', null],
                    ['admin.reports.index', 'Reports', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2z', 'reports'],
                    ['admin.moderation.index', 'Moderation', 'M9 12l2 2 4-4m5-3a9 9 0 11-16 0', 'moderation'],
                    ['admin.analytics.index', 'Analytics', 'M4 19h16M7 16V8m5 8V4m5 12v-6', null],
                    ['admin.roles.index', 'Roles & Permissions', 'M9 12l2 2 4-4M7.8 4.7a3.4 3.4 0 014.4-.8 3.4 3.4 0 004.4.8', null],
                ];
            @endphp

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Admin Panel</p>
                @foreach($common as [$route, $label, $icon, $countKey])
                    <a href="{{ route($route) }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 hover:bg-white/5 hover:text-white {{ request()->routeIs($route) ? 'bg-brand-500/20 text-brand-300' : '' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $icon }}"/></svg>
                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                        @if($countKey)<x-admin.sidebar-count :count="$adminSidebarCounts[$countKey] ?? 0" />@endif
                    </a>
                @endforeach

            </nav>

            <div class="shrink-0 border-t border-white/10 bg-navy-900 p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-brand-500/20 text-brand-300">
                        @if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover">@else<span class="text-sm font-bold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span>@endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-400">Admin</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('home') }}" class="btn-sm flex-1 bg-white/10 text-left text-slate-200 hover:bg-white/20">Store</a>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="btn-sm flex w-full items-center justify-center gap-2 bg-rose-500/20 text-rose-300 hover:bg-rose-500/30" aria-label="Logout from SHOPPICK">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Mobile top bar --}}
        <div class="fixed inset-x-0 top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                <x-shoppick.logo class="h-8 w-8" />
                <span class="text-lg font-extrabold"><span class="text-brand-500">SHOP</span><span class="text-accent-500">PICK</span></span>
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="p-2 text-navy-700 hover:bg-slate-100 rounded-lg"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6-8h4m-4 8h4V8h-4v12z"/></svg></a>
                <button type="button" onclick="document.getElementById('mobile-nav').classList.toggle('hidden')" class="p-2 text-navy-700 hover:bg-slate-100 rounded-lg"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            </div>
        </div>

        {{-- Mobile nav --}}
        <div id="mobile-nav" class="fixed inset-x-0 top-14 z-30 hidden bg-navy-900 p-4 lg:hidden">
            <nav class="grid grid-cols-2 gap-2">
                @foreach($common as [$route, $label, $icon, $countKey])
                    <a href="{{ route($route) }}" class="flex min-w-0 items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-200 hover:bg-white/10 {{ request()->routeIs($route) ? 'bg-brand-500/20 text-brand-300' : '' }}">
                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                        @if($countKey)<x-admin.sidebar-count :count="$adminSidebarCounts[$countKey] ?? 0" />@endif
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="mt-4 border-t border-white/10 pt-4">
                @csrf
                <button type="submit" class="btn-sm flex w-full items-center justify-center gap-2 bg-rose-500/20 text-rose-300 hover:bg-rose-500/30" aria-label="Logout from SHOPPICK">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Logout
                </button>
            </form>
        </div>

        {{-- Content --}}
        <div class="flex-1 px-4 pb-8 pt-16 lg:pl-72 lg:pt-6">
            @if(session('success'))<div class="mb-4"><div class="alert-success">{{ session('success') }}</div></div>@endif
            @if(session('error'))<div class="mb-4"><div class="alert-error">{{ session('error') }}</div></div>@endif
            @if($errors->any())<div class="mb-4"><div class="alert-error"><p class="mb-1 font-semibold">Please fix the following:</p><ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>@endif
            @yield('content')
        </div>
    </div>
    <x-admin.confirm-modal />
    @stack('scripts')
</body>
</html>
