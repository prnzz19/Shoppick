@props(['sticky' => true])

<header {{ $attributes->merge(['class' => ($sticky ? 'sticky top-0 z-40 ' : '') . 'bg-white shadow-sm']) }}>
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex h-14 items-center gap-4">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
                <x-shoppick.logo class="h-9 w-9" />
                <span class="hidden sm:block text-2xl font-extrabold tracking-tight">
                    <span class="text-brand-500">SHOP</span><span class="text-accent-500">PICK</span>
                </span>
            </a>

            {{-- Search --}}
            <div class="relative flex-1 max-w-xl">
                <div class="flex items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100 focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-100 transition">
                    <span class="pl-3 text-slate-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    </span>
                    <form action="{{ route('products.index') }}" method="GET" class="flex-1">
                        <input id="search-input" type="text" name="q" value="{{ request('q') }}"
                               autocomplete="off" placeholder="Search SHOPPICK..." 
                               class="w-full bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    </form>
                    <button type="submit" onclick="event.preventDefault(); this.closest('form').submit();" class="m-1 rounded-lg bg-brand-500 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-600">Search</button>
                </div>
                <div id="search-suggestions" class="absolute top-full mt-1 hidden w-full overflow-hidden rounded-xl border border-slate-100 bg-white shadow-lg"></div>
            </div>

            {{-- Right actions --}}
            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                @auth
                    <a href="{{ route('wishlist.index') }}" class="relative rounded-lg p-2 text-navy-700 hover:bg-slate-100" title="Wishlist">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        @if($sharedWishlistCount > 0)<span class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-white">{{ $sharedWishlistCount }}</span>@endif
                    </a>
                    <a href="{{ route('cart.index') }}" class="relative rounded-lg p-2 text-navy-700 hover:bg-slate-100" title="Cart">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        @if($sharedCartCount > 0)<span class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white">{{ $sharedCartCount }}</span>@endif
                    </a>
                    <a href="{{ route('notifications.index') }}" class="relative rounded-lg p-2 text-navy-700 hover:bg-slate-100" title="Notifications">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if($sharedUnreadNotifications > 0)<span class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white">{{ $sharedUnreadNotifications }}</span>@endif
                    </a>

                    {{-- Profile dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100">
                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-600">
                                @if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover" alt="">@else<span class="text-sm font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>@endif
                            </span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-56 rounded-2xl border border-slate-100 bg-white p-1.5 shadow-xl">
                            <div class="border-b border-slate-100 px-3 py-2">
                                <p class="truncate text-sm font-semibold text-navy-800">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('account.profile') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">My Account</a>
                            <a href="{{ route('orders.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">My Orders</a>
                            <a href="{{ route('wishlist.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Wishlist</a>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ auth()->user()->isSuperAdmin() ? route('superadmin.dashboard') : route('admin.dashboard') }}" class="block rounded-lg bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-100">Dashboard</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-slate-100 pt-1">
                                @csrf
                                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Log out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost btn-sm">Login</a>
                    <a href="{{ route('register') }}" class="btn-primary btn-sm">Register</a>
                @endauth
            </div>
        </div>

        {{-- Category nav --}}
        <nav class="hidden md:block -mx-4 overflow-x-auto">
            <ul class="flex items-center gap-1 px-4 pb-1 text-sm">
                <li><a href="{{ route('products.index') }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 font-medium text-navy-700 hover:bg-brand-50 hover:text-brand-600">All Products</a></li>
                @foreach($sharedCategories as $cat)
                    <li class="relative group">
                        <a href="{{ route('products.category', $cat->id) }}" class="whitespace-nowrap rounded-lg px-3 py-1.5 font-medium text-navy-700 hover:bg-brand-50 hover:text-brand-600">{{ $cat->name }}</a>
                        @if($cat->children->isNotEmpty())
                            <div class="invisible absolute left-0 top-full z-30 min-w-[200px] rounded-xl border border-slate-100 bg-white p-2 shadow-lg opacity-0 transition group-hover:visible group-hover:opacity-100">
                                @foreach($cat->children as $child)
                                    <a href="{{ route('products.category', $child->id) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-brand-50 hover:text-brand-600">{{ $child->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>
