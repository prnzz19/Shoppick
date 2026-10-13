<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Find real products from approved shops in one easy place on SHOPPICK.">
    <title>SHOPPICK — Shop Easy. Pick Smart.</title>
    <link rel="preload" href="{{ asset('fonts/manrope/Manrope-Variable.ttf') }}" as="font" type="font/ttf" crossorigin>
    <script>window.APP_ROUTES = { autocomplete: @json(route('search.autocomplete')) };</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/landing-redesign.css') }}?v={{ filemtime(public_path('css/landing-redesign.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/landing-mockup.css') }}?v={{ filemtime(public_path('css/landing-mockup.css')) }}">
</head>
<body class="sp-landing">
<a class="sp-skip" href="#main">Skip to content</a>

<div class="sp-topbar">
    <div class="sp-frame sp-topbar-inner">
        <span>Shop easy. Pick smart.</span>
        <a href="{{ auth()->check() ? auth()->user()->sellerAction()['url'] : route('seller.apply') }}">{{ auth()->check() ? auth()->user()->sellerAction()['label'] : 'Sell on SHOPPICK' }} <span aria-hidden="true">→</span></a>
    </div>
</div>

<header class="sp-header">
    <nav class="sp-frame sp-nav" aria-label="Main navigation">
        <a class="sp-brand" href="{{ route('landing') }}" aria-label="SHOPPICK home">
            <x-shoppick.logo/><span><span class="sp-word-shop">SHOP</span><span class="sp-word-pick">PICK</span></span>
        </a>
        <div class="sp-nav-links">
            <a href="{{ route('landing') }}" aria-current="page">Home</a>
            <a href="{{ route('home') }}">Shop</a>
            <a href="#categories">Categories</a>
            <a href="#recommended">Collections</a>
            <a href="{{ route('products.index', ['sort' => 'latest']) }}">Deals</a>
        </div>
        <div class="sp-header-search-wrap">
            <form class="sp-header-search" action="{{ route('products.index') }}" method="GET" role="search">
                <x-landing-icon name="search"/>
                <label class="sr-only" for="search-input">Search SHOPPICK</label>
                <input id="search-input" name="q" type="search" autocomplete="off" placeholder="Search for products, brands, or stores...">
                <button type="submit" aria-label="Submit search"><x-landing-icon name="search"/></button>
            </form>
            <div id="search-suggestions" class="sp-search-suggestions" aria-live="polite"></div>
        </div>
        <div class="sp-nav-actions">
            @auth
                <a class="sp-utility-link" href="{{ route('wishlist.index') }}"><x-landing-icon name="heart"/> <span>Wishlist</span></a>
                <a class="sp-utility-link" href="{{ route('cart.index') }}"><x-landing-icon name="cart"/> <span>Cart</span></a>
                <a class="sp-account-link" href="{{ route('account.profile') }}">Account</a>
            @else
                <a class="sp-utility-link" href="{{ route('cart.index') }}"><x-landing-icon name="cart"/> <span>Cart</span></a>
                <a class="sp-account-link" href="{{ route('login') }}">Log in</a>
                <a class="sp-button sp-button-teal sp-header-cta" href="{{ route('register') }}">Create account</a>
            @endauth
        </div>
        <details class="sp-mobile-menu">
            <summary aria-label="Toggle navigation"><x-seller.icon name="menu"/><span>Menu</span></summary>
            <div>
                <a href="{{ route('landing') }}">Home</a>
                <a href="{{ route('home') }}">Shop</a>
                <a href="#categories">Categories</a>
                <a href="#recommended">Collections</a>
                <a href="{{ route('products.index', ['sort' => 'latest']) }}">Deals</a>
                <a href="#search-input">Search</a>
            </div>
        </details>
    </nav>
</header>

