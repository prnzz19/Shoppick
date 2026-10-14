<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Find real products from approved shops in one easy place on SHOPPICK.">
    <title>SHOPPICK &mdash; Shop Easy. Pick Smart.</title>
    <link rel="preload" href="{{ asset('fonts/manrope/Manrope-Variable.ttf') }}" as="font" type="font/ttf" crossorigin>
    <script>window.APP_ROUTES = { autocomplete: @json(route('search.autocomplete')) };</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/landing-redesign.css') }}?v={{ filemtime(public_path('css/landing-redesign.css')) }}">
</head>
<body class="sp-landing">
<a class="sp-skip" href="#main">Skip to content</a>

<div class="sp-topbar">
    <div class="sp-frame sp-topbar-inner">
        <span>New finds from trusted SHOPPICK sellers, picked for everyday life.</span>
        @if(auth()->check() && auth()->user()->hasApprovedSellerAccess())
        <a href="{{ route('seller.dashboard') }}">Seller Dashboard <span aria-hidden="true">&rarr;</span></a>
        @else
        <a href="{{ route('seller.apply') }}">Sell on SHOPPICK <span aria-hidden="true">&rarr;</span></a>
        @endif
    </div>
</div>

<header class="sp-header">
    <nav class="sp-frame sp-nav" aria-label="Main navigation">
        <a class="sp-brand" href="{{ route('landing') }}" aria-label="SHOPPICK home">
            <x-shoppick.logo class="sp-brand-mark"/>
            <span><span class="sp-word-shop">SHOP</span><span class="sp-word-pick">PICK</span></span>
        </a>
        <div class="sp-nav-links">
            <a href="{{ route('landing') }}" aria-current="page">Home</a>
            <a href="{{ route('home') }}">Shop</a>
            <a href="#categories">Categories</a>
            <a href="#recommended">Collections</a>
            <a href="{{ route('products.index', ['sort' => 'latest']) }}">Deals</a>
        </div>
        <div class="sp-nav-actions">
            <div class="sp-header-search-wrap">
                <form class="sp-header-search" action="{{ route('products.index') }}" method="GET" role="search">
                    <x-landing-icon name="search"/>
                    <label class="sr-only" for="search-input">Search SHOPPICK</label>
                    <input id="search-input" name="q" type="search" autocomplete="off" placeholder="Search products, brands, or stores...">
                    <button type="submit" aria-label="Submit search"><x-landing-icon name="search"/></button>
                </form>
                <div id="search-suggestions" class="sp-search-suggestions" aria-live="polite"></div>
            </div>
            @auth
                <a class="sp-utility-link" href="{{ route('wishlist.index') }}" aria-label="Wishlist"><x-landing-icon name="heart"/><span>Wishlist</span></a>
                <a class="sp-utility-link" href="{{ route('cart.index') }}" aria-label="Cart"><x-landing-icon name="cart"/><span>Cart</span></a>
                <a class="sp-account-link" href="{{ route('account.profile') }}" aria-label="Account"><x-landing-icon name="user"/><span>Account</span></a>
            @else
                <a class="sp-utility-link" href="{{ route('login') }}" aria-label="Wishlist"><x-landing-icon name="heart"/><span>Wishlist</span></a>
                <a class="sp-utility-link" href="{{ route('cart.index') }}" aria-label="Cart"><x-landing-icon name="cart"/><span>Cart</span></a>
                <a class="sp-account-link" href="{{ route('login') }}" aria-label="Account"><x-landing-icon name="user"/><span>Account</span></a>
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

