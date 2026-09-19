@props(['name'])
@php
    $paths = [
        'home' => 'M3 10l9-7 9 7M5 9v12h5v-7h4v7h5V9',
        'orders' => 'M8 4H5v17h14V4h-3M9 3h6v4H9V3ZM8 11h8M8 15h8',
        'box' => 'M20 7l-8-4-8 4v10l8 4 8-4V7ZM4 7l8 5 8-5M12 12v9M8 5l8 5',
        'ticket' => 'M3 6h18v4a2 2 0 000 4v4H3v-4a2 2 0 000-4V6ZM12 8v2m0 4v2',
        'star' => 'm12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6Z',
        'chart' => 'M4 20V4M4 20h17M8 16l4-5 4 2 5-7',
        'store' => 'M4 3h16l2 6a3 3 0 01-5 2 3 3 0 01-5 0 3 3 0 01-5 0 3 3 0 01-5-2l2-6ZM4 12v9h16v-9M9 21v-7h6v7M8 3 7 9m9-6 1 6',
        'bell' => 'M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
        'settings' => 'm10 3-1 3-3 1-3 3 2 2-1 4 3 1 2 4h5l2-3 4-1 1-4-2-3-1-4-4-1-1-2h-3ZM15 12a3 3 0 11-6 0 3 3 0 016 0',
        'plus' => 'M12 5v14M5 12h14',
        'left' => 'm14 6-6 6 6 6',
        'right' => 'M4 12h16m-6-6 6 6-6 6',
        'logout' => 'M9 4H4v16h5M8 12h13m-5-5 5 5-5 5',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'close' => 'm6 6 12 12M6 18 18 6',
        'chat' => 'M21 11a9 9 0 01-9 9H4l-2 2 1-7A9 9 0 1121 11ZM8 11h.01M12 11h.01M16 11h.01',
    ];
@endphp
<svg {{ $attributes->class(['seller-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['box'] }}"/></svg>
