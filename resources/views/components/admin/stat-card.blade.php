@props(['label', 'value', 'icon', 'color' => 'bg-brand-50 text-brand-600'])

<div class="card p-5">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-extrabold text-navy-800">{{ $value }}</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $color }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
        </span>
    </div>
</div>
