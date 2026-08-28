@extends('layouts.storefront')

@section('title', 'My Wishlist')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold text-navy-800">My Wishlist</h1>

    @if($items->isEmpty())
        <div class="card flex flex-col items-center justify-center p-16 text-center">
            <svg class="h-20 w-20 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            <h3 class="mt-4 text-lg font-semibold text-navy-800">Your wishlist is empty</h3>
            <a href="{{ route('products.index') }}" class="btn-primary mt-4">Discover Products</a>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
            @foreach($items as $item)
                <div class="card overflow-hidden">
                    <a href="{{ route('products.show', $item->product->slug) }}" class="block aspect-square overflow-hidden bg-slate-100">
                        @if($item->product->getMainImageAttribute())
                            <img src="{{ asset('storage/'.$item->product->getMainImageAttribute()) }}" class="h-full w-full object-cover" alt="">
                        @endif
                    </a>
                    <div class="p-3">
                        <a href="{{ route('products.show', $item->product->slug) }}" class="line-clamp-1 text-sm font-medium text-navy-800 hover:text-brand-600">{{ $item->product->name }}</a>
                        <p class="mt-1 font-bold text-accent-600">₱{{ number_format($item->product->salePrice(), 2) }}</p>
                        <div class="mt-2 flex gap-2">
                            <form method="POST" action="{{ route('wishlist.move-to-cart', $item->id) }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn-primary btn-sm w-full" @if($item->product->isOutOfStock()) disabled @endif>Move to Cart</button>
                            </form>
                            <form method="POST" action="{{ route('wishlist.remove', $item->id) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-ghost btn-sm p-2" title="Remove">
                                    <svg class="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
