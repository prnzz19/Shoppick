<div class="report-pagination">
    <p class="report-note">@if($shops->total()) Showing {{ $shops->firstItem() }}–{{ $shops->lastItem() }} of {{ number_format($shops->total()) }} shops @else 0 matching shops @endif</p>
    @if($shops->hasPages())
    <nav aria-label="Shop sales pagination" class="report-page-links">
        @if($shops->onFirstPage())<span aria-disabled="true">Previous</span>@else<a rel="prev" href="{{ $shops->previousPageUrl() }}">Previous</a>@endif
        @php($pages = collect([1,...range(max(1,min($shops->lastPage(),$shops->currentPage())-2),min($shops->lastPage(),$shops->currentPage()+2)),$shops->lastPage()])->unique()->sort()->values())
        @foreach($pages as $page)
            @if($loop->index && $page-$pages[$loop->index-1]>1)<span aria-hidden="true">…</span>@endif
            @if($page===$shops->currentPage())<span class="current" aria-current="page">{{ $page }}</span>@else<a aria-label="Go to page {{ $page }}" href="{{ $shops->url($page) }}">{{ $page }}</a>@endif
        @endforeach
        @if($shops->hasMorePages())<a rel="next" href="{{ $shops->nextPageUrl() }}">Next</a>@else<span aria-disabled="true">Next</span>@endif
    </nav>
    @endif
</div>
