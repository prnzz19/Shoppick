@extends('layouts.storefront')

@section('title', request('q') ? 'Search: '.request('q') : (request('category') ? app('db')->table('categories')->find(request('category'))?->name ?? 'Products' : 'All Products'))

@section('content')
<div class="w-full px-4 py-6 sm:px-6 lg:px-8">
    <div class="grid items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[300px_minmax(0,1fr)]">
        {{-- Filters sidebar --}}
        <aside class="hidden self-start lg:block">
            <div class="card sticky top-28 max-h-[calc(100vh-8rem)] overflow-y-auto p-5">
                <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Filters</h3>

                {{-- Categories --}}
                <div class="mb-5">
                    <p class="mb-2.5 text-sm font-semibold text-slate-600">Category</p>
                    <div class="space-y-1.5">
                        <a href="{{ route('products.index', collect(request()->except('category'))->toArray()) }}" class="group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition hover:bg-brand-50 {{ !request('category') ? 'bg-brand-50 font-semibold text-brand-600' : 'text-navy-700' }}"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600 transition group-hover:scale-105"><x-category-icon name="All Products" class="h-5 w-5" /></span><span>All</span></a>
                        @foreach($categories as $cat)
                            <div>
                                <a href="{{ route('products.index', array_merge(collect(request()->except('category'))->toArray(), ['category' => $cat->id])) }}" class="group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition hover:bg-brand-50 {{ (string)request('category') === (string)$cat->id ? 'bg-brand-50 font-semibold text-brand-600' : 'font-medium text-navy-700' }}"><span class="h-7 w-7 shrink-0 transition group-hover:scale-105"><x-category-visual :category="$cat" icon-class="h-5 w-5" /></span><span class="min-w-0 truncate">{{ $cat->name }}</span></a>
                                @if($cat->children->isNotEmpty())
                                    <div class="ml-[2.15rem] mt-1 space-y-0.5 border-l border-slate-200 pl-3">
                                        @foreach($cat->children as $child)
                                            <a href="{{ route('products.index', array_merge(collect(request()->except('category'))->toArray(), ['category' => $child->id])) }}" class="block rounded-lg px-2.5 py-1.5 text-sm transition {{ (string)request('category') === (string)$child->id ? 'bg-brand-50 font-semibold text-brand-600' : 'text-slate-500 hover:bg-slate-50 hover:text-brand-600' }}">{{ $child->name }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <form method="GET" action="{{ route('products.index') }}" class="space-y-5 border-t border-slate-100 pt-5">
                    @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                    @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif

                    {{-- Price --}}
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Price Range</p>
                        <div class="flex gap-2">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="input !py-1.5 text-xs">
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="input !py-1.5 text-xs">
                        </div>
                    </div>

                    {{-- Rating --}}
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Rating</p>
                        <select name="rating" class="input !py-1.5 text-sm">
                            <option value="">Any rating</option>
                            @foreach([4,3,2,1] as $r)
                                <option value="{{ $r }}" @selected((string)request('rating') === (string)$r)>{{ $r }}+ stars</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Availability --}}
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-navy-700">
                        <input type="checkbox" name="availability" value="in_stock" @checked(request('availability') === 'in_stock') class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-300">
                        In stock
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-navy-700">
                        <input type="checkbox" name="discount" value="1" @checked(request()->has('discount')) class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-300">
                        On sale
                    </label>

                    @if($brands->isNotEmpty())
                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-600">Brand</p>
                        <select name="brand" class="input !py-1.5 text-sm">
                            <option value="">All brands</option>
                            @foreach($brands as $brand)
                                <option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="btn-primary btn-sm flex-1">Apply</button>
                        <a href="{{ route('products.index') }}" class="btn-ghost btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </aside>

        {{-- Results --}}
        <div>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-lg font-bold text-navy-800">
                    @if(request('q'))Results for "{{ request('q') }}"@elseif(request('category')){{ app('db')->table('categories')->find(request('category'))?->name ?? 'Products' }}@else All Products @endif
                    <span class="text-sm font-normal text-slate-500">({{ $products->total() }})</span>
                </h1>
                <form method="GET" action="{{ route('products.index') }}" class="ml-auto">
                    @foreach(request()->except(['sort', 'page']) as $key => $val)
                        @if(is_array($val)) @foreach($val as $v)<input type="hidden" name="{{ $key }}[]" value="{{ $v }}">@endforeach @else<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
                    @endforeach
                    <select name="sort" onchange="this.form.submit()" class="input !w-auto !py-2 text-sm">
                        <option value="relevance" @selected(request('sort') === 'relevance' || !request('sort'))>Sort: Relevance</option>
                        <option value="latest" @selected(request('sort') === 'latest')>Latest</option>
                        <option value="popular" @selected(request('sort') === 'popular')>Popular</option>
                        <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                        <option value="rating" @selected(request('sort') === 'rating')>Highest Rated</option>
                    </select>
                </form>
            </div>

            {{-- Mobile category chips --}}
            <div class="mb-4 flex gap-2 overflow-x-auto pb-1 lg:hidden">
                <a href="{{ route('products.index', collect(request()->except('category'))->toArray()) }}" class="chip flex shrink-0 items-center gap-1.5 {{ !request('category') ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}"><x-category-icon name="All Products" class="h-4 w-4" />All</a>
                @foreach($categories as $cat)
                    <a href="{{ route('products.index', array_merge(collect(request()->except('category'))->toArray(), ['category' => $cat->id])) }}" class="chip flex shrink-0 items-center gap-1.5 whitespace-nowrap {{ (string)request('category') === (string)$cat->id ? 'border-brand-400 bg-brand-50 text-brand-600' : 'border-slate-200 bg-white text-navy-700' }}"><span class="h-5 w-5"><x-category-visual :category="$cat" icon-class="h-3.5 w-3.5" /></span>{{ $cat->name }}</a>
                @endforeach
            </div>

            @if($products->isEmpty())
                <div class="card flex flex-col items-center justify-center p-14 text-center">
                    <svg class="h-16 w-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <h3 class="mt-4 text-lg font-semibold text-navy-800">{{ request('q') ? "No products found for '".request('q')."'." : 'No products found' }}</h3>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your filters or search terms.</p>
                    <a href="{{ route('products.index') }}" class="btn-primary btn-sm mt-4">Clear Filters</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $products->links('components.pagination') }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
