@props(['shops'])
<section id="shops" class="sp-wrap sp-section sp-shops-section sp-opportunities">
    <div class="sp-section-heading"><div><p class="sp-eyebrow">Meet the marketplace</p><h2>Featured Shops</h2><p>View products from approved shops.</p></div></div><div class="sp-opportunity-grid">
    @forelse($shops as $shop)
        <article class="sp-shop-card">
            <div class="sp-shop-top">
                @if($shop->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($shop->logo))
                    <img src="{{ asset('storage/'.$shop->logo) }}" alt="{{ $shop->name }} logo" width="64" height="64" loading="lazy">
                @else
                    <span class="sp-shop-placeholder"><x-seller.icon name="store"/></span>
                @endif
                <div><h3>{{ $shop->name }}</h3><p>{{ $shop->products_count }} listed {{ \Illuminate\Support\Str::plural('product', $shop->products_count) }}</p></div>
            </div>
            <p class="sp-shop-description">{{ \Illuminate\Support\Str::limit($shop->description ?: 'Browse the current products in this shop.', 130) }}</p>
            <a class="sp-button sp-button-outline" href="{{ route('shops.show', $shop->slug) }}">View Shop <span aria-hidden="true">&rarr;</span></a>
        </article>
    @empty
        <p>Shop collections will appear here when approved sellers have published products.</p>
    @endforelse
    </div>
</section>
<section id="community" class="sp-wrap sp-partner-section" aria-label="Join SHOPPICK">
    <article class="sp-join-card sp-join-seller"><span class="sp-join-icon"><x-seller.icon name="store"/></span><h3>Have Something to Sell?</h3><p>Open your shop on SHOPPICK and start reaching more customers.</p><a class="sp-button sp-button-teal" href="{{ (auth()->check() ? auth()->user()->sellerAction()['url'] : route('seller.apply')) }}">{{ auth()->check() ? auth()->user()->sellerAction()['label'] : 'Start Selling' }} <span aria-hidden="true">&rarr;</span></a></article>
    <article class="sp-join-card sp-join-rider"><span class="sp-join-icon"><x-landing-icon name="delivery"/></span><h3>Deliver with SHOPPICK</h3><p>Join the SHOPPICK delivery team and help customers receive their orders.</p><a class="sp-button sp-button-outline" href="{{ route('register.rider') }}">Apply as Rider <span aria-hidden="true">&rarr;</span></a></article>
</section>
