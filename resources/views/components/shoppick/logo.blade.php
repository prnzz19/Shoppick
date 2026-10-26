@props(['class' => 'h-9 w-9'])

<img {{ $attributes->merge(['class' => $class . ' shrink-0 object-contain']) }} src="{{ asset('images/brand/shoppick-mark.png') }}" alt="SHOPPICK" width="64" height="64" decoding="async">
