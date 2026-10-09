@if($paginator->hasPages())
<nav aria-label="Product pagination" class="flex flex-wrap items-center justify-end gap-2 text-sm">
@if($paginator->onFirstPage())<span class="rounded-lg border px-3 py-2 text-slate-400" aria-disabled="true">Previous</span>
@else<a class="rounded-lg border bg-white px-3 py-2 text-brand-700" rel="prev" href="{{ $paginator->previousPageUrl() }}">Previous</a>@endif
@foreach($elements as $element)
@if(is_string($element))<span class="px-2">{{ $element }}</span>
@else
@foreach($element as $page=>$url)
@if($page === $paginator->currentPage())<span aria-current="page" class="rounded-lg bg-brand-600 px-3 py-2 font-semibold text-white">{{ $page }}</span>
@else<a aria-label="Go to page {{ $page }}" class="rounded-lg border bg-white px-3 py-2 text-brand-700" href="{{ $url }}">{{ $page }}</a>@endif
@endforeach
@endif
@endforeach
@if($paginator->hasMorePages())<a class="rounded-lg border bg-white px-3 py-2 text-brand-700" rel="next" href="{{ $paginator->nextPageUrl() }}">Next</a>
@else<span class="rounded-lg border px-3 py-2 text-slate-400" aria-disabled="true">Next</span>@endif
</nav>
@endif