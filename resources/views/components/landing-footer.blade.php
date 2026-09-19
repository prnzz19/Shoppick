<footer class="sp-footer"><div class="sp-wrap">
<a class="sp-brand" href="{{ route('landing') }}" aria-label="SHOPPICK home"><x-shoppick.logo/><span><span class="sp-word-shop">SHOP</span><span class="sp-word-pick">PICK</span></span></a><p class="sp-footer-tagline">Shop Easy. Pick Smart.<br>Find products from different shops in one easy place.</p>
<div class="sp-footer-grid">
<div><h2>Shop</h2><a href="{{ route('home') }}">Shop Now</a><a href="#categories">Categories</a><a href="{{ route('cart.index') }}">View Cart</a></div>
<div><h2>Account</h2><a href="{{ route('login') }}">Log In</a><a href="{{ route('register') }}">Create Account</a><a href="{{ route('orders.index') }}">My Orders</a></div>
<div><h2>Sell with Us</h2><a href="{{ route('register.seller') }}">Become a Seller</a></div>
<div><h2>Delivery</h2><a href="{{ route('register.rider') }}">Apply as Rider</a></div>
<div><h2>Company</h2><a href="#contact">Contact</a><a href="#why">About SHOPPICK</a></div>
</div><div id="contact" class="sp-contact-note"><strong>Contact information</strong><p>A public SHOPPICK support contact has not been provided yet. Visit individual shop pages for any available business information.</p></div><div class="sp-footer-bottom">&copy; {{ date('Y') }} SHOPPICK. All rights reserved.</div>
</div></footer>
