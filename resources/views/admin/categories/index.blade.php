@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-navy-800">Categories</h1>
    <button type="button" onclick="openCategoryModal()" class="btn-primary">+ Add Category</button>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    @foreach($categories as $cat)
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if($cat->image)<img src="{{ asset('storage/'.$cat->image) }}" class="h-10 w-10 rounded-xl object-cover">@endif
                    <div>
                        <p class="font-semibold text-navy-800">{{ $cat->name }} <span class="text-xs font-normal text-slate-400">({{ $cat->products_count ?? $cat->products->count() }} products)</span></p>
                        <div class="flex gap-2 mt-1">
                            <span class="badge {{ $cat->is_active ? 'bg-leaf-100 text-leaf-500' : 'bg-slate-100 text-slate-500' }}">{{ $cat->is_active ? 'Active' : 'Inactive' }}</span>
                            <span class="badge bg-slate-100 text-slate-500">{{ $cat->children->count() }} subcategories</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-1">
                    <button type="button" onclick="openCategoryModal({{ json_encode(array_merge($cat->toArray(), ['name' => $cat->name])) }})" class="p-2 text-slate-400 hover:text-brand-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                    <form method="POST" action="{{ route('admin.categories.toggle', $cat->id) }}"><button type="submit" class="p-2 text-slate-400 hover:text-navy-700"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></button></form>
                    <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}" onsubmit="return confirm('Delete this category?')">@csrf @method('DELETE')<button type="submit" class="p-2 text-slate-400 hover:text-rose-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form>
                </div>
            </div>

            @if($cat->children->isNotEmpty())
                <div class="mt-4 space-y-2 border-t border-slate-100 pt-3">
                    @foreach($cat->children as $child)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                            <span class="text-sm text-navy-700">{{ $child->name }} <span class="text-xs text-slate-400">({{ $child->products->count() }})</span></span>
                            <div class="flex gap-1">
                                <button type="button" onclick="openCategoryModal({{ json_encode(array_merge($child->toArray(), ['name' => $child->name])) }})" class="p-1.5 text-slate-400 hover:text-brand-600"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>

{{-- Modal --}}
<div id="category-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="cat-modal-title" class="text-lg font-bold text-navy-800">Add Category</h3>
            <button type="button" onclick="closeCategoryModal()" class="text-slate-400 hover:text-navy-800"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
        </div>
        <form id="category-form" method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data">
            <input type="hidden" name="_method" id="cat-method" value="POST">
            <div class="space-y-3">
                <div>
                    <label class="label">Name</label>
                    <input type="text" name="name" id="cat-name" required class="input">
                </div>
                <div>
                    <label class="label">Parent Category</label>
                    <select name="parent_id" id="cat-parent" class="input">
                        <option value="">None (main category)</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" data-name="{{ $cat->name }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Icon (emoji or short text)</label>
                    <input type="text" name="icon" id="cat-icon" class="input" placeholder="e.g. 📱">
                </div>
                <div>
                    <label class="label">Image</label>
                    <input type="file" name="image" accept="image/*" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-600">
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
    function openCategoryModal(cat) {
        const modal = document.getElementById('category-modal');
        const form = document.getElementById('category-form');
        document.getElementById('cat-modal-title').textContent = cat ? 'Edit Category' : 'Add Category';
        document.getElementById('cat-method').value = cat ? 'PUT' : 'POST';
        form.action = cat ? '/admin/categories/' + cat.id : '/admin/categories';
        document.getElementById('cat-name').value = cat ? cat.name : '';
        document.getElementById('cat-parent').value = cat && cat.parent_id ? cat.parent_id : '';
        document.getElementById('cat-icon').value = cat && cat.icon ? cat.icon : '';
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
