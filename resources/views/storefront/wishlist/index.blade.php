@extends('layouts.storefront')

@section('title', 'My Wishlist')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-navy-800">My Wishlist</h1>
        <p class="mt-1 text-sm text-slate-500">Save products you love and find them here.</p>
    </div>

    @if($items->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <svg class="h-20 w-20 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-navy-800">Your wishlist is empty.</h3>
            <p class="mt-1 text-sm text-slate-500">Save products you love and find them here.</p>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Discover Products</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($items as $item)
                @php
                    $product = $item->product;
                    $publiclyAvailable = $availableProductIds->contains($product->id);
                    $canPurchase = $publiclyAvailable && ! $product->isOutOfStock();
                    $hasVariants = $product->variants->isNotEmpty();
                    $status = $product->trashed() ? 'Archived' : (! $publiclyAvailable ? 'Unavailable' : ($product->isOutOfStock() ? 'Out of Stock' : 'In Stock'));
                @endphp
                <article class="card flex overflow-hidden sm:flex-col">
                    @if($publiclyAvailable)
                        <a href="{{ route('products.show', $product->slug) }}" class="block h-36 w-36 shrink-0 overflow-hidden bg-slate-100 sm:aspect-square sm:h-auto sm:w-full">
                    @else
                        <div class="block h-36 w-36 shrink-0 overflow-hidden bg-slate-100 sm:aspect-square sm:h-auto sm:w-full">
                    @endif
                        @if($product->getMainImageAttribute())
                            <img src="{{ asset('storage/'.$product->getMainImageAttribute()) }}" class="h-full w-full object-cover {{ $publiclyAvailable ? '' : 'opacity-60 grayscale' }}" alt="{{ $product->name }}">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-slate-300"><svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4-4 4 4 4-5 4 5M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg></div>
                        @endif
                    @if($publiclyAvailable)</a>@else</div>@endif
                    <div class="flex min-w-0 flex-1 flex-col p-4">
                        @if($publiclyAvailable)<a href="{{ route('products.show', $product->slug) }}" class="line-clamp-2 text-sm font-semibold text-navy-800 hover:text-brand-600">{{ $product->name }}</a>@else<p class="line-clamp-2 text-sm font-semibold text-slate-600">{{ $product->name }}</p>@endif
                        <p class="mt-1 truncate text-xs text-brand-600">{{ $product->store?->name ?? 'SHOPPICK Marketplace' }}</p>
                        <div class="mt-1 flex items-center gap-1 text-xs"><span class="text-sun-400">★</span><span class="font-medium text-navy-700">{{ number_format($product->rating_avg,1) }}</span><span class="text-slate-400">({{ $product->rating_count }})</span></div>
                        <p class="mt-2 font-bold text-accent-600">₱{{ number_format($product->salePrice(), 2) }}</p>
                        <span class="mt-2 w-fit rounded-full px-2 py-0.5 text-xs font-semibold {{ $canPurchase ? 'bg-leaf-100 text-leaf-500' : 'bg-slate-100 text-slate-500' }}">{{ $status }}</span>

                        <div class="mt-auto grid grid-cols-2 gap-2 pt-4">
                            @if($canPurchase && ! $hasVariants)
                                <form method="POST" action="{{ route('wishlist.move-to-cart', $item->id) }}">@csrf<input type="hidden" name="quantity" value="1"><button type="submit" class="btn-outline btn-sm w-full">Add to Cart</button></form>
                                <form method="POST" action="{{ route('buy-now') }}">@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="quantity" value="1"><button type="submit" class="btn-primary btn-sm w-full">Buy Now</button></form>
                            @elseif($canPurchase)
                                <a href="{{ route('products.show',$product->slug) }}" class="btn-outline btn-sm col-span-2 text-center">Select Options</a>
                            @else
                                <button type="button" disabled class="btn-outline btn-sm col-span-2 opacity-50">Unavailable</button>
                            @endif
                            <form method="POST" action="{{ route('wishlist.remove', $item->id) }}" class="col-span-2">@csrf @method('DELETE')<button type="submit" class="w-full rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Remove</button></form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
