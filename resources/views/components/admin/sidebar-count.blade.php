@props(['count' => 0])

@if((int) $count > 0)
    <span {{ $attributes->class('ml-auto inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-brand-500 px-1.5 text-[10px] font-bold leading-none text-white ring-1 ring-white/25') }}
          aria-label="{{ (int) $count }} items need attention">
        {{ (int) $count > 99 ? '99+' : (int) $count }}
    </span>
@endif
