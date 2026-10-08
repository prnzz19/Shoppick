@if(!$filters['from'])
<span class="report-note" title="No comparable period">—</span><p class="report-note">No comparison</p>
@elseif($shop->previous_sales == 0)
    @if($shop->sales > 0)<span class="report-trend up">New Activity</span><p class="report-note">No prior sales</p>@else<span class="report-note">—</span>@endif
@else
<span class="report-trend {{ $shop->sales > $shop->previous_sales ? 'up' : ($shop->sales < $shop->previous_sales ? 'down' : '') }}">{{ $shop->sales > $shop->previous_sales ? '▲' : ($shop->sales < $shop->previous_sales ? '▼' : '—') }} {{ sprintf('%+.1f%%',($shop->sales-$shop->previous_sales)/$shop->previous_sales*100) }}</span><p class="report-note">vs previous period</p>
@endif
