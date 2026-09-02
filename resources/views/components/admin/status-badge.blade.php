@props(['status'])

@php
    $map = [
        'pending' => ['bg-sun-100', 'text-sun-500'],
        'confirmed' => ['bg-brand-100', 'text-brand-700'],
        'processing' => ['bg-brand-100', 'text-brand-700'],
        'packed' => ['bg-brand-100', 'text-brand-700'],
        'ready_to_ship' => ['bg-sun-100', 'text-sun-500'],
        'shipped' => ['bg-brand-100', 'text-brand-700'],
        'delivered' => ['bg-brand-100', 'text-brand-700'],
        'completed' => ['bg-leaf-100', 'text-leaf-500'],
        'cancelled' => ['bg-rose-100', 'text-rose-600'],
        'refunded' => ['bg-rose-100', 'text-rose-600'],
        'active' => ['bg-leaf-100', 'text-leaf-500'],
        'inactive' => ['bg-slate-100', 'text-slate-500'],
        'expired' => ['bg-slate-100', 'text-slate-500'],
        'paid' => ['bg-leaf-100', 'text-leaf-500'],
        'unpaid' => ['bg-sun-100', 'text-sun-500'],
        'cod' => ['bg-brand-100', 'text-brand-700'],
    ];
    $classes = $map[$status] ?? ['bg-slate-100', 'text-slate-600'];
@endphp
<span class="badge {{ $classes[0] }} {{ $classes[1] }}">{{ ucwords(str_replace('_', ' ', $status)) }}</span>