@php
    $categoryBySlug = $categories->keyBy(fn ($category) => \Illuminate\Support\Str::slug($category->name));
    $findCategory = function (array $slugs) use ($categoryBySlug) {
        foreach ($slugs as $slug) {
            if ($categoryBySlug->has($slug)) {
                return $categoryBySlug->get($slug);
            }
        }
        return null;
    };
    $categoryCards = [
        ['name' => 'Electronics', 'slugs' => ['electronics', 'technology'], 'image' => 'images/landing/category/electronics.png', 'alt' => 'Modern laptop, headphones, smartphone and speaker', 'description' => 'Smart tools and everyday tech for the way you work and play.'],
        ['name' => 'Home & Living', 'slugs' => ['home-living', 'home-and-living', 'home'], 'image' => 'images/landing/category/home-living.png', 'alt' => 'Minimal living room with sofa and wooden coffee table', 'description' => 'Comfortable, considered finds for every room.'],
        ['name' => 'Fashion', 'slugs' => ['fashion', 'clothing'], 'image' => 'images/landing/category/fashion.png', 'alt' => 'Neutral clothing and accessories displayed on a wooden rack', 'description' => 'Easy pieces and personal style for every day.'],
    ];
    $collectionCards = [
        ['name' => 'Home & Living', 'description' => 'Soft, useful pieces for the way you live.', 'image' => 'images/categories/home-living.jpg', 'category' => $findCategory(['home-living', 'home-and-living', 'home'])],
        ['name' => 'Beauty & Personal Care', 'description' => 'Small rituals that make everyday life feel better.', 'image' => 'storage/products/glamglow-skincare-set-catalog.png', 'category' => $findCategory(['beauty', 'beauty-personal-care'])],
        ['name' => 'Tech & Gadgets', 'description' => 'Useful upgrades for work, play, and everything between.', 'image' => 'storage/products/novabook-laptop-14-catalog.png', 'category' => $findCategory(['electronics', 'technology'])],
        ['name' => 'Decor Details', 'description' => 'Warm accents with a personal point of view.', 'image' => 'images/categories/decor.jpg', 'category' => $findCategory(['decor', 'home-decor', 'home-living'])],
    ];
    $filterCategories = $categories->filter(fn ($category) => (int) $category->products_count > 0)->take(6);
    $electronicsCategory = $findCategory(['electronics', 'technology']);
    $heroSlides = [
        [
            'eyebrow' => 'AMAZING PRODUCTS',
            'headline' => "Find Your<br>Next Favorite<br>Pick",
            'description' => 'Amazing products from trusted sellers, all in one easy place. Browse thoughtfully chosen finds and shop with confidence.',
            'primary_label' => 'Shop Now', 'primary_url' => route('home'),
            'secondary_label' => 'Explore Collections', 'secondary_url' => route('products.index'),
            'image' => 'images/landing/hero/hero-main.png', 'alt' => 'SHOPPICK lifestyle campaign with two shoppers and everyday picks.',
        ],
        [
            'eyebrow' => 'SMARTER EVERYDAY PICKS',
            'headline' => "Style Meets<br>Everyday Tech",
            'description' => 'Discover practical tech, accessories, and lifestyle essentials made for everyday use.',
            'primary_label' => 'Shop Electronics', 'primary_url' => $electronicsCategory ? route('products.index', ['category' => $electronicsCategory->id]) : route('products.index'),
            'secondary_label' => 'Explore Products', 'secondary_url' => route('products.index'),
            'image' => 'images/landing/hero/hero-lifestyle-01.png', 'alt' => 'SHOPPICK shoppers surrounded by headphones, a game console, and useful tech essentials.',
        ],
        [
            'eyebrow' => 'MORE TO DISCOVER',
            'headline' => "Everything You<br>Need, One Pick Away",
            'description' => 'Explore fashion, gadgets, home finds, accessories, and more from SHOPPICK sellers.',
            'primary_label' => 'Shop Now', 'primary_url' => route('home'),
            'secondary_label' => 'Browse Categories', 'secondary_url' => route('products.index'),
            'image' => 'images/landing/hero/hero-lifestyle-02.png', 'alt' => 'SHOPPICK lifestyle duo exploring fashion and technology essentials.',
        ],
    ];
@endphp

