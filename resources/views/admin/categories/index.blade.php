@extends('layouts.admin')
@section('title', 'Categories')
@section('content')
@php($categoryRoutePrefix = 'admin')
<style>
    #category-workspace [hidden] { display:none !important; }
    .category-workspace { display:grid; gap:1.25rem; }
    .category-list-scroll { max-height:20rem; overflow-y:auto; }
    .category-choice[aria-pressed="true"] { border-left-color:#14b8a6; background:#effcf9; }
    .category-choice[aria-pressed="true"] .category-choice-name { color:#0f756d; }
    @media (min-width:1024px) {
        .category-workspace { grid-template-columns:minmax(0,38fr) minmax(0,62fr); height:max(420px,calc(100dvh - 180px)); max-height:900px; }
        .category-master { display:flex; flex-direction:column; min-height:0; }
        .category-list-scroll { flex:1; min-height:0; max-height:none; }
        .category-detail-scroll { height:100%; overflow-y:auto; }
    }
</style>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><p class="text-sm font-semibold text-brand-600">Catalog organization</p><h1 class="text-2xl font-bold text-navy-800">Categories</h1><p class="mt-1 text-sm text-slate-500">Select a category to review its subcategories and manage details.</p></div>
    <button type="button" onclick="openCategoryModal()" class="btn-primary">+ Add Category</button>
</div>
<div id="category-workspace" class="category-workspace">
    <section class="category-master card min-w-0 overflow-hidden" aria-labelledby="category-list-heading">
        <div class="border-b border-slate-100 p-4">
            <div class="mb-3 flex items-center justify-between gap-2"><h2 id="category-list-heading" class="font-bold text-navy-800">Category list</h2><span class="text-xs text-slate-400">{{ $categories->count() }} {{ Str::plural('category', $categories->count()) }}</span></div>
            <label for="category-search" class="sr-only">Search categories</label>
            <input id="category-search" type="search" placeholder="Search categories..." class="input" autocomplete="off" aria-controls="category-list">
        </div>
        <div id="category-list" class="category-list-scroll p-2">
            @foreach($categories as $cat)
                @php($productCount = $cat->products_count ?? $cat->products->count())
                <button type="button" data-category-id="{{ $cat->id }}" data-search="{{ $cat->name.' '.$cat->children->pluck('name')->implode(' ') }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="category-detail-{{ $cat->id }}" class="category-choice flex w-full items-center gap-3 rounded-xl border-l-4 border-transparent px-3 py-3 text-left transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400">
                    <span class="h-9 w-9 shrink-0" aria-hidden="true"><x-category-visual :category="$cat" /></span>
                    <span class="min-w-0 flex-1"><span class="category-choice-name block truncate text-sm font-semibold text-navy-800">{{ $cat->name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $productCount }} {{ Str::plural('product', $productCount) }} · {{ $cat->children->count() }} {{ Str::plural('subcategory', $cat->children->count()) }}</span></span>
                    <span class="badge shrink-0 {{ $cat->is_active ? 'bg-leaf-100 text-leaf-500' : 'bg-slate-100 text-slate-500' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span>
                </button>
            @endforeach
            <p id="category-no-results" class="px-3 py-8 text-center text-sm text-slate-500" role="status" hidden>No categories match your search.</p>
            @if($categories->isEmpty())<p class="px-3 py-8 text-center text-sm text-slate-500">No categories yet.</p>@endif
        </div>
    </section>
    <section class="card min-w-0 overflow-hidden" aria-label="Category details">
        <div class="category-detail-scroll">
            <p id="category-detail-empty" class="p-8 text-center text-sm text-slate-500" @if($categories->isNotEmpty()) hidden @endif>{{ $categories->isEmpty() ? 'Add a category to get started.' : 'Select a category from the list.' }}</p>
            @foreach($categories as $cat)
                @php($productCount = $cat->products_count ?? $cat->products->count())
                <article id="category-detail-{{ $cat->id }}" class="category-detail" aria-labelledby="category-title-{{ $cat->id }}" @if(!$loop->first) hidden @endif>
                    <div class="border-b border-slate-100 p-5">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider text-brand-600">Category details</p>
                        <div class="flex items-start gap-3">
                            <span class="h-12 w-12 shrink-0" aria-hidden="true"><x-category-visual :category="$cat" /></span>
                            <div class="min-w-0 flex-1"><h2 id="category-title-{{ $cat->id }}" class="break-words text-xl font-bold text-navy-800">{{ $cat->name }}</h2><span class="badge mt-2 {{ $cat->is_active ? 'bg-leaf-100 text-leaf-500' : 'bg-slate-100 text-slate-500' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span></div>
                        </div>
                        @if($cat->description)<p class="mt-4 break-words text-sm text-slate-500">{{ $cat->description }}</p>@endif
                        <div class="mt-4 flex flex-wrap gap-2"><span class="chip border-slate-200 text-navy-700">{{ $productCount }} {{ Str::plural('product', $productCount) }}</span><span class="chip border-slate-200 text-navy-700">{{ $cat->children->count() }} {{ Str::plural('subcategory', $cat->children->count()) }}</span></div>
                    </div>
                    <div class="p-5">
                        <h3 class="mb-3 text-sm font-bold text-navy-800">Subcategories</h3>
                        <div class="divide-y divide-slate-100">
                            @forelse($cat->children as $child)
                                @php($childCount = $child->products_count ?? $child->products->count())
                                <div class="flex flex-wrap items-center gap-3 py-3">
                                    <span class="min-w-0 flex-1 break-words text-sm font-medium text-navy-700">{{ $child->name }}</span>
                                    <span class="text-xs text-slate-500">{{ $childCount }} {{ Str::plural('product', $childCount) }}</span>
                                    <button type="button" data-category-edit="{{ json_encode($child->only(['id', 'name', 'description', 'sort_order', 'is_active'])) }}" class="btn-outline btn-sm" aria-label="Edit {{ $child->name }}">Edit</button>
                                </div>
                            @empty
                                <p class="rounded-xl bg-slate-50 px-4 py-6 text-sm text-slate-500">No subcategories yet.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/60 p-5">
                        <button type="button" data-category-edit="{{ json_encode($cat->only(['id', 'name', 'description', 'sort_order', 'is_active'])) }}" class="btn-outline btn-sm">Edit Category</button>
                        <form method="POST" action="{{ route($categoryRoutePrefix.'.categories.toggle', $cat->id) }}">@csrf<button type="submit" class="btn-outline btn-sm">{{ $cat->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        <form method="POST" action="{{ route($categoryRoutePrefix.'.categories.destroy', $cat->id) }}" data-confirm-title="Delete this category?" data-confirm-message="This action may permanently remove the selected category." data-confirm-action="Delete" data-confirm-type="danger">@csrf @method('DELETE')<button type="submit" class="btn-outline btn-sm !border-rose-200 !text-rose-600 hover:!bg-rose-50">Delete Category</button></form>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>

{{-- Modal --}}
<div id="category-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="cat-modal-title" class="text-lg font-bold text-navy-800">Add Category</h3>
            <button type="button" onclick="closeCategoryModal()" class="text-slate-400 hover:text-navy-800"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="category-form" method="POST" action="{{ route($categoryRoutePrefix.'.categories.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" id="cat-method" value="POST">
            <div class="space-y-3">
                <div>
                    <label class="label">Name</label>
                    <input type="text" name="name" id="cat-name" required class="input">
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea name="description" id="cat-description" rows="3" maxlength="1000" class="input" placeholder="Short category description (optional)"></textarea>
                </div>
                <div>
                    <label class="label">Image</label>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-600">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Sort Order</label>
                        <input type="number" name="sort_order" id="cat-sort" value="0" class="input">
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select name="is_active" id="cat-active" class="input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn-primary flex-1">Save</button>
                <button type="button" onclick="closeCategoryModal()" class="btn-ghost">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const categoryChoices = [...document.querySelectorAll('#category-list [data-category-id]')];
    const categoryDetails = [...document.querySelectorAll('.category-detail')];
    let selectedCategoryId = categoryChoices[0]?.dataset.categoryId ?? null;
    function selectCategory(id) {
        selectedCategoryId = id;
        categoryChoices.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.categoryId === id)));
        categoryDetails.forEach(panel => { panel.hidden = panel.id !== 'category-detail-' + id; });
        document.getElementById('category-detail-empty').hidden = id !== null;
        document.querySelector('.category-detail-scroll').scrollTop = 0;
    }
    categoryChoices.forEach(button => button.addEventListener('click', () => selectCategory(button.dataset.categoryId)));
    document.getElementById('category-search').addEventListener('input', event => {
        const search = event.target.value.trim().toLocaleLowerCase();
        categoryChoices.forEach(button => { button.hidden = !button.dataset.search.toLocaleLowerCase().includes(search); });
        const visible = categoryChoices.filter(button => !button.hidden);
        document.getElementById('category-no-results').hidden = visible.length > 0 || categoryChoices.length === 0;
        if (!visible.some(button => button.dataset.categoryId === selectedCategoryId)) {
            selectCategory(visible[0]?.dataset.categoryId ?? null);
        }
    });
    document.querySelectorAll('[data-category-edit]').forEach(button => {
        button.addEventListener('click', () => openCategoryModal(JSON.parse(button.dataset.categoryEdit)));
    });
    const categoryBaseUrl = @json(url('/'.$categoryRoutePrefix.'/categories'));
    function openCategoryModal(cat) {
        const modal = document.getElementById('category-modal');
        const form = document.getElementById('category-form');
        form.querySelector('input[type="file"]').value = '';
        document.getElementById('cat-modal-title').textContent = cat ? 'Edit Category' : 'Add Category';
        document.getElementById('cat-method').value = cat ? 'PUT' : 'POST';
        form.action = cat ? categoryBaseUrl + '/' + cat.id : categoryBaseUrl;
        document.getElementById('cat-name').value = cat ? cat.name : '';
        document.getElementById('cat-description').value = cat && cat.description ? cat.description : '';
        document.getElementById('cat-sort').value = cat ? (cat.sort_order || 0) : 0;
        document.getElementById('cat-active').value = cat ? (cat.is_active ? '1' : '0') : '1';
        modal.classList.remove('hidden'); modal.classList.add('flex');
    }
    function closeCategoryModal() {
        const modal = document.getElementById('category-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }
</script>
@endpush
