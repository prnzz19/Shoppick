@extends('layouts.seller')
@section('title','Inventory')
@section('content')
@include('seller.inventory.styles')
<div id="inventory-workspace" class="inventory-workspace">
    <header class="inventory-heading"><div><p class="inventory-eyebrow">SELLER CENTER / STOCK CONTROL</p><h1>Inventory</h1><p>Find products quickly. Manage product and variant quantities in one place.</p></div><a class="btn-outline" href="{{ route('seller.products.create') }}">Add product</a></header>
    <div id="inventory-summary" class="inventory-summary">
        @foreach(['total'=>'Total products','low'=>'Low stock','out'=>'Out of stock','units'=>'Total units'] as $key=>$label)<div><span>{{ $label }}</span><strong>{{ number_format($summary->$key) }}</strong></div>@endforeach
    </div>
    <form id="inventory-filters" action="{{ route('seller.inventory.index',[],false) }}" method="GET" class="inventory-filters">
        <label class="inventory-search">Search product or SKU<input class="input" name="q" type="search" maxlength="200" value="{{ $filters['q'] }}" placeholder="Search product name or SKU" autocomplete="off"></label>
        <label>Category<select class="input" name="category"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category'] ?? '')==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label>Stock status<select class="input" name="status"><option value="">All Stock Statuses</option>@foreach(['in'=>'In Stock','low'=>'Low Stock','out'=>'Out of Stock'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label>Product type<select class="input" name="type"><option value="">All Products</option><option value="simple" @selected(($filters['type'] ?? '')==='simple')>Simple Products</option><option value="variants" @selected(($filters['type'] ?? '')==='variants')>Products with Variants</option></select></label>
        <label>Sort<select class="input" name="sort">@foreach(['updated'=>'Recently Updated','name_asc'=>'Product Name A-Z','name_desc'=>'Product Name Z-A','stock_asc'=>'Lowest Stock','stock_desc'=>'Highest Stock'] as $key=>$label)<option value="{{ $key }}" @selected($filters['sort']===$key)>{{ $label }}</option>@endforeach</select></label>
        <noscript><button class="btn-primary">Apply filters</button></noscript>
    </form>
    <p id="inventory-feedback" role="status" aria-live="polite"></p>
    <div id="inventory-results">
        <div class="inventory-toolbar"><nav aria-label="Quick inventory filters" class="inventory-chips">
            @foreach(['All'=>['status'=>'','type'=>'','count'=>$summary->total],'Low Stock'=>['status'=>'low','count'=>$summary->low],'Out of Stock'=>['status'=>'out','count'=>$summary->out],'With Variants'=>['type'=>'variants','count'=>$summary->with_variants]] as $label=>$chip)
                @php $count=$chip['count']; unset($chip['count']); $active=collect($chip)->every(fn($value,$key)=>($filters[$key] ?? '')===$value); @endphp
                <a data-inventory-link href="{{ route('seller.inventory.index',array_merge($filters,$chip,['page'=>1]),false) }}" @if($active) aria-current="true" @endif>{{ $label }} <span>{{ $count }}</span></a>
            @endforeach
        </nav><span>{{ $products->total() }} matching {{ Str::plural('product',$products->total()) }}</span>
        @if($filters['q']!=='' || !empty($filters['category']) || !empty($filters['status']) || !empty($filters['type']) || $filters['sort']!=='updated')<a data-inventory-link href="{{ route('seller.inventory.index',[],false) }}" class="inventory-clear">Clear Filters</a>@endif</div>
        @if($products->isNotEmpty())
        <div class="inventory-list"><div class="inventory-columns" aria-hidden="true"><span>Product / SKU</span><span>Category</span><span>Stock / Alert</span><span>Status</span><span>Action</span></div>
        @foreach($products as $product)
            @php $status=$product->stock<=0?'out':($product->stock<=$product->low_stock_threshold?'low':'in'); $label=['out'=>'Out of Stock','low'=>'Low Stock','in'=>'In Stock'][$status]; $reopen=$errors->any() && (string)old('inventory_product_id')===(string)$product->id; @endphp
            <details class="inventory-item" data-product="{{ $product->id }}" @if($reopen) open @endif>
                <summary class="inventory-row">
                    <span class="inventory-product">@if($product->primaryImage)<img src="{{ $product->primaryImage->url }}" alt="" loading="lazy">@else<span class="inventory-placeholder" aria-hidden="true">&#9633;</span>@endif<span><strong>{{ $product->name }}</strong><small>{{ $product->sku ?: 'No SKU' }}</small><small>{{ $product->variants->count() ? $product->variants->count().' variants' : 'Simple product' }}</small></span></span>
                    <span class="inventory-category">{{ $product->category?->name ?: 'Uncategorized' }}</span>
                    <span class="inventory-stock"><strong>{{ number_format($product->stock) }} <small>units</small></strong><small>Alert at {{ $product->low_stock_threshold }}</small></span>
                    <span><span class="inventory-badge inventory-badge-{{ $status }}">{{ $label }}</span></span>
                    <span class="inventory-manage"><span class="manage-open">Manage</span><span class="manage-close">Close</span> <span aria-hidden="true">&#8964;</span></span>
                </summary>
                <form class="inventory-editor" method="POST" action="{{ route('seller.inventory.update',$product,false) }}" data-stock-form>
                    @csrf<input type="hidden" name="inventory_product_id" value="{{ $product->id }}">
                    <div class="inventory-editor-heading"><h2>Stock Management</h2><a href="{{ route('seller.products.edit',$product) }}">Edit product details &rarr;</a></div>
                    <div class="inventory-fields">
                        <label for="stock-{{ $product->id }}">Total Stock<input id="stock-{{ $product->id }}" class="input" type="number" step="1" min="0" max="2147483647" name="stock" value="{{ $reopen ? old('stock',$product->stock) : $product->stock }}" required><span class="inventory-error" data-error="stock">{{ $reopen ? $errors->first('stock') : '' }}</span></label>
                        <label for="threshold-{{ $product->id }}">Low-stock alert threshold<input id="threshold-{{ $product->id }}" class="input" type="number" step="1" min="0" max="2147483647" name="low_stock_threshold" value="{{ $reopen ? old('low_stock_threshold',$product->low_stock_threshold) : $product->low_stock_threshold }}" required><small>You'll be warned when stock reaches this quantity.</small><span class="inventory-error" data-error="low_stock_threshold">{{ $reopen ? $errors->first('low_stock_threshold') : '' }}</span></label>
                    </div>
                    @if($product->variants->isNotEmpty())<h3>Variant Stock</h3><p class="inventory-help">Total stock is a separate overall limit, not the sum of these options. A purchase is limited by both total stock and the selected variant.</p>
                        <div class="inventory-fields inventory-variants">@foreach($product->variants as $variant)<label for="variant-{{ $variant->id }}">{{ $variant->label }}<input id="variant-{{ $variant->id }}" class="input" type="number" min="0" step="1" max="2147483647" name="variants[{{ $variant->id }}]" value="{{ $reopen ? old('variants.'.$variant->id,$variant->stock) : $variant->stock }}" required>@if(fmod((float)$variant->stock,1)!==0.0)<small class="inventory-legacy">Legacy fractional quantity. Confirm a whole-unit count before saving.</small>@endif<span class="inventory-error" data-error="variants.{{ $variant->id }}">{{ $reopen ? $errors->first('variants.'.$variant->id) : '' }}</span></label>@endforeach</div>
                    @endif
                    <p class="inventory-error" data-error="variants">{{ $reopen ? $errors->first('variants') : '' }}</p><p class="inventory-error" data-form-error role="alert"></p>
                    <div class="inventory-editor-actions"><button class="btn-primary" type="submit">Update Stock</button><button class="btn-outline" type="button" data-close-editor>Cancel</button></div>
                </form>
            </details>
        @endforeach</div>
        <div class="inventory-pagination">{{ $products->links() }}</div>
        @else<div class="inventory-empty"><h2>{{ $summary->total ? 'No products match your filters.' : "You don't have any products yet." }}</h2>@if($summary->total)<a data-inventory-link class="btn-outline" href="{{ route('seller.inventory.index',[],false) }}">Clear Filters</a>@else<a class="btn-primary" href="{{ route('seller.products.create') }}">Add Product</a>@endif</div>@endif
    </div>
</div>
@include('seller.inventory.script')
@endsection
