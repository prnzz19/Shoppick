@props(['category', 'iconClass' => 'h-7 w-7'])

@php
    $hasUploadedImage = filled($category->image)
        && Illuminate\Support\Facades\Storage::disk('public')->exists($category->image);
@endphp

@if($hasUploadedImage)
    <img src="{{ asset('storage/'.$category->image) }}" alt="{{ $category->name }}" {{ $attributes->merge(['class' => 'h-full w-full rounded-full object-cover']) }}>
@elseif(filled($category->icon))
    <span {{ $attributes->merge(['class' => 'flex h-full w-full items-center justify-center rounded-full bg-brand-50 text-brand-600']) }} aria-hidden="true">{{ $category->icon }}</span>
@else
    <span {{ $attributes->merge(['class' => 'flex h-full w-full items-center justify-center rounded-full bg-gradient-to-br from-brand-100 to-sun-100 text-brand-600']) }}>
        <x-category-icon :name="$category->name" :class="$iconClass" />
    </span>
@endif
