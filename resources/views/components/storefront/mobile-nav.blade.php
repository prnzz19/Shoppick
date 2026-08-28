<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-100 bg-white shadow-[0_-2px_10px_rgba(0,0,0,0.05)] md:hidden">
    <div class="mx-auto grid max-w-2xl grid-cols-5">
        <a href="{{ route('home') }}" class="flex flex-col items-center gap-0.5 py-2 text-navy-700 {{ request()->routeIs('home') ? 'text-brand-500' : '' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span class="text-[10px] font-medium">Home</span>
        </a>
        <a href="{{ route('products.index') }}" class="flex flex-col items-center gap-0.5 py-2 text-navy-700 {{ request()->routeIs('products.*') && !request()->routeIs('products.show') ? 'text-brand-500' : '' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            <span class="text-[10px] font-medium">Categories</span>
        </a>
        <a href="{{ route('cart.index') }}" class="relative flex flex-col items-center gap-0.5 py-2 text-navy-700 {{ request()->routeIs('cart.*') ? 'text-brand-500' : '' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            @if($sharedCartCount > 0)<span class="absolute right-1/2 top-0 flex h-4 w-4 -translate-y-0.5 items-center justify-center rounded-full bg-brand-500 text-[9px] font-bold text-white">{{ $sharedCartCount }}</span>@endif
            <span class="text-[10px] font-medium">Cart</span>
        </a>
        <a href="{{ route('orders.index') }}" class="flex flex-col items-center gap-0.5 py-2 text-navy-700 {{ request()->routeIs('orders.*') ? 'text-brand-500' : '' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <span class="text-[10px] font-medium">Orders</span>
        </a>
        <a href="{{ auth()->check() ? route('account.profile') : route('login') }}" class="flex flex-col items-center gap-0.5 py-2 text-navy-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span class="text-[10px] font-medium">Account</span>
        </a>
    </div>
</nav>
