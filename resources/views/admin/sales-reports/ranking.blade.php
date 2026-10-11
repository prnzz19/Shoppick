@if(count($rows))
<div class="report-chart-frame"><canvas data-report-chart="{{ $kind }}" role="img" aria-label="Top five {{ $kind }}. Exact values follow below."></canvas></div>
<ol class="report-ranking-values" @if($kind==='products') data-product-values @endif>@foreach($rows as $row)<li><span>{{ $row['label'] }}</span><strong>&#8369;{{ number_format($row['value'],2) }}</strong></li>@endforeach</ol>
@else<div class="report-chart-empty compact"><strong>{{ $empty }}</strong><span>Try a different reporting period.</span></div>@endif
