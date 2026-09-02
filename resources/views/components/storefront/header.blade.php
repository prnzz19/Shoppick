@props(['sticky' => true])

<header {{ $attributes->merge(['class' => ($sticky ? 'sticky top-0 z-40 ' : '') . 'bg-white shadow-sm']) }}>
    <div class="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8">
        <div class="flex min-h-16 flex-wrap items-center gap-x-3 gap-y-2 py-2 md:flex-nowrap md:gap-4 md:py-0">
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2">
                <x-shoppick.logo class="h-9 w-9" />
                <span class="hidden sm:block text-2xl font-extrabold tracking-tight">
                    <span class="text-brand-500">SHOP</span><span class="text-accent-500">PICK</span>
                </span>
            </a>

            {{-- Search --}}
            <div class="relative order-3 w-full min-w-0 md:order-none md:max-w-2xl md:flex-1">
                <form action="{{ route('products.index') }}" method="GET" class="flex items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100 transition focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-100">
                    <span class="pl-3 text-slate-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                    </span>
                    <div class="flex-1">
                        <input id="search-input" type="text" name="q" value="{{ request('q') }}"
                               autocomplete="off" placeholder="Search SHOPPICK..." 
                               class="w-full bg-transparent px-3 py-2.5 text-sm focus:outline-none">
                    </div>
                    <button type="submit" class="m-1 rounded-lg bg-brand-500 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-600">Search</button>
                </form>
                <div id="search-suggestions" class="absolute top-full mt-1 hidden w-full overflow-hidden rounded-xl border border-slate-100 bg-white shadow-lg"></div>
            </div>

            {{-- Right actions --}}
            <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
                @auth
                    <a href="{{ route('wishlist.index') }}" class="relative rounded-lg p-2 text-navy-700 hover:bg-slate-100" title="Wishlist">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        <span data-wishlist-count class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-white {{ $sharedWishlistCount > 0 ? '' : 'hidden' }}">{{ $sharedWishlistCount }}</span>
                    </a>
                    <a href="{{ route('cart.index') }}" class="relative rounded-lg p-2 text-navy-700 hover:bg-slate-100" title="Cart">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span data-cart-count class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white {{ $sharedCartCount > 0 ? '' : 'hidden' }}">{{ $sharedCartCount }}</span>
                    </a>
                    <a href="{{ route('notifications.index') }}" class="relative rounded-lg p-2 text-navy-700 hover:bg-slate-100" title="Notifications">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        @if($sharedUnreadNotifications > 0)<span class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white">{{ $sharedUnreadNotifications }}</span>@endif
                    </a>

                    {{-- Profile dropdown --}}
                    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu" aria-controls="buyer-account-menu" class="flex items-center gap-1 rounded-lg p-1.5 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-brand-200">
                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-600">
                                @if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover" alt="">@else<span class="text-sm font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>@endif
                            </span>
                            <svg class="hidden h-4 w-4 text-slate-400 transition sm:block" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="buyer-account-menu" x-show="open" x-transition.origin.top.right @click.outside="open = false" x-cloak role="menu" class="absolute right-0 top-full z-50 mt-2 w-[min(17rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="flex min-w-0 items-center gap-3 border-b border-slate-100 px-2 py-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-brand-600">
                                    @if(auth()->user()->avatar)<img src="{{ auth()->user()->avatar_url }}" class="h-full w-full object-cover" alt="">@else<span class="font-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>@endif
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-navy-800" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-slate-500" title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</p>
                                    @if(auth()->user()->phone)<p class="truncate text-xs text-slate-400">{{ auth()->user()->phone }}</p>@endif
                                </div>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('account.profile') }}" @click="open = false" role="menuitem" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-navy-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21a8 8 0 10-16 0m8-10a4 4 0 100-8 4 4 0 000 8z"/></svg>My Account</a>
                                <a href="{{ route('orders.index') }}" @click="open = false" role="menuitem" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-navy-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 4h12v16H6V4zm3 4h6m-6 4h6m-6 4h4"/></svg>My Orders</a>
                                <a href="{{ route('wishlist.index') }}" @click="open = false" role="menuitem" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-navy-700 hover:bg-slate-50"><svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4.3 6.3a4.5 4.5 0 000 6.4L12 20.4l7.7-7.7a4.5 4.5 0 00-6.4-6.4L12 7.6l-1.3-1.3a4.5 4.5 0 00-6.4 0z"/></svg>Wishlist</a>
                            </div>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ auth()->user()->isSuperAdmin() ? route('superadmin.dashboard') : route('admin.dashboard') }}" class="block rounded-lg bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-100">Dashboard</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100 pt-1">
                                @csrf
                                <button type="submit" role="menuitem" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-rose-600 hover:bg-rose-50"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 8l4 4m0 0l-4 4m4-4H9m3 7H5a2 2 0 01-2-2V7a2 2 0 012-2h7"/></svg>Log out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-xl border border-brand-300 bg-white px-4 text-sm font-semibold text-brand-700 transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-200 focus:ring-offset-1 sm:h-11 sm:px-5 sm:text-[15px]">Login</a>
                    <a href="{{ route('register') }}" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-xl bg-brand-500 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-1 sm:h-11 sm:px-5 sm:text-[15px]">Register</a>
                @endauth
            </div>
        </div>

        {{-- Category nav --}}
        <nav aria-label="Product categories" class="scrollbar-none -mx-4 overflow-x-auto border-t border-slate-100 sm:-mx-6 md:overflow-visible lg:-mx-8">
            <ul class="flex min-w-max items-center gap-1 px-4 py-1.5 text-xs sm:px-6 md:min-w-0 md:text-sm lg:px-8">
                <li class="group shrink-0 md:flex-1">
                    <a href="{{ route('products.index') }}" class="flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-3 font-medium transition {{ request()->routeIs('products.index') && !request()->filled('category') ? 'bg-brand-50 text-brand-700' : 'text-navy-700 hover:bg-brand-50 hover:text-brand-600' }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-50 text-brand-600 transition group-hover:bg-brand-100">
                            <x-category-icon name="All Products" />
                        </span>
                        <span>All Products</span>
                    </a>
                </li>
                @foreach($sharedCategories as $cat)
                    <li class="group relative shrink-0 md:flex-1">
                        <a href="{{ route('products.category', $cat->id) }}" class="flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-3 font-medium transition {{ (string)request('category') === (string)$cat->id ? 'bg-brand-50 text-brand-700' : 'text-navy-700 hover:bg-brand-50 hover:text-brand-600' }}">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-50 text-brand-600 transition group-hover:bg-brand-100">
                                <x-category-icon :name="$cat->name" />
                            </span>
                            <span>{{ $cat->name }}</span>
                        </a>
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