<main id="main">
    <section id="hero-carousel" class="sp-frame sp-hero" aria-roledescription="carousel" aria-label="SHOPPICK shopping inspiration">
        <div class="sp-hero-copy">
            <p class="sp-eyebrow">Your everyday marketplace</p>
            <h1 id="hero-title">Find Your Next Favorite Pick.</h1>
            <p id="hero-description" class="sp-hero-intro">Amazing products from trusted sellers, all in one place.</p>
            <div class="sp-actions">
                <a class="sp-button sp-button-teal" href="{{ route('home') }}">Shop Now <span aria-hidden="true">→</span></a>
                <a class="sp-button sp-button-outline" href="#recommended">Explore Collections <span aria-hidden="true">→</span></a>
            </div>
            <div class="sp-hero-note"><span>Real shops</span><i aria-hidden="true"></i><span>Clear order updates</span><i aria-hidden="true"></i><span>Simple checkout</span></div>
        </div>
        <div class="sp-lifestyle-hero">
            <img src="{{ asset('images/landing/campaign-model-original.jpg') }}" alt="SHOPPICK campaign model in a warm everyday setting" width="1536" height="1024" fetchpriority="high" decoding="async">
            <div class="sp-hero-stamp"><strong>SHOPPICK</strong><span>everyday finds, thoughtfully picked</span></div>
        </div>
    </section>

    <section class="sp-frame sp-discovery-bar" aria-label="Search SHOPPICK">
        <form class="sp-search" action="{{ route('products.index') }}" method="GET" role="search">
            <label for="search-input">What are you looking for?</label>
            <div class="sp-search-control">
                <x-landing-icon name="search"/>
                <input id="legacy-search-input" name="q" type="search" autocomplete="off" placeholder="Search products, shops, or categories">
                <button class="sp-button sp-button-teal" type="submit">Search</button>
            </div>
            <div id="legacy-search-suggestions" class="sp-search-suggestions" aria-live="polite"></div>
        </form>
        <p class="sp-seller-note">Have something to sell? <a href="{{ auth()->check() ? auth()->user()->sellerAction()['url'] : route('seller.apply') }}">Start selling <span aria-hidden="true">→</span></a></p>
    </section>

    <section id="categories" class="sp-frame sp-section sp-promo-section" aria-labelledby="category-title">
        <div class="sp-section-heading">
            <div><p class="sp-eyebrow">Explore the marketplace</p><h2 id="category-title">Shop by Category</h2><p>Start with a collection, then find the product that fits your day.</p></div>
            <a class="sp-text-link" href="{{ route('products.index') }}">View all categories <span aria-hidden="true">→</span></a>
        </div>
        <div class="sp-promo-grid">
            @forelse($featuredCategories->take(3) as $category)
                @php
                    $categoryProduct = $category->landingProduct;
                    $categoryImage = $categoryProduct?->main_image
                        ? asset('storage/'.$categoryProduct->main_image)
                        : (filled($category->image) && \Illuminate\Support\Facades\Storage::disk('public')->exists($category->image) ? asset('storage/'.$category->image) : null);
                @endphp
                <a class="sp-promo-card" href="{{ route('products.index', ['category' => $category->id]) }}">
                    <div class="sp-promo-media">
                        @if($categoryImage)<img src="{{ $categoryImage }}" alt="{{ $category->name }} products" width="900" height="720" loading="lazy">@else<span class="sp-promo-fallback"><x-category-visual :category="$category"/></span>@endif
                    </div>
                    <div class="sp-promo-copy"><span class="sp-promo-number">0{{ $loop->iteration }}</span><h3>{{ $category->name }}</h3><p>{{ $category->description ?: 'Explore products from this SHOPPICK category.' }}</p><span class="sp-promo-link">Shop {{ $category->name }} <span aria-hidden="true">→</span></span></div>
                </a>
            @empty
                <p class="sp-empty-state">Categories will appear here as approved shops publish products.</p>
            @endforelse
        </div>
    </section>

    <section id="newly-dropped" class="sp-frame sp-section sp-products-section" aria-labelledby="new-title">
        <div class="sp-section-heading">
            <div><p class="sp-eyebrow">Latest Products</p><h2 id="new-title">Newly Dropped Products</h2><p>Discover the latest products from our amazing sellers.</p></div>
            <a class="sp-text-link" href="{{ route('products.index', ['sort' => 'latest']) }}">See all new products <span aria-hidden="true">→</span></a>
        </div>
        @if($picks->isNotEmpty())
            <div class="sp-product-grid">@foreach($picks as $product)<x-landing-product-card :product="$product"/>@endforeach</div>
        @else
            <p class="sp-empty-state">No products are available right now. Please check back soon.</p>
        @endif
        <div class="sp-see-more"><a class="sp-button sp-button-outline" href="{{ route('products.index') }}">See More Products <span aria-hidden="true">→</span></a></div>
    </section>

    <section id="recommended" class="sp-frame sp-section sp-recommended-section" aria-labelledby="recommended-title">
        <div class="sp-section-heading sp-centered-heading"><div><p class="sp-eyebrow">A considered place to begin</p><h2 id="recommended-title">Most Recommended Collections For You</h2><p>Curated collections built from real SHOPPICK categories and products.</p></div></div>
        @if($collectionCategories->isNotEmpty())
            <div class="sp-collection-collage">
                @foreach($collectionCategories as $category)
                    @php
                        $collectionProduct = $category->landingProduct;
                        $collectionImage = $collectionProduct?->main_image
                            ? asset('storage/'.$collectionProduct->main_image)
                            : (filled($category->image) && \Illuminate\Support\Facades\Storage::disk('public')->exists($category->image) ? asset('storage/'.$category->image) : null);
                    @endphp
                    <a class="sp-collection-tile sp-collection-tile-{{ $loop->iteration }}" href="{{ route('products.index', ['category' => $category->id]) }}">
                        @if($collectionImage)<img src="{{ $collectionImage }}" alt="{{ $category->name }} collection" width="1200" height="900" loading="lazy">@else<span class="sp-collection-fallback"><x-category-visual :category="$category"/></span>@endif
                        <span class="sp-collection-overlay"><strong>{{ $category->name }}</strong><span>View collection <span aria-hidden="true">→</span></span></span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="sp-empty-state">Collections will appear here as products become available.</p>
        @endif
    </section>

    <section id="featured-picks" class="sp-frame sp-section sp-products-section sp-products-section-alt" aria-labelledby="featured-title">
        <div class="sp-section-heading">
            <div><p class="sp-eyebrow">Popular right now</p><h2 id="featured-title">Featured Collections</h2><p>More products worth a closer look, selected from the live marketplace.</p></div>
            <a class="sp-text-link" href="{{ route('products.index', ['sort' => 'popular']) }}">Browse popular products <span aria-hidden="true">→</span></a>
        </div>
        @if($popularPicks->isNotEmpty())
            <div class="sp-product-grid">@foreach($popularPicks as $product)<x-landing-product-card :product="$product"/>@endforeach</div>
        @else
            <p class="sp-empty-state">More picks will appear when the marketplace has additional products.</p>
        @endif
        <div class="sp-see-more"><a class="sp-button sp-button-outline" href="{{ route('products.index') }}">Explore More Products <span aria-hidden="true">→</span></a></div>
    </section>

    @if($shops->isNotEmpty())
        <section id="shops" class="sp-frame sp-section sp-shop-strip" aria-labelledby="shops-title">
            <div class="sp-section-heading"><div><p class="sp-eyebrow">Meet the marketplace</p><h2 id="shops-title">Shop from approved sellers</h2><p>See what real SHOPPICK shops are offering today.</p></div></div>
            <div class="sp-shop-grid">
                @foreach($shops as $shop)
                    <a class="sp-shop-mini" href="{{ route('shops.show', $shop->slug) }}">
                        @if($shop->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($shop->logo))<img src="{{ asset('storage/'.$shop->logo) }}" alt="{{ $shop->name }} logo" width="72" height="72" loading="lazy">@else<span class="sp-shop-mini-fallback"><x-seller.icon name="store"/></span>@endif
                        <span><strong>{{ $shop->name }}</strong><small>{{ $shop->products_count }} {{ \Illuminate\Support\Str::plural('product', $shop->products_count) }}</small></span><span class="sp-shop-arrow" aria-hidden="true">↗</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="sp-frame sp-final-section" aria-labelledby="final-shopping-title">
        <div class="sp-final-cta">
            <div class="sp-final-copy"><p class="sp-final-label">Your next pick starts here</p><h2 id="final-shopping-title">Discover More<br>With SHOPPICK.</h2><p>Great products. Trusted sellers. All for you.</p><a class="sp-button sp-button-light" href="{{ route('home') }}">Shop Now <span aria-hidden="true">→</span></a></div>
            <div class="sp-final-photo"><img src="{{ asset('images/landing/lifestyle-slide-1.jpg') }}" alt="A SHOPPICK shopper with a parcel" width="1122" height="1402" loading="lazy"></div>
        </div>
    </section>
</main>

<x-landing-footer/>
<script src="{{ asset('js/landing.js') }}" defer></script>
<script src="{{ asset('js/hero-carousel.js') }}?v={{ filemtime(public_path('js/hero-carousel.js')) }}" defer></script>
</body>
</html>
