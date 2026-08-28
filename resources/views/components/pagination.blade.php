@if ($paginator->hasPages())
<nav role="navigation" aria-label="Pagination" class="flex items-center justify-between">
    <div class="hidden sm:block">
        <p class="text-sm text-slate-500">
            Showing
            @if ($paginator->firstItem())
                <span class="font-medium text-navy-800">{{ $paginator->firstItem() }}</span>-
                <span class="font-medium text-navy-800">{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            of <span class="font-medium text-navy-800">{{ $paginator->total() }}</span>
        </p>
    </div>

    <div class="flex items-center gap-1">
        {{-- Previous link --}}
        @if ($paginator->onFirstPage())
            <span class="rounded-xl border border-slate-200 p-2 text-slate-300"><</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-xl border border-slate-200 bg-white p-2 text-navy-700 hover:border-brand-300 hover:text-brand-600"><</a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-3 py-2 text-sm text-slate-400">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="rounded-xl bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm text-navy-700 hover:border-brand-300 hover:text-brand-600">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-xl border border-slate-200 bg-white p-2 text-navy-700 hover:border-brand-300 hover:text-brand-600">></a>
        @else
            <span class="rounded-xl border border-slate-200 p-2 text-slate-300">></span>
        @endif
    </div>
</nav>
@endif
