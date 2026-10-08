<section class="report-card"><h2 class="report-section-title">{{ $dashboard?'Sales Trend':'Sales Over Time' }}</h2><p class="report-note">{{ $monthly?'Monthly':'Daily' }} completed net sales · {{ $period }}</p>
@if($summary['orders']===0)<p class="analytics-empty">No completed sales were recorded for this period.</p>@elseif(!count($chart['points']))<p class="analytics-empty">No dated completed sales are available to plot.</p>@else
<svg class="analytics-chart" viewBox="0 0 720 205" role="img" aria-label="Completed sales over time"><title>Completed net sales, {{ $period }}</title>
@foreach([35,105,175] as $y)<line x1="35" x2="685" y1="{{ $y }}" y2="{{ $y }}" stroke="#e2e8f0" stroke-dasharray="3 5"/>@endforeach
<text x="35" y="22" font-size="11" fill="#64748b">₱{{ number_format($chart['max'],2) }}</text>
<polygon points="35,{{ $chart['zero'] }} {{ $chart['line'] }} 685,{{ $chart['zero'] }}" fill="#14b8a615"/>
<polyline points="{{ $chart['line'] }}" fill="none" stroke="#0d9488" stroke-width="2.5" stroke-linejoin="round"/>
@foreach($chart['points'] as $point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="#0d9488" tabindex="0"><title>{{ $point['period'] }} · ₱{{ number_format($point['sales'],2) }}</title></circle>@endforeach
@if(count($chart['points']))<text x="35" y="200" font-size="11" fill="#64748b">{{ $chart['points'][0]['period'] }}</text><text x="685" y="200" text-anchor="end" font-size="11" fill="#64748b">{{ end($chart['points'])['period'] }}</text>@endif
</svg>
<p class="report-note">Hover or focus a point to see its net sales.</p>
@endif
@if($chart['undated_sales']!=0)<p class="report-note">₱{{ number_format($chart['undated_sales'],2) }} has no recorded completion date and is included in All Time totals only.</p>@endif</section>
