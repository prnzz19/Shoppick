<footer class="mt-8 bg-navy-900 text-white">
    <div class="mx-auto max-w-7xl px-4 py-10">
        <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
            <div class="col-span-2">
                <div class="flex items-center gap-2">
                    <x-shoppick.logo class="h-9 w-9" />
                    <span class="text-2xl font-extrabold tracking-tight"><span class="text-brand-400">SHOP</span><span class="text-accent-400">PICK</span></span>
                </div>
                <p class="mt-3 max-w-sm text-sm text-slate-300">Your friendly, colorful online marketplace. Find deals, discover new favorites, and shop with a smile — featuring our beloved red panda.</p>
            </div>
            <div>
                <h4 class="mb-3 text-sm font-semibold text-brand-300">Shop</h4>
                <ul class="space-y-2 text-sm text-slate-300">
                    <li><a href="{{ route('products.index') }}" class="hover:text-white">All Products</a></li>
                    <li><a href="{{ route('products.index', ['sort' => 'popular']) }}" class="hover:text-white">Popular</a></li>
                    <li><a href="{{ route('products.index', ['sort' => 'latest']) }}" class="hover:text-white">New Arrivals</a></li>
                    <li><a href="{{ route('products.index', ['discount' => 1]) }}" class="hover:text-white">Flash Deals</a></li>
                </ul>
            </div>
            <div>
                <h4 class="mb-3 text-sm font-semibold text-brand-300">Account</h4>
                <ul class="space-y-2 text-sm text-slate-300">
                    @auth
                    <li><a href="{{ route('account.profile') }}" class="hover:text-white">My Account</a></li>
                    <li><a href="{{ route('orders.index') }}" class="hover:text-white">My Orders</a></li>
                    <li><a href="{{ route('wishlist.index') }}" class="hover:text-white">Wishlist</a></li>
                    @else
                    <li><a href="{{ route('login') }}" class="hover:text-white">Login</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white">Register</a></li>
                    @endauth
                </ul>
            </div>
        </div>
        <div class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-white/10 pt-6 text-xs text-slate-400 sm:flex-row">
            <p>© {{ date('Y') }} SHOPPICK. All rights reserved.</p>
            <p>Made with <span class="text-accent-400">♥</span> for shoppers everywhere</p>
        </div>
    </div>
</footer>
