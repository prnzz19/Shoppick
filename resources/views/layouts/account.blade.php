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

    <main class="mx-auto flex-1 w-full max-w-7xl px-4 py-6 pb-20 md:pb-8">
        @if(session('success'))<div class="mb-4"><div class="alert-success" data-flash-success>{{ session('success') }}</div></div>@endif
        @if(session('error'))<div class="mb-4"><div class="alert-error" data-flash-error>{{ session('error') }}</div></div>@endif
        @if($errors->any())<div class="mb-4"><div class="alert-error"><p class="mb-1 font-semibold">Please fix the following:</p><ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>@endif

        <div class="grid gap-6 lg:grid-cols-[240px_1fr]">
            {{-- Sidebar --}}
            <aside class="card h-fit p-3">
                <div class="mb-3 flex items-center gap-3 border-b border-slate-100 px-2 pb-3">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-600">
                        @if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover">@else<span class="text-lg font-bold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span>@endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-navy-800">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <nav class="space-y-1 text-sm">
                    @php
                        $links = [
                            'account.profile' => ['My Profile', route('account.profile')],
                            'orders.index' => ['My Orders', route('orders.index')],
                            'account.addresses' => ['Addresses', route('account.addresses')],
                            'wishlist.index' => ['Wishlist', route('wishlist.index')],
                            'notifications.index' => ['Notifications', route('notifications.index')],
                            'account.password' => ['Change Password', route('account.password')],
                        ];
                    @endphp
                    @foreach($links as $route => [$label, $url])
                        <a href="{{ $url }}" class="block rounded-lg px-3 py-2 font-medium {{ request()->routeIs($route) ? 'bg-brand-50 text-brand-600' : 'text-navy-700 hover:bg-slate-50' }}">{{ $label }}</a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 pt-1">
                        @csrf
                        <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left font-medium text-rose-600 hover:bg-rose-50">Log out</button>
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
