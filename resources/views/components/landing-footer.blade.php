<footer class="sp-footer">
    <div class="sp-frame">
        <div class="sp-footer-intro">
            <a class="sp-brand" href="{{ route('landing') }}" aria-label="SHOPPICK home">
                <x-shoppick.logo class="sp-brand-mark"/>
                <span><span class="sp-word-shop">SHOP</span><span class="sp-word-pick">PICK</span></span>
            </a>
            <p class="sp-footer-tagline">Shop easy. Pick smart.<br>Great products from trusted sellers, all for you.</p>
            <a class="sp-button sp-button-outline" href="{{ route('home') }}">Start shopping <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="sp-footer-grid">
            <div><h2>Shop</h2><a href="{{ route('home') }}">All Products</a><a href="#categories">Categories</a><a href="#recommended">Collections</a><a href="{{ route('products.index', ['sort' => 'latest']) }}">New arrivals</a></div>
            <div><h2>Help</h2><a href="{{ route('orders.index') }}">How to Order</a><a href="{{ route('login') }}">My Account</a><a href="#contact">Contact SHOPPICK</a></div>
            <div><h2>Company</h2><a href="{{ route('landing') }}">About SHOPPICK</a><a href="{{ route('seller.apply') }}">Become a Seller</a><a href="{{ route('register.rider') }}">Deliver with Us</a></div>
            <div><h2>Follow</h2><a href="{{ route('login') }}">Wishlist</a><a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Create account</a></div>
        </div>
        <div id="contact" class="sp-contact-note">Questions about an order? Sign in to reach the right SHOPPICK support team.</div>
        <div class="sp-footer-bottom">&copy; {{ date('Y') }} SHOPPICK. All rights reserved.</div>
    </div>
</footer>