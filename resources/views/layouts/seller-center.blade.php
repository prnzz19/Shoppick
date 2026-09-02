<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Seller Center') · SHOPPICK</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script><style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-slate-50" x-data="{collapsed:false,mobile:false}">
@php
    $store = auth()->user()->store;
    $sellerUnreadNotifications = auth()->user()->notificationsData()->unread()->count();
    $sellerOpenOrders = $store->sellerOrders()->whereIn('status', ['pending','confirmed','processing','packed','ready_to_ship'])->count();
    $nav = [
        ['seller.dashboard','Dashboard','⌂',0], ['seller.orders.index','Orders','▤',$sellerOpenOrders],
        ['seller.products.index','Products','□',0], ['seller.inventory.index','Inventory','◇',0],
        ['seller.marketing.index','Marketing','◆',0], ['seller.reviews.index','Reviews','☆',0],
        ['seller.sales.index','Sales','↗',0], ['seller.store.edit','Store','⌑',0],
        ['seller.notifications.index','Notifications','○',$sellerUnreadNotifications], ['seller.settings.index','Settings','⚙',0],
    ];
@endphp
<aside :class="collapsed?'w-20':'w-60'" class="fixed inset-y-0 left-0 z-40 hidden flex-col border-r border-slate-200 bg-white transition-all duration-200 lg:flex">
    <div class="flex h-16 items-center border-b border-slate-100 px-4" :class="collapsed?'justify-center':'justify-between'"><a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2"><x-shoppick.logo class="h-9 w-9"/><span x-show="!collapsed" class="font-extrabold text-navy-900"><span class="text-brand-500">SHOP</span>PICK</span></a><button x-show="!collapsed" @click="collapsed=true" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100">‹</button></div>
    <div x-show="!collapsed" class="mx-3 mt-4 rounded-xl bg-slate-50 p-3"><p class="truncate text-sm font-bold text-navy-900">{{ $store->name }}</p><p class="truncate text-xs text-slate-500">Seller Center</p></div>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">@foreach($nav as [$route,$label,$icon,$badge])<a href="{{ route($route) }}" title="{{ $label }}" class="flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium {{ request()->routeIs($route)||request()->routeIs(str_replace('.index','.*',$route))?'bg-brand-50 text-brand-700':'text-slate-600 hover:bg-slate-50 hover:text-navy-900' }}"><span class="w-5 text-center text-base">{{ $icon }}</span><span x-show="!collapsed">{{ $label }}</span>@if($badge>0)<span x-show="!collapsed" class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $badge }}</span>@endif</a>@endforeach</nav>
    <div class="border-t border-slate-100 p-3"><button x-show="collapsed" @click="collapsed=false" title="Expand" class="mb-2 w-full rounded-lg p-2 text-slate-500 hover:bg-slate-50">›</button><form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"><span class="w-5">↪</span><span x-show="!collapsed">Logout</span></button></form></div>
</aside>
<header class="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden"><a href="{{ route('seller.dashboard') }}" class="flex items-center gap-2"><x-shoppick.logo class="h-8 w-8"/><b class="text-navy-900"><span class="text-brand-500">SHOP</span>PICK</b></a><button @click="mobile=true" class="rounded-lg p-2 text-xl text-navy-900">☰</button></header>
<div x-cloak x-show="mobile" class="fixed inset-0 z-50 lg:hidden"><button @click="mobile=false" class="absolute inset-0 bg-navy-900/40"></button><aside x-transition class="relative h-full w-72 bg-white p-4 shadow-xl"><div class="mb-4 flex items-center justify-between"><b>Seller Center</b><button @click="mobile=false" class="p-2">×</button></div><nav class="space-y-1">@foreach($nav as [$route,$label,$icon,$badge])<a href="{{ route($route) }}" class="flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-slate-700 hover:bg-brand-50"><span>{{ $icon }}</span>{{ $label }}@if($badge>0)<span class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-bold text-white">{{ $badge }}</span>@endif</a>@endforeach</nav></aside></div>
<main :class="collapsed?'lg:pl-24':'lg:pl-64'" class="min-h-screen px-4 pb-10 pt-20 transition-all duration-200 sm:px-6 lg:pr-8 lg:pt-8">@if(session('success'))<div class="alert-success mb-5">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert-error mb-5">{{ session('error') }}</div>@endif @if($errors->any())<div class="alert-error mb-5">Unable to save. Check the highlighted fields.</div>@endif @yield('content')</main>
@stack('scripts')
</body></html>