<main id="main" class="sp-landing-main">
    <section id="hero-carousel" class="sp-frame sp-hero" aria-roledescription="carousel" aria-label="SHOPPICK featured picks" data-hero-carousel>
        <div class="sp-hero-slides">
            @foreach($heroSlides as $index => $slide)
                <article class="sp-hero-slide{{ $index === 0 ? ' is-active' : '' }}" data-hero-slide="{{ $index }}" aria-hidden="{{ $index === 0 ? 'false' : 'true' }}" aria-labelledby="hero-title-{{ $index }}">
                    <div class="sp-hero-copy">
                        <p class="sp-eyebrow">{{ $slide['eyebrow'] }}</p>
                        <h1 id="hero-title-{{ $index }}">{!! $slide['headline'] !!}</h1>
                        <p class="sp-hero-intro">{{ $slide['description'] }}</p>
                        <div class="sp-actions">
                            <a class="sp-button sp-button-teal" href="{{ $slide['primary_url'] }}">{{ $slide['primary_label'] }} <span aria-hidden="true">&rarr;</span></a>
                            <a class="sp-button sp-button-outline" href="{{ $slide['secondary_url'] }}">{{ $slide['secondary_label'] }}</a>
                        </div>
                        <div class="sp-hero-note"><span>Fast shipping</span><i aria-hidden="true"></i><span>Trusted sellers</span><i aria-hidden="true"></i><span>24/7 support</span></div>
                    </div>
                    <div class="sp-lifestyle-hero">
                        <img src="{{ asset($slide['image']) }}" alt="{{ $slide['alt'] }}" width="2048" height="1152" {{ $index === 0 ? 'fetchpriority=high' : 'loading=lazy' }} decoding="async">
                    </div>
                </article>
            @endforeach
        </div>
        <div class="sp-hero-dots" role="tablist" aria-label="Choose hero slide">
            @foreach($heroSlides as $index => $slide)
                <button type="button" class="sp-hero-dot{{ $index === 0 ? ' is-active' : '' }}" data-hero-dot="{{ $index }}" role="tab" aria-label="Show slide {{ $index + 1 }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}"></button>
            @endforeach
        </div>
    </section>

    <section class="sp-frame sp-reference-intro" aria-labelledby="intro-title">
        <p class="sp-reference-kicker">A marketplace made for real life</p>
        <h2 id="intro-title">Good things, found simply.</h2>
        <p>Browse useful, beautiful products from trusted local sellers and discover the pieces that make everyday life feel a little better.</p>
        <a class="sp-text-link" href="{{ route('home') }}">Explore the marketplace <span aria-hidden="true">&rarr;</span></a>
    </section>

    <section class="sp-frame sp-reference-benefits" aria-label="SHOPPICK service benefits">
        <div class="sp-benefit"><span class="sp-benefit-icon" aria-hidden="true">+</span><div><strong>Fast shipping</strong><small>Carefully packed and sent your way.</small></div></div>
        <div class="sp-benefit"><span class="sp-benefit-icon" aria-hidden="true">OK</span><div><strong>Secure checkout</strong><small>Shop with confidence every time.</small></div></div>
        <div class="sp-benefit"><span class="sp-benefit-icon" aria-hidden="true">R</span><div><strong>Easy returns</strong><small>Simple help when plans change.</small></div></div>
        <div class="sp-benefit"><span class="sp-benefit-icon" aria-hidden="true">@</span><div><strong>Trusted support</strong><small>People are here when you need them.</small></div></div>
    </section>

    <section id="categories" class="sp-frame sp-section sp-promo-section" aria-labelledby="category-title">
        <div class="sp-section-heading sp-centered-heading">
            <div>
                <h2 id="category-title">Shop by Category</h2>
                <p>Start with a space, routine, or style you want to make better.</p>
            </div>
        </div>
        <div class="sp-promo-grid">
            @foreach($categoryCards as $card)
                @php
                    $category = $findCategory($card['slugs']);
                    $categoryUrl = $category ? route('products.index', ['category' => $category->id]) : route('products.index');
                @endphp
                <a class="sp-promo-card sp-reveal" href="{{ $categoryUrl }}" aria-label="Shop {{ $card['name'] }}">
                    <span class="sp-promo-media"><img src="{{ asset($card['image']) }}" alt="{{ $card['alt'] ?? $card['name'] }}" width="2048" height="1152" loading="lazy"></span>
                    <div class="sp-promo-copy">
                        <span class="sp-promo-number">0{{ $loop->iteration }}</span>
                        <h3>{{ $card['name'] }}</h3>
                        <p>{{ $card['description'] }}</p>
                        <span class="sp-promo-link">Shop {{ $card['name'] }} <span aria-hidden="true">&rarr;</span></span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section id="newly-dropped" class="sp-frame sp-section sp-products-section" aria-labelledby="new-title">
        <div class="sp-section-heading">
            <div>
                <h2 id="new-title">Newly Dropped Products</h2>
                <p>Discover the latest products from our amazing sellers.</p>
            </div>
            <a class="sp-text-link" href="{{ route('products.index', ['sort' => 'latest']) }}">See all new picks <span aria-hidden="true">&rarr;</span></a>
        </div>
        <div class="sp-filter-pills" aria-label="Filter featured products">
            <a class="sp-filter-pill is-active" href="{{ route('products.index') }}">All</a>
            @foreach($filterCategories as $filterCategory)
                <a class="sp-filter-pill" href="{{ route('products.index', ['category' => $filterCategory->id]) }}">{{ $filterCategory->name }}</a>
            @endforeach
        </div>
        @if($picks->isNotEmpty())
            <div class="sp-product-grid">
                @foreach($picks->values() as $product)
                    <x-landing-product-card :product="$product"/>
                @endforeach
            </div>
        @else
            <p class="sp-empty-state">No products are available right now. Please check back soon.</p>
        @endif
        <div class="sp-see-more"><a class="sp-button sp-button-outline" href="{{ route('products.index') }}">See More Products <span aria-hidden="true">&rarr;</span></a></div>
    </section>

    <section class="sp-frame sp-reference-feature-split" aria-label="SHOPPICK featured stories">
        @php $homeCategory = $findCategory(['home-living', 'home-and-living', 'home']); @endphp
        <a class="sp-reference-feature-card sp-reference-feature-light sp-reveal" href="{{ $homeCategory ? route('products.index', ['category' => $homeCategory->id]) : route('products.index') }}">
            <img src="{{ asset('images/categories/home-living.jpg') }}" alt="Home and Living collection" width="1200" height="800" loading="lazy">
            <span class="sp-reference-feature-overlay">
                <strong>Make space for better days.</strong>
                <small>Home &amp; Living finds with a softer point of view.</small>
                <span>Shop Home &amp; Living <span aria-hidden="true">&rarr;</span></span>
            </span>
        </a>
        <a class="sp-reference-feature-card sp-reference-feature-dark sp-reveal" href="{{ route('products.index', ['sort' => 'popular']) }}">
            <img src="{{ asset('images/landing/campaign-model-original.jpg') }}" alt="SHOPPICK style edit" width="1200" height="800" loading="lazy">
            <span class="sp-reference-feature-overlay">
                <strong>Good finds, picked with care.</strong>
                <small>Explore the products shoppers keep coming back to.</small>
                <span>View the edit <span aria-hidden="true">&rarr;</span></span>
            </span>
        </a>
    </section>
    <section id="recommended" class="sp-frame sp-section sp-recommended-section" aria-labelledby="recommended-title">
        <div class="sp-section-heading sp-centered-heading">
            <div>
                <h2 id="recommended-title">Most Recommended Collections For You</h2>
                <p>Curated collections from real SHOPPICK categories, ready to explore.</p>
            </div>
        </div>
        <div class="sp-collection-collage">
            @foreach($collectionCards as $card)
                @php
                    $collectionUrl = $card['category'] ? route('products.index', ['category' => $card['category']->id]) : route('products.index');
                @endphp
                <a class="sp-collection-tile sp-collection-tile-{{ $loop->iteration }} sp-reveal" href="{{ $collectionUrl }}" aria-label="View {{ $card['name'] }} collection">
                    <img src="{{ asset($card['image']) }}" alt="{{ $card['name'] }}" width="1000" height="700" loading="lazy">
                    <div class="sp-collection-overlay">
                        <div>
                            <strong>{{ $card['name'] }}</strong>
                            <small>{{ $card['description'] }}</small>
                        </div>
                        <span>View collection <span aria-hidden="true">&rarr;</span></span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section id="featured-picks" class="sp-frame sp-section sp-products-section sp-products-section-alt" aria-labelledby="featured-title">
        <div class="sp-section-heading">
            <div>
                <h2 id="featured-title">Featured Products</h2>
                <p>Another set of real SHOPPICK products, selected from active listings.</p>
            </div>
            <a class="sp-text-link" href="{{ route('products.index', ['sort' => 'popular']) }}">Explore popular picks <span aria-hidden="true">&rarr;</span></a>
        </div>
        @if($popularPicks->isNotEmpty())
            <div class="sp-product-grid">
                @foreach($popularPicks->values() as $product)
                    <x-landing-product-card :product="$product"/>
                @endforeach
            </div>
        @else
            <p class="sp-empty-state">More picks will appear when the marketplace has additional products.</p>
        @endif
        <div class="sp-see-more"><a class="sp-button sp-button-outline" href="{{ route('products.index') }}">See More Products <span aria-hidden="true">&rarr;</span></a></div>
    </section>

    <div class="sp-frame sp-reference-ticker" aria-label="SHOPPICK promise">
        <span>Shop smart</span><i aria-hidden="true">*</i><span>Pick well</span><i aria-hidden="true">*</i><span>Live beautifully</span>
    </div>
    <x-landing-logistics-section/>
</main>

<x-landing-footer/>
<script src="{{ asset('js/landing.js') }}" defer></script>
<script src="{{ asset('js/hero-carousel.js') }}?v={{ filemtime(public_path('js/hero-carousel.js')) }}" defer></script>
</body>
</html>
