@props(['product'])

@php
    $isWishlisted = in_array($product->id, $sharedWishlistProductIds ?? [], true);
    $imagePath = $product->main_image;
@endphp

<article class="sp-product-card">
    <div class="sp-product-media">
        <a class="sp-product-image-link" href="{{ route('products.show', $product->slug) }}" aria-label="View {{ $product->name }}">
            @if($imagePath)
                <img src="{{ asset('storage/'.$imagePath) }}" alt="{{ $product->name }}" width="720" height="720" loading="lazy">
            @else
                <span class="sp-product-image-fallback"><x-shoppick.logo/></span>
            @endif
        </a>
        @if($product->hasDiscount())
            <span class="sp-product-badge">-{{ (int) $product->discount }}%</span>
        @endif
        <button type="button" class="sp-wishlist {{ $isWishlisted ? 'is-active' : '' }}" onclick="toggleWishlist(event)" data-wishlist data-product-id="{{ $product->id }}" aria-label="{{ $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' }}" aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}" title="{{ $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' }}">
            <svg data-wishlist-icon viewBox="0 0 24 24" fill="{{ $isWishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20.8 8.7c0 5.5-8.8 10.3-8.8 10.3S3.2 14.2 3.2 8.7A4.7 4.7 0 0 1 12 6.1a4.7 4.7 0 0 1 8.8 2.6Z"/></svg>
        </button>
    </div>

    <div class="sp-product-details">
        <div class="sp-product-meta">
            <span>{{ $product->category?->name ?? 'SHOPPICK pick' }}</span>
            @if($product->store)<a href="{{ route('shops.show', $product->store->slug) }}">{{ $product->store->name }}</a>@endif
        </div>
        <a class="sp-product-name" href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
        @if($product->rating_count > 0)
            <div class="sp-product-rating" aria-label="{{ number_format($product->rating_avg, 1) }} out of 5 from {{ number_format($product->rating_count) }} ratings">
                <span aria-hidden="true">★★★★★</span><small>{{ number_format($product->rating_avg, 1) }} ({{ number_format($product->rating_count) }})</small>
            </div>
        @endif
        <div class="sp-product-price-row">
            <strong>₱{{ number_format($product->salePrice(), 2) }}</strong>
            @if($product->hasDiscount())<del>₱{{ number_format($product->originalPrice(), 2) }}</del>@endif
        </div>
        <div class="sp-product-actions">
            <button type="button" class="sp-card-action sp-card-action-cart" onclick="quickAdd(event)" data-product-id="{{ $product->id }}" @disabled($product->isOutOfStock())>Add to Cart</button>
            <form method="POST" action="{{ route('buy-now') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="sp-card-action sp-card-action-buy" @disabled($product->isOutOfStock())>Buy Now</button>
            </form>
        </div>
    </div>
</article>
