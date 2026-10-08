@if($from)
    <p class="text-xs text-slate-500">Sales completed {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }} (inclusive).</p>
@endif
