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
                    <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" min="0" required class="input">
                </div>
                <div>
                    <label class="label">Low Stock Threshold</label>
                    <input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" min="0" class="input">
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
                <h3 class="text-sm font-bold uppercase tracking-wide text-navy-800">Variants (optional)</h3>
                <button type="button" onclick="addVariantRow()" class="btn-outline btn-sm">+ Add Variant</button>
            </div>
            <div id="variants-list" class="space-y-2">
                @php
                    $variants = old('variants', $product->variants ?? []);
                @endphp
                @foreach($variants as $index => $v)
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5 variant-row">
                        <input type="text" name="variants[{{ $index }}][type]" value="{{ is_array($v) ? ($v['type'] ?? '') : $v->type }}" placeholder="Type (e.g. Color)" class="input !py-2 text-sm">
                        <input type="text" name="variants[{{ $index }}][value]" value="{{ is_array($v) ? ($v['value'] ?? '') : $v->value }}" placeholder="Value (e.g. Red)" class="input !py-2 text-sm">
                        <input type="text" name="variants[{{ $index }}][sku]" value="{{ is_array($v) ? ($v['sku'] ?? '') : $v->sku }}" placeholder="SKU" class="input !py-2 text-sm">
                        <input type="number" name="variants[{{ $index }}][price]" value="{{ is_array($v) ? ($v['price'] ?? '') : $v->price }}" placeholder="Price" class="input !py-2 text-sm" step="0.01">
                        <div class="flex gap-2">
                            <input type="number" name="variants[{{ $index }}][stock]" value="{{ is_array($v) ? ($v['stock'] ?? 0) : $v->stock }}" placeholder="Stock" class="input !py-2 text-sm">
                            <button type="button" onclick="this.closest('.variant-row').remove()" class="p-2 text-rose-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        </div>
                    </div>
                @endforeach
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

@push('scripts')
<script>
    let variantIdx = {{ count(old('variants', $product->variants ?? [])) }};
    function addVariantRow() {
        const list = document.getElementById('variants-list');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-2 gap-2 sm:grid-cols-5 variant-row';
        row.innerHTML = `
            <input type="text" name="variants[${variantIdx}][type]" placeholder="Type (e.g. Color)" class="input !py-2 text-sm">
            <input type="text" name="variants[${variantIdx}][value]" placeholder="Value (e.g. Red)" class="input !py-2 text-sm">
            <input type="text" name="variants[${variantIdx}][sku]" placeholder="SKU" class="input !py-2 text-sm">
            <input type="number" name="variants[${variantIdx}][price]" placeholder="Price" class="input !py-2 text-sm" step="0.01">
            <div class="flex gap-2">
                <input type="number" name="variants[${variantIdx}][stock]" placeholder="Stock" class="input !py-2 text-sm">
                <button type="button" onclick="this.closest('.variant-row').remove()" class="p-2 text-rose-500"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>`;
        list.appendChild(row);
        variantIdx++;
    }
</script>
@endpush
