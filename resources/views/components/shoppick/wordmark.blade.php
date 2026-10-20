@props(['class' => 'h-12 w-auto', 'label' => 'SHOPPICK'])

<span {{ $attributes->merge([
    'class' => $class . ' sp-brand-lockup',
    'role' => 'img',
    'aria-label' => $label,
]) }}>
    <img class="sp-brand-lockup__mark" src="{{ asset('images/brand/shoppick-mark.png') }}" alt="" aria-hidden="true" width="640" height="610" decoding="async">
    <img class="sp-brand-lockup__wordmark" src="{{ asset('images/brand/shoppick-wordmark-hoppick.png') }}" alt="" aria-hidden="true" width="944" height="245" decoding="async">
</span>
