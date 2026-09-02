<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Logistics') · SHOPPICK</title>@vite(['resources/css/app.css', 'resources/js/app.js'])@stack('head')
</head>
<body class="logistics-shell bg-slate-50 text-navy-900">
@php
    $groups = [
        'Logistics' => [['logistics.dashboard', 'Dashboard', '⌂']],
        'Operations' => [['logistics.shipments.index', 'Orders / Loads', '▣'], ['logistics.dispatch', 'Dispatch', '◇'], ['logistics.fleet', 'Fleet', '▰'], ['logistics.riders', 'Riders', '♙'], ['logistics.tracking', 'Routes & Tracking', '⌁'], ['logistics.hubs', 'Hubs / Warehouses', '▥'], ['logistics.pod', 'Proof of Delivery', '▤']],
        'Finance' => [['logistics.billing', 'Billing & Invoices', '▧']],
        'Management' => [['logistics.reports', 'Reports', '▥'], ['logistics.ai', 'AI Ops Assistant', '✦'], ['logistics.notifications', 'Notifications', '◉'], ['logistics.settings', 'Settings', '⚙']],
    ];
@endphp
<input type="checkbox" id="logistics-menu-toggle" class="logistics-menu-toggle" aria-hidden="true">
<aside id="logistics-sidebar" class="logistics-sidebar">
    <a href="{{ route('logistics.dashboard') }}" class="logistics-brand"><x-shoppick.logo class="h-9 w-9"/><span><b class="text-brand-400">SHOP</b><b class="text-accent-400">PICK</b><small>LOGISTICS</small></span></a>
    <nav class="logistics-nav" aria-label="Logistics navigation">
        @foreach($groups as $group => $links)<p>{{ $group }}</p>@foreach($links as [$route, $label, $icon])<a href="{{ route($route) }}" class="{{ request()->routeIs($route) || request()->routeIs($route.'.*') ? 'active' : '' }}"><span>{{ $icon }}</span>{{ $label }}</a>@endforeach @endforeach
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
        <div class="ml-auto flex items-center gap-3"><label class="logistics-search"><span>⌕</span><input placeholder="Search logistics…" aria-label="Search Logistics"></label>@php($unreadLogistics=auth()->user()->notificationsData()->unread()->count())<a href="{{ route('logistics.notifications') }}" class="top-icon relative" aria-label="Notifications{{ $unreadLogistics?' ('.$unreadLogistics.' unread)':'' }}"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>@if($unreadLogistics)<span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ $unreadLogistics>99?'99+':$unreadLogistics }}</span>@endif</a><div class="hidden text-right sm:block"><b>{{ auth()->user()->name }}</b><small>Operations Manager</small></div><div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div></div>
    </header>
    <section class="logistics-content">@if(session('success'))<div class="alert-success mb-4">{{ session('success') }}</div>@endif @if($errors->any())<div class="alert-error mb-4">{{ $errors->first() }}</div>@endif @yield('content')</section>
</main>
<x-admin.confirm-modal/>@stack('scripts')
</body>
</html>
