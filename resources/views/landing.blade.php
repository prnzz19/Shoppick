<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Find products from different shops in one easy place. Shop products and categories on SHOPPICK.">
<title>SHOPPICK &mdash; Shop Easy. Pick Smart.</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="sp-landing">
<a class="sp-skip" href="#main">Skip to content</a>
<header class="sp-header"><nav class="sp-wrap sp-nav" aria-label="Main navigation">
<a class="sp-brand" href="{{ route('landing') }}" aria-label="SHOPPICK home"><x-shoppick.logo/><span><span class="sp-word-shop">SHOP</span><span class="sp-word-pick">PICK</span></span></a>
<div class="sp-nav-links"><a href="{{ route('landing') }}" aria-current="page">Home</a><a href="{{ route('home') }}">Shop</a><a href="#categories">Categories</a><a href="#shops">Shops</a><a href="#why">Why SHOPPICK</a></div>
<div class="sp-nav-actions"><a href="#landing-search" aria-label="Search products" class="sp-nav-tool"><x-landing-icon name="search"/></a>@guest<a href="{{ route('cart.index') }}" aria-label="View Cart" class="sp-nav-tool"><x-landing-icon name="cart"/></a>@endguest
@guest<a href="{{ route('login') }}"><x-landing-icon name="user"/>Log In</a><a href="{{ route('register') }}">Create Account</a>@else<a href="{{ route('orders.index') }}">My Orders</a><a href="{{ route('cart.index') }}"><x-landing-icon name="cart"/>View Cart</a>@endguest<a class="sp-button sp-button-teal" href="{{ route('home') }}">Shop Now</a></div>
<details class="sp-mobile-menu"><summary aria-label="Toggle navigation"><x-seller.icon name="menu"/><span>Menu</span></summary><div><a href="{{ route('landing') }}">Home</a><a href="{{ route('home') }}">Shop</a><a href="#categories">Categories</a><a href="#shops">Shops</a><a href="#why">Why SHOPPICK</a></div></details>
</nav></header>
<main id="main">
<section class="sp-wrap sp-hero" id="hero-carousel" aria-roledescription="carousel" aria-label="Everyday shopping inspiration">
<div class="sp-hero-copy"><p class="sp-eyebrow">Your everyday online marketplace</p><h1 id="hero-title">Find What Fits Your Life.</h1><p class="sp-intro" id="hero-description">From everyday essentials to your next favorite find, SHOPPICK helps you discover products made for the way you live.</p>
<div class="sp-actions"><a class="sp-button sp-button-teal" href="{{ route('home') }}"><x-landing-icon name="cart"/>Shop Now</a><a class="sp-button sp-button-outline" href="#categories"><x-landing-icon/>Browse Categories</a></div>
<form class="sp-search" action="{{ route('products.index') }}" method="GET" role="search"><label for="landing-search">What are you looking for?</label><div><input id="landing-search" name="q" type="search" placeholder="Search for products..."><button class="sp-button sp-button-teal" type="submit"><x-landing-icon name="search"/>Search</button></div></form>
<p class="sp-seller-note">Want to sell your products? <a href="{{ route('register.seller') }}">Start Selling &rarr;</a></p></div>
<div class="sp-lifestyle-hero"><img src="{{ asset('images/landing/lifestyle-slide-1.jpg') }}" alt="A shopper holding a smartphone, a parcel and a teal shopping bag" width="1122" height="1402" fetchpriority="high" decoding="async"></div>
</section>
@if($picks->whereNotNull('category')->isNotEmpty())
<section id="categories" class="sp-wrap sp-section sp-collections" aria-labelledby="collections-title"><div class="sp-section-heading"><div><p class="sp-eyebrow">Find something for your day</p><h2 id="collections-title">Shop by Category</h2><p>Choose a category to see more products like these.</p></div></div><div class="sp-collection-grid">
@foreach($picks->whereNotNull('category')->filter(fn ($product) => $product->category->is_active)->unique('category_id')->take(3) as $product)
<a class="sp-collection-card" href="{{ route('products.index', ['category' => $product->category_id]) }}"><div class="sp-collection-image">@if($product->main_image)<img src="{{ asset('storage/'.$product->main_image) }}" alt="{{ $product->name }}" width="600" height="600" loading="lazy">@else<x-shoppick.logo/>@endif</div><div class="sp-collection-copy"><h3>{{ $product->category->name }}</h3><p>Find {{ strtolower($product->category->name) }} products for your everyday needs.</p><span class="sp-button sp-button-outline">Shop {{ $product->category->name }} &rarr;</span></div></a>
@endforeach
</div></section>
@endif
<section id="discover" class="sp-wrap sp-section"><div class="sp-section-heading"><div><h2>Latest Products</h2><p>Fresh products from shops across SHOPPICK.</p></div></div><div class="sp-picks">@forelse($picks as $product)<article class="sp-pick"><a href="{{ route('products.show', $product->slug) }}"><div class="sp-product-image">@if($product->main_image)<img src="{{ asset('storage/'.$product->main_image) }}" alt="{{ $product->name }}" width="600" height="600" loading="lazy">@else<x-shoppick.logo/>@endif</div><div class="sp-product-copy"><h3>{{ $product->name }}</h3><p>{{ $product->store->name }}</p><strong>&#8369;{{ number_format($product->salePrice(), 2) }}</strong><span class="sp-view-product">View Product &rarr;</span></div></a></article>@empty<p>No products are available right now. Please check back soon.</p>@endforelse</div><div class="sp-see-all"><a class="sp-button sp-button-teal" href="{{ route('home') }}">View All Products &rarr;</a></div></section>
<section class="sp-wrap sp-section sp-more-categories" aria-labelledby="more-categories-title"><div class="sp-section-heading"><div><h2 id="more-categories-title">Shop More by Category</h2><p>Find more of what you like, all in one place.</p></div></div><div class="sp-category-collage">
@foreach($picks->whereNotNull('category')->filter(fn ($product) => $product->category->is_active)->unique('category_id')->take(4) as $product)
<a class="sp-collage-card" href="{{ route('products.index', ['category' => $product->category_id]) }}">@if($product->main_image)<img src="{{ asset('storage/'.$product->main_image) }}" alt="{{ $product->name }}" width="700" height="700" loading="lazy">@else<x-shoppick.logo/>@endif<div><h3>{{ $product->category->name }}</h3><span class="sp-button sp-button-outline">View {{ $product->category->name }} &rarr;</span></div></a>
@endforeach
</div><div class="sp-category-links">@foreach($categories as $category)<a href="{{ route('products.index', ['category' => $category->id]) }}">{{ $category->name }} &rarr;</a>@endforeach</div></section>
<x-landing-benefits/>
<section class="sp-wrap"><div class="sp-how"><h2>How to Shop</h2><ol>@foreach([['search','Find a Product','Browse categories or search for what you need.'],['cart','Add to Cart','Choose your product and add it to your cart.'],['orders','Place Your Order','Enter your delivery details and confirm your order.']] as [$icon,$title,$copy])<li><span class="sp-step-number">{{ $loop->iteration }}</span><x-landing-icon :name="$icon"/><h3>{{ $title }}</h3><p>{{ $copy }}</p></li>@endforeach</ol></div></section>
<x-landing-shops :shops="$shops"/>

<section class="sp-wrap sp-final-section" aria-labelledby="final-shopping-title"><div class="sp-final-cta"><div class="sp-final-copy"><p class="sp-final-label">Your next pick starts here</p><h2 id="final-shopping-title">Ready to Find Your Next Pick?</h2><p>Start exploring products available on SHOPPICK.</p><a class="sp-button sp-button-light" href="{{ route('home') }}"><x-landing-icon name="cart"/>Shop Now</a></div><div class="sp-final-mascot" aria-hidden="true"><x-shoppick.logo/></div></div></section>
</main>
<x-landing-footer/>
<script src="{{ asset('js/landing.js') }}" defer></script>
<script src="{{ asset('js/hero-carousel.js') }}" defer></script>
</body></html>
