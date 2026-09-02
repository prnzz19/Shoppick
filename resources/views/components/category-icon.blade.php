@props(['name' => ''])
@php($category = strtolower($name))
<svg {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0']) }} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
    @if($category === 'all products')
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5a1 1 0 011-1h5v6H4V5zm10-1h5a1 1 0 011 1v5h-6V4zM4 14h6v6H5a1 1 0 01-1-1v-5zm10 0h6v5a1 1 0 01-1 1h-5v-6z"/>
    @elseif(str_contains($category, 'electronic'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v11H4V5zm5 15h6m-3-4v4"/>
    @elseif(str_contains($category, 'fashion'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 4a3 3 0 006 0l5 3-2 4-2-1v10H8V10l-2 1-2-4 5-3z"/>
    @elseif(str_contains($category, 'beauty'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3zm6 10l.7 2.3L21 16l-2.3.7L18 19l-.7-2.3L15 16l2.3-.7L18 13zM6 14l.8 2.2L9 17l-2.2.8L6 20l-.8-2.2L3 17l2.2-.8L6 14z"/>
    @elseif(str_contains($category, 'home') || str_contains($category, 'living'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11l9-7 9 7M5 10v10h14V10m-9 10v-6h4v6"/>
    @elseif(str_contains($category, 'food'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 3v7m-2-7v4a2 2 0 004 0V3m-2 7v11m9-18v18m0-18c3 2 4 5 4 8h-4"/>
    @elseif(str_contains($category, 'sport'))
        <circle cx="12" cy="12" r="8" stroke-width="1.8"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 5l1 4 3 2 3-2 1-4M4 13l4 1 1 4m11-5l-4 1-1 4"/>
    @elseif(str_contains($category, 'gaming') || str_contains($category, 'game'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 8h10a4 4 0 013.8 5.3l-1.2 3.6a2 2 0 01-3.2.9L14 16h-4l-2.4 1.8a2 2 0 01-3.2-.9l-1.2-3.6A4 4 0 017 8zm1 3v4m-2-2h4m6-1h.01M18 14h.01"/>
    @elseif(str_contains($category, 'school') || str_contains($category, 'suppl'))
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5.5A2.5 2.5 0 016.5 3H11v16H6.5A2.5 2.5 0 004 21V5.5zm16 0A2.5 2.5 0 0017.5 3H13v16h4.5A2.5 2.5 0 0120 21V5.5z"/>
    @else
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7zm4-2V3m8 2V3M4 10h16"/>
    @endif
</svg>
