@csrf

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Basic Information</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Product Name</label>
                    <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" required class="input">
                </div>
                <div>
                    <label class="label">Category</label>
                    <select name="category_id" required class="input">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id ?? '') == $cat->id)>{{ $cat->name }}</option>
                            @foreach($cat->children as $child)
                                <option value="{{ $child->id }}" @selected(old('category_id', $product->category_id ?? '') == $child->id)>&nbsp;&nbsp;{{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Brand</label>
                    <input type="text" name="brand" value="{{ old('brand', $product->brand ?? '') }}" class="input">
                </div>
                <div>
                    <label class="label">SKU</label>
                    <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" class="input">
                </div>
                <div>
                    <label class="label">Stock</label>
                    <p class="py-2.5 text-sm font-semibold text-navy-800">{{ $product->stock ?? 0 }}</p>
                </div>
                <div>
                    <label class="label">Low Stock Threshold</label>
                    <p class="py-2.5 text-sm text-slate-500">{{ $product->low_stock_threshold ?? 5 }}</p>
                </div>
            </div>
            <div class="mt-4">
                <label class="label">Description</label>
                <textarea name="description" rows="4" class="input">{{ old('description', $product->description ?? '') }}</textarea>
            </div>
            <div class="mt-4 flex flex-wrap gap-5">
                <label class="flex items-center gap-2 text-sm text-navy-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="h-4 w-4 rounded border-slate-300 text-brand-500"> Active
                </label>
                <label class="flex items-center gap-2 text-sm text-navy-700">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false)) class="h-4 w-4 rounded border-slate-300 text-brand-500"> Featured
                </label>
            </div>
        </div>

        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Pricing</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label">Price (₱)</label>
                    <input type="number" name="price" value="{{ old('price', $product->price ?? '') }}" step="0.01" min="0" required class="input">
                </div>
                <div>
                    <label class="label">Original Price (₱)</label>
                    <input type="number" name="original_price" value="{{ old('original_price', $product->original_price ?? '') }}" step="0.01" min="0" class="input">
                </div>
                <div>
                    <label class="label">Discount (%)</label>
                    <input type="number" name="discount" value="{{ old('discount', $product->discount ?? 0) }}" step="0.01" min="0" max="100" class="input">
                </div>
            </div>
        </div>

        <div class="card p-5">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-wide text-navy-800">Seller Inventory</h3>
            </div>
            <p class="mb-4 text-sm text-slate-500">Stock, warning thresholds, and variants are managed by sellers. Admin access is view-only.</p>
            <div class="space-y-2">
                @forelse($product->variants ?? [] as $variant)
                    <div class="flex flex-wrap justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm">
                        <span class="font-medium text-navy-800">{{ $variant->label }}</span>
                        <span class="text-slate-500">SKU: {{ $variant->sku ?: '—' }} · Stock: {{ $variant->stock }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">No variants.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Product Images</h3>
            <input type="file" name="images[]" multiple accept="image/*" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-600">
            <p class="mt-2 text-xs text-slate-400">Upload one or more images (JPG, PNG, WebP).</p>
        </div>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="btn-primary">Save Product</button>
    <a href="{{ route('admin.products.index') }}" class="btn-ghost">Cancel</a>
</div>
