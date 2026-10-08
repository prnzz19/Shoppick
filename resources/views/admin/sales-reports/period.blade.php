<div class="report-strip"><div><p class="report-strong">{{ $period }} <span class="report-note">· {{ number_format($summary['shops']) }} matching shops</span></p><p class="report-note">
@if($filters['from'])
 · Compared with {{ $filters['previousFrom']->format('M d') }} – {{ $filters['previousTo']->format('M d, Y') }}
@else
 · No comparable period
@endif
</p></div>
@if($filters['from'] && $shops->count())
<div class="report-strip-badges" aria-label="Performance of shops on this page"><span class="report-note">On this page</span>
@foreach($shops->getCollection()->groupBy('performance') as $state=>$group)
    @if($state!=='No comparable period')
        @include('admin.sales-reports.ui.badge',['label'=>$state])<span class="report-note">{{ $group->count() }}</span>
    @endif
@endforeach
</div>
@endif
</div>
