@if(count($rows) || $kind === 'categories')
<div class="overview-chart"><canvas data-report-chart="{{ $kind }}" role="img" aria-label="Top five {{ $kind }} by allocated net sales. Exact values are available below."></canvas>@if(!count($rows))@include('admin.analytics.partials.sales-empty',['message'=>'No completed sales for this period.'])@endif</div>
@if(count($rows))<details class="report-chart-data"><summary>View {{ $kind === 'categories' ? 'category' : 'product' }} values</summary><ol class="report-ranking-values">@foreach($rows as $row)<li><span>{{ $row['label'] }}</span><strong>&#8369;{{ number_format($row['value'],2) }}</strong></li>@endforeach</ol></details>@endif
@else<div class="overview-empty">{{ $empty }}</div>@endif
