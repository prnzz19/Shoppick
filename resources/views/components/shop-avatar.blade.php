@props(['shop', 'size' => 'h-12 w-12'])

@php
    $displayName = $shop->name ?: ($shop->store_name ?? 'Shop');
    $initial = str($displayName)->trim()->substr(0, 1)->upper();
@endphp

<div {{ $attributes->merge(['class' => "$size flex shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-lg font-black text-brand-800"]) }}>
    @if($shop->logo)
        <img src="{{ asset('storage/'.$shop->logo) }}" alt="{{ $displayName }} logo" class="h-full w-full object-cover">
    @else
        <span aria-hidden="true">{{ $initial }}</span>
        <span class="sr-only">Default SHOPPICK avatar for {{ $displayName }}</span>
    @endif
</div>