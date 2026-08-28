@props(['title', 'subtitle' => null, 'link' => null, 'linkText' => 'See all'])

<div class="mb-4 flex items-end justify-between">
    <div>
        <h2 class="text-xl font-bold text-navy-800 sm:text-2xl">{{ $title }}</h2>
        @if($subtitle)<p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @if($link)
        <a href="{{ $link }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700">
            {{ $linkText }}
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>
    @endif
</div>
