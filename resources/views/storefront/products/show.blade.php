@extends('layouts.storefront')

@section('title', $product->name)

@section('content')
@php
    $isWishlisted = in_array($product->id, $sharedWishlistProductIds ?? [], true);
@endphp
<div class="mx-auto max-w-7xl px-4 py-6">
    {{-- Breadcrumb --}}
    <nav class="mb-4 text-sm text-slate-500">
        <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a>
        <span class="mx-1">/</span>
        @if($product->category)<a href="{{ route('products.category', $product->category->id) }}" class="hover:text-brand-600">{{ $product->category->name }}</a>@endif
        <span class="mx-1">/</span>
        <span class="text-navy-800">{{ \Illuminate\Support\Str::limit($product->name, 40) }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- Gallery --}}
        <div>
            <div class="card overflow-hidden">
                <div id="main-image" class="flex aspect-square items-center justify-center bg-slate-50">
                    @php $first = $product->images->first(); @endphp
                    @if($first)
                        <img id="main-img-el" src="{{ asset('storage/'.$first->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-slate-300"><svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                    @endif
                </div>
            </div>
            @if($product->images->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach($product->images as $img)
                        <button type="button" onclick="setMainImage(this, '{{ asset('storage/'.$img->path) }}')" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border-2 {{ $loop->first ? 'border-brand-500' : 'border-slate-200' }} bg-slate-50">
                            <img src="{{ asset('storage/'.$img->path) }}" class="h-full w-full object-cover" alt="">
                        </button>
                    @endforeach
                </div>
            @endif

            @auth
            <div x-data="{open:false}" class="mt-5 border-t pt-4">
                <button type="button" @click="open=!open" class="text-sm font-semibold text-slate-500 hover:text-rose-600">Report Product</button>
                <form x-show="open" x-cloak method="POST" action="{{ route('products.report',$product) }}" enctype="multipart/form-data" class="mt-3 space-y-3 rounded-xl bg-slate-50 p-4">@csrf
                    <select name="reason" class="input" required><option value="">Select reason</option>@foreach(['prohibited_product'=>'Prohibited/restricted product','misleading_information'=>'Misleading information','fake_product'=>'Fake product','scam_suspicion'=>'Scam suspicion','inappropriate_product_image'=>'Inappropriate image','counterfeit_suspicion'=>'Counterfeit suspicion','incorrect_listing'=>'Incorrect listing','other'=>'Other'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                    <textarea name="description" class="input" maxlength="2000" required placeholder="Briefly describe the concern"></textarea>
                    <input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp" class="input">
                    <button class="btn-primary btn-sm">Submit Report</button>
                </form>
            </div>
            @endauth
        </div>

        {{-- Info --}}
        <div>
            <div class="flex items-start justify-between gap-4">
                <h1 class="text-2xl font-bold text-navy-800">{{ $product->name }}</h1>
                <button type="button" onclick="toggleWishlist(event)" class="shrink-0 rounded-xl border border-slate-200 p-2.5 transition hover:border-rose-200 hover:text-rose-500 disabled:opacity-60 {{ $isWishlisted ? 'text-rose-500' : 'text-navy-700' }}" data-wishlist data-product-id="{{ $product->id }}" aria-label="{{ $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' }}" aria-pressed="{{ $isWishlisted ? 'true' : 'false' }}" title="{{ $isWishlisted ? 'Remove from wishlist' : 'Add to wishlist' }}">
                    <svg data-wishlist-icon class="h-6 w-6" fill="{{ $isWishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </button>
            </div>

            @if($product->brand)<p class="mt-1 text-sm text-slate-500">Brand: <span class="font-semibold text-navy-700">{{ $product->brand }}</span></p>@endif
            @if($product->store)<p class="mt-2 text-sm text-slate-500">Sold by <a class="font-bold text-brand-600 hover:underline" href="{{ route('shops.show',$product->store->slug) }}">{{ $product->store->name }}</a> · {{ $product->store->location }}</p>@endif

            {{-- Rating & sold --}}
            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                <a href="#reviews" class="flex items-center gap-1 text-sun-400">
                    @for($i=1;$i<=5;$i++)<svg class="h-4 w-4 {{ $i <= round($product->rating_avg) ? 'fill-sun-400' : 'fill-slate-200' }}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                    <span class="font-semibold text-navy-800">{{ number_format($product->rating_avg, 1) }}</span>
                    <span class="text-slate-500">({{ $product->rating_count }} ratings)</span>
                </a>
                <span class="text-slate-300">|</span>
                <span class="text-slate-500">{{ number_format($product->sold_count) }} sold</span>
            </div>

            {{-- Price --}}
            <div class="mt-4 rounded-2xl bg-brand-50 p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-3xl font-extrabold text-accent-600">₱{{ number_format($product->salePrice(), 2) }}</span>
                    @if($product->hasDiscount())
                        <span class="text-base text-slate-400 line-through">₱{{ number_format($product->originalPrice(), 2) }}</span>
                        <span class="tag-gradient rounded-lg px-2 py-0.5 text-xs font-bold text-white">-{{ (int)$product->discount }}%</span>
                    @endif
                </div>
            </div>

            {{-- Stock --}}
            <div class="mt-3 text-sm">
                @if($product->isOutOfStock())
                    <span class="badge bg-rose-100 text-rose-600">Out of stock</span>
                @elseif($product->isLowStock())
                    <span class="badge bg-sun-100 text-sun-500">Only {{ $product->stock }} left</span>
                @else
                    <span class="badge bg-leaf-100 text-leaf-500">In stock ({{ $product->stock }})</span>
                @endif
            </div>

            {{-- Variants --}}
            <form method="POST" action="{{ route('cart.add') }}" id="product-form">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="product_variant_id" id="selected-variant" value="">
                <input type="hidden" name="quantity" id="selected-qty" value="1">

                @if($product->variants->isNotEmpty())
                    @php $grouped = $product->variants->groupBy('type'); @endphp
                    <div class="mt-5 space-y-4">
                        @foreach($grouped as $type => $options)
                            <div>
                                <p class="label">{{ ucfirst($type) }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($options as $option)
                                        <button type="button" data-variant-id="{{ $option->id }}" onclick="selectVariant(this, {{ $option->id }})"
                                            data-variant-stock="{{ $option->stock }}"
                                            class="chip border-slate-200 bg-white text-navy-700 hover:border-brand-400 disabled:cursor-not-allowed disabled:opacity-40" @disabled($option->stock < 1)>
                                            {{ $option->value }} @if($option->stock < 1)(Out of stock)@endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Quantity + actions --}}
                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <div class="flex items-center rounded-xl border border-slate-300">
                        <button type="button" onclick="changeQty(-1)" class="px-3 py-2 text-lg text-slate-500 hover:text-brand-600">−</button>
                        <span id="qty-display" class="w-10 text-center text-sm font-semibold">1</span>
                        <button type="button" onclick="changeQty(1)" class="px-3 py-2 text-lg text-slate-500 hover:text-brand-600">+</button>
                    </div>
                    <div class="flex flex-1 flex-wrap gap-2">
                        <button type="submit" class="btn-accent flex-1 whitespace-nowrap" @if($product->isOutOfStock()) disabled @endif>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Add to Cart
                        </button>
                        <button type="submit" formaction="{{ route('buy-now') }}" class="btn-primary flex-1 whitespace-nowrap" @if($product->isOutOfStock()) disabled @endif>Buy Now</button>
                    </div>
                </div>
            </form>

            {{-- Description --}}
            @if($product->description)
            <div class="mt-6">
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-navy-800">Description</h3>
                <p class="text-sm leading-relaxed text-slate-600">{{ $product->description }}</p>
            </div>
            @endif

            {{-- Specifications --}}
            @if($product->specifications)
            <div class="mt-5">
                <h3 class="mb-2 text-sm font-bold uppercase tracking-wide text-navy-800">Specifications</h3>
                <div class="overflow-hidden rounded-xl border border-slate-100">
                    <table class="w-full text-sm">
                        @foreach($product->specifications as $key => $value)
                            <tr class="{{ $loop->even ? 'bg-slate-50' : 'bg-white' }}">
                                <td class="px-3 py-2 font-medium text-slate-600">{{ $key }}</td>
                                <td class="px-3 py-2 text-navy-800">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Reviews --}}
    <div id="reviews" class="mt-12">
        <div class="mb-5 flex items-end justify-between">
            <h2 class="text-xl font-bold text-navy-800">Customer Reviews ({{ $product->rating_count }})</h2>
        </div>

        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            {{-- Summary --}}
            <div class="card p-5 text-center">
                <p class="text-4xl font-extrabold text-navy-800">{{ number_format($product->rating_avg, 1) }}</p>
                <div class="mt-1 flex justify-center text-sun-400">
                    @for($i=1;$i<=5;$i++)<svg class="h-5 w-5 {{ $i <= round($product->rating_avg) ? 'fill-sun-400' : 'fill-slate-200' }}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ $product->rating_count }} ratings</p>

                <div class="mt-4 space-y-1.5 text-left">
                    @foreach([5,4,3,2,1] as $star)
                        @php $count = $ratingCounts[$star] ?? 0; $pct = $product->rating_count > 0 ? round($count / $product->rating_count * 100) : 0; @endphp
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-6 text-slate-500">{{ $star }}★</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-sun-400" style="width: {{ $pct }}%"></div></div>
                            <span class="w-8 text-right text-slate-500">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Review list --}}
            <div class="space-y-4">
                @forelse($product->reviews as $review)
                    <div class="card p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-600">{{ strtoupper(substr($review->user->name ?? 'U', 0, 1)) }}</span>
                                <div>
                                    <p class="text-sm font-semibold text-navy-800">{{ $review->user->name ?? 'Customer' }}</p>
                                    <div class="flex text-sun-400">
                                        @for($i=1;$i<=5;$i++)<svg class="h-3.5 w-3.5 {{ $i <= $review->rating ? 'fill-sun-400' : 'fill-slate-200' }}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs text-slate-400">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-600">{{ $review->comment }}</p>
                    </div>
                @empty
                    <div class="card flex flex-col items-center justify-center p-10 text-center text-slate-400">
                        <p class="text-sm">No reviews yet. Be the first to review this product!</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Related --}}
    @if($related->isNotEmpty())
    <div class="mt-12">
        <x-section-heading title="You May Also Like" subtitle="Similar products customers love" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
            @foreach($related as $rel)
                <x-product-card :product="$rel" />
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    let selectedVariantEl = null;
    function setMainImage(btn, url) {
        document.getElementById('main-img-el').src = url;
        document.querySelectorAll('#main-image + div button, .mt-3 button').forEach(b => b.classList.remove('border-brand-500'));
        btn.classList.add('border-brand-500');
    }
    function selectVariant(btn, variantId) {
        document.getElementById('selected-variant').value = variantId;
        document.querySelectorAll('[data-variant-id]').forEach(b => b.classList.remove('border-brand-400', 'bg-brand-50', 'text-brand-600'));
        btn.classList.add('border-brand-400', 'bg-brand-50', 'text-brand-600');
        selectedVariantEl = btn;
        const max = parseInt(btn.dataset.variantStock || '1');
        const qty = Math.min(parseInt(document.getElementById('selected-qty').value), max);
        document.getElementById('selected-qty').value = qty;
        document.getElementById('qty-display').textContent = qty;
    }
    function changeQty(delta) {
        const el = document.getElementById('qty-display');
        let q = parseInt(el.textContent) + delta;
        const max = selectedVariantEl ? parseInt(selectedVariantEl.dataset.variantStock || '1') : {{ $product->stock ?: 1 }};
        q = Math.max(1, Math.min(q, max));
        el.textContent = q;
        document.getElementById('selected-qty').value = q;
    }
</script>
@endpush
