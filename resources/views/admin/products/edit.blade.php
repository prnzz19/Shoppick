@extends('layouts.admin')

@section('title', 'Edit Product')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-navy-800">Edit Product</h1>
    <p class="text-sm text-slate-500">{{ $product->name }}</p>
</div>
<form method="POST" action="{{ route('admin.products.update', $product->id) }}" enctype="multipart/form-data">
    @method('PUT')
    @include('admin.products._form', ['product' => $product])
</form>

@if($product->images->isNotEmpty())
<div class="card mt-8 p-5">
    <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-navy-800">Image Gallery</h3>
    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
        @foreach($product->images as $img)
            <div class="relative">
                <img src="{{ asset('storage/'.$img->path) }}" class="aspect-square w-full rounded-xl object-cover" alt="">
                @if($img->is_primary)<span class="absolute left-2 top-2 badge bg-brand-500 text-white">Main</span>@endif
                <div class="mt-1 flex gap-1">
                    @if(!$img->is_primary)
                        <form method="POST" action="{{ route('admin.products.images.primary', [$product->id, $img->id]) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="btn-sm w-full bg-brand-50 text-brand-600 hover:bg-brand-100">Set main</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.products.images.destroy', [$product->id, $img->id]) }}" data-confirm-title="Delete this image?" data-confirm-message="This image will be permanently removed from the product." data-confirm-action="Delete" data-confirm-type="danger">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-sm bg-rose-50 text-rose-600 hover:bg-rose-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.products.images.store', $product->id) }}" enctype="multipart/form-data" class="mt-4 border-t border-slate-100 pt-4">
        @csrf
        <label class="label">Add more images</label>
        <div class="flex gap-2">
            <input type="file" name="images[]" multiple accept="image/*" class="input file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand-600">
            <button type="submit" class="btn-primary btn-sm">Upload</button>
        </div>
    </form>
</div>
@endif
@endsection
