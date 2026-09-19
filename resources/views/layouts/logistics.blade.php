<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Logistics') · SHOPPICK</title>@vite(['resources/css/app.css', 'resources/js/app.js'])@stack('head')
</head>
<body class="logistics-shell bg-slate-50 text-navy-900">
@php
    $groups = [
        'Sorting Center' => [
            ['logistics.dashboard', 'Dashboard', 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z', null],
            ['logistics.pickup-requests', 'Pickup Requests', 'M4 17h16M6 17V9h9l3 3v5M8 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm9 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z', 'pickup_requests'],
            ['logistics.incoming', 'Incoming Parcels', 'M12 3v12m0 0 5-5m-5 5-5-5M4 20h16', 'incoming_parcels'],
            ['logistics.sorting', 'Sorting', 'M4 7h5l3 3 3-3h5M4 17h5l3-3 3 3h5', 'sorting'],
            ['logistics.delivery-assignment', 'Delivery Assignment', 'M5 12h14m-5-5 5 5-5 5', 'delivery_assignment'],
            ['logistics.tracking', 'Delivery Monitoring', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v4l3 2', 'delivery_monitoring'],
        ],
        'People' => [
            ['logistics.riders', 'Riders / Couriers', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'rider_attention'],
            ['logistics.rider-applications', 'Rider Applications', 'M12 3 4 7v11h16V7l-8-4Zm-3 8h6m-6 4h6', 'rider_applications'],
        ],
        'Management' => [
            ['logistics.sorting-reports', 'Reports', 'M4 19V9m6 10V5m6 14v-7m4 7H2', null],
            ['logistics.notifications', 'Chat / Messaging', 'M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z', 'unread_messages'],
            ['logistics.account', 'Account', 'M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', null],
        ],
    ];
@endphp
<input type="checkbox" id="logistics-menu-toggle" class="logistics-menu-toggle" aria-hidden="true">
<aside id="logistics-sidebar" class="logistics-sidebar">
    <a href="{{ route('logistics.dashboard') }}" class="logistics-brand"><x-shoppick.logo class="h-9 w-9"/><span><b class="text-brand-400">SHOP</b><b class="text-accent-400">PICK</b><small>LOGISTICS</small></span></a>
    <nav class="logistics-nav" aria-label="Logistics navigation">
        @foreach($groups as $group => $links)
            <p>{{ $group }}</p>
            @foreach($links as [$route, $label, $icon, $countKey])
                @php($count=$countKey ? ($logisticsSidebarCounts[$countKey] ?? 0) : 0)
                <a href="{{ route($route) }}" class="{{ request()->routeIs($route) || request()->routeIs($route.'.*') ? 'active' : '' }}">
                    <span class="logistics-menu-left">
                        <span class="logistics-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg></span>
                        <span class="logistics-nav-label">{{ $label }}</span>
                    </span>
                    @if($count>0)<span class="logistics-count" aria-label="{{ $count }} pending">{{ $count>99?'99+':$count }}</span>@endif
                </a>
            @endforeach
        @endforeach
    </nav>
    <footer class="logistics-user">
        <div class="logistics-user-info"><div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div><div class="min-w-0 flex-1"><b>{{ auth()->user()->name }}</b><small>Logistics</small></div></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="logistics-logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 8l4 4m0 0-4 4m4-4H9m3 7H5a2 2 0 01-2-2V7a2 2 0 012-2h7"/></svg><span>Logout</span></button></form>
    </footer>
</aside>
<label for="logistics-menu-toggle" class="logistics-sidebar-backdrop" aria-label="Close Logistics menu"></label>
<main class="logistics-main">
    <header class="logistics-topbar">
        <label for="logistics-menu-toggle" class="logistics-menu-button" aria-label="Open Logistics menu" aria-controls="logistics-sidebar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></label>
        <div><span class="text-slate-400">Dashboard</span><b class="mx-2 inline text-slate-300">›</b><span>@yield('title', 'Logistics')</span></div>
        <div class="ml-auto flex items-center gap-3">
            <label class="logistics-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg><input placeholder="Search logistics…" aria-label="Search Logistics"></label>
            @php($unreadLogistics=$logisticsSidebarCounts['unread_messages']??0)
            <a href="{{ route('logistics.notifications') }}" class="top-icon relative" aria-label="Notifications{{ $unreadLogistics?' ('.$unreadLogistics.' unread)':'' }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>@if($unreadLogistics)<span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $unreadLogistics>99?'99+':$unreadLogistics }}</span>@endif</a>
            <div class="hidden text-right sm:block"><b>{{ auth()->user()->name }}</b><small>Operations Manager</small></div><div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
        </div>
    </header>
    <section class="logistics-content">@if(session('success'))<div class="alert-success mb-4">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert-error mb-4">{{ $errors->first() }}</div>@endif @yield('content')</section>
</main>
<x-admin.confirm-modal/>@stack('scripts')
</body>
</html>
