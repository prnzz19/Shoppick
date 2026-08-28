@props(['product'])

<div class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
    <a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-square overflow-hidden bg-slate-100">
        @if($product->getMainImageAttribute())
            <img src="{{ asset('storage/'.$product->getMainImageAttribute()) }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy">
        @else
            <div class="flex h-full w-full items-center justify-center text-slate-300">
                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        @endif

        @if($product->hasDiscount())
            <span class="absolute left-2 top-2 rounded-lg tag-gradient px-2 py-0.5 text-xs font-bold text-white shadow">-{{ (int)$product->discount }}%</span>
        @endif
        @if($product->isOutOfStock())
            <div class="absolute inset-0 flex items-center justify-center bg-black/50">
                <span class="rounded-full bg-white px-4 py-1.5 text-sm font-bold text-navy-800">Out of Stock</span>
            </div>
        @endif

        {{-- Wishlist --}}
        @auth
            <button type="button" onclick="toggleWishlist(event, {{ $product->id }})" class="absolute right-2 top-2 rounded-full bg-white/90 p-2 text-navy-700 shadow backdrop-blur hover:text-rose-500"
                data-wishlist data-product-id="{{ $product->id }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </button>
        @endauth
    </a>

    <div class="flex flex-1 flex-col p-3">
        <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ $product->brand ?? $product->category?->name }}</p>
        <a href="{{ route('products.show', $product->slug) }}" class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-sm font-medium text-navy-800 hover:text-brand-600">{{ $product->name }}</a>
        @if($product->store)<a href="{{ route('shops.show',$product->store->slug) }}" class="mt-1 truncate text-xs text-brand-600">{{ $product->store->name }}</a>@endif

        <div class="mt-1 flex items-center gap-1 text-xs">
            <span class="flex items-center gap-0.5 text-sun-400">
                @for($i=1;$i<=5;$i++)
                    <svg class="h-3.5 w-3.5 {{ $i <= round($product->rating_avg) ? 'fill-sun-400' : 'fill-slate-200' }}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                @endfor
            </span>
            @if($product->rating_count > 0)<span class="ml-1 text-slate-400">{{ $product->sold_count > 0 ? number_format($product->sold_count).' sold' : '' }}</span>@endif
        </div>

        <div class="mt-auto flex items-end justify-between pt-2">
            <div>
                <p class="text-base font-bold text-navy-800">₱{{ number_format($product->salePrice(), 2) }}</p>
                @if($product->hasDiscount())
                    <p class="text-xs text-slate-400 line-through">₱{{ number_format($product->originalPrice(), 2) }}</p>
                @endif
            </div>
            <button type="button" onclick="quickAdd(event, {{ $product->id }})" class="rounded-lg bg-brand-500 p-2 text-white shadow-sm transition hover:bg-brand-600 disabled:opacity-40" title="Add to cart" @if($product->isOutOfStock()) disabled @endif>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </button>
        </div>
    </div>
</div>
