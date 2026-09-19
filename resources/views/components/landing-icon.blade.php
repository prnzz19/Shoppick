@props(['name' => 'grid'])
@php
$paths = [
 'grid'=>'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
 'cart'=>'M3 3h2l3 12h11l3-9H6M9 20h.01M18 20h.01',
 'search'=>'M21 21l-5-5M18 10a8 8 0 11-16 0 8 8 0 0116 0',
 'user'=>'M20 21v-2a7 7 0 00-14 0v2M16 7a4 4 0 11-8 0 4 4 0 018 0',
 'electronics'=>'M3 4h18v13H3zM8 21h8M12 17v4',
 'fashion'=>'m8 3-6 4 3 5 3-2v11h8V10l3 2 3-5-6-4a4 4 0 01-8 0Z',
 'beauty'=>'M8 10h8v11H8zM10 10V4l4-2v8M8 15h8',
 'home'=>'m3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9',
 'food'=>'M4 3v6a3 3 0 006 0V3M7 3v18M20 21V3c-5 2-5 10 0 10',
 'sports'=>'M21 12a9 9 0 11-18 0 9 9 0 0118 0ZM3 12h18M12 3v18M5 5c9 5 9 9 14 14M19 5C10 10 10 14 5 19',
 'delivery'=>'M3 5h12v13H3zM15 10h4l3 4v4h-7M7 21a2 2 0 100-4 2 2 0 000 4ZM18 21a2 2 0 100-4 2 2 0 000 4Z',
 'orders'=>'M8 4H5v17h14V4h-3M9 3h6v4H9zM8 11h8M8 15h8',
];
@endphp
<svg {{ $attributes->class(['sp-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>
