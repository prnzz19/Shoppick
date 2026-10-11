<div x-show="scope === 'selected_products'" x-cloak class="md:col-span-2">
    <label class="label">Applicable Products <span class="text-rose-500">*</span></label>
    <div class="space-y-3">
        <p class="text-sm text-slate-600" x-text="`${selectedProducts.length} products selected`"></p>
        <div class="flex flex-wrap gap-2">
            <template x-for="product in visibleSelectedProducts" :key="product.id">
                <span class="inline-flex max-w-full items-center gap-1 rounded-full border border-brand-100 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800">
                    <span class="max-w-48 truncate" x-text="product.name"></span>
                    <button type="button" class="rounded-full px-1 text-brand-700 hover:bg-brand-100" :aria-label="`Remove ${product.name}`" @click="removeProduct(product.id)">×</button>
                </span>
            </template>
            <span x-show="hiddenSelectedCount > 0" class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600" x-text="`+${hiddenSelectedCount} more`"></span>
        </div>
        <template x-for="product in selectedProducts" :key="`product-input-${product.id}`">
            <input type="hidden" name="product_ids[]" :value="product.id">
        </template>
        <button type="button" class="btn-outline btn-sm" @click="openPicker()">Manage Products</button>
    </div>

    <div x-cloak x-show="modalOpen" x-transition.opacity class="fixed inset-0 z-[70] flex items-center justify-center bg-navy-900/50 p-4" role="presentation" @click.self="closePicker()" @keydown.escape.window="closePicker()">
        <section x-show="modalOpen" x-transition class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" :aria-labelledby="`product-picker-title-${pickerId}`">
            <header class="flex items-start justify-between border-b border-slate-100 p-5">
                <div>
                    <h2 :id="`product-picker-title-${pickerId}`" class="text-lg font-bold text-navy-900">Select Products</h2>
                    <p class="mt-1 text-sm text-slate-500"><span x-text="draftProducts.length"></span> selected</p>
                </div>
                <button type="button" class="rounded-lg p-2 text-xl leading-none text-slate-500 hover:bg-slate-100" aria-label="Close product picker" @click="closePicker()">×</button>
            </header>

            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-[minmax(0,1fr)_220px]">
                <input x-ref="productSearch" x-model="search" x-on:input.debounce.250ms="loadProducts(1)" type="search" class="input" placeholder="Search product or SKU" aria-label="Search product or SKU">
                <select x-model="category" x-on:change="loadProducts(1)" class="input" aria-label="Filter products by category">
                    <option value="">All Categories</option>
                    <template x-for="item in categories" :key="item.id">
                        <option :value="String(item.id)" x-text="item.name"></option>
                    </template>
                </select>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-4">
                <p x-show="loading" class="py-8 text-center text-sm text-slate-500" role="status">Loading products…</p>
                <p x-show="loadError" x-text="loadError" class="py-5 text-center text-sm text-rose-600" role="alert"></p>
                <p x-show="!loading && !loadError && products.length === 0" class="py-8 text-center text-sm text-slate-500">No products found.</p>
                <div x-show="!loading && !loadError" class="space-y-2">
                    <template x-for="product in products" :key="product.id">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm hover:border-brand-300 hover:bg-brand-50/40">
                            <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" :checked="isSelected(product.id)" @change="toggleProduct(product, $event.target.checked)">
                            <img x-show="product.image" :src="product.image" :alt="''" class="h-11 w-11 rounded-lg object-cover">
                            <span x-show="!product.image" class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-400" aria-hidden="true">IMG</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-navy-900" x-text="product.name"></span>
                                <span class="block truncate text-xs text-slate-500" x-text="`SKU: ${product.sku || '—'}${product.category ? ` · ${product.category}` : ''}`"></span>
                            </span>
                        </label>
                    </template>
                </div>
            </div>

            <footer class="flex flex-col gap-3 border-t border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center justify-between gap-3 text-xs text-slate-500">
                    <span x-text="total ? `Showing ${from}–${to} of ${total}` : 'Showing 0 products'"></span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-outline btn-sm" :disabled="currentPage <= 1 || loading" @click="loadProducts(currentPage - 1)">Previous</button>
                        <span x-text="`${currentPage} / ${lastPage}`"></span>
                        <button type="button" class="btn-outline btn-sm" :disabled="currentPage >= lastPage || loading" @click="loadProducts(currentPage + 1)">Next</button>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="closePicker()">Cancel</button>
                    <button type="button" class="btn-primary" @click="applySelection()">Add Selected</button>
                </div>
            </footer>
        </section>
    </div>
</div>
