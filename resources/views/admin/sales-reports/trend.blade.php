@if(!$filters['from'])<span>No comparable period</span>
@elseif($shop->previous_sales == 0)<span>{{ $shop->sales > 0 ? 'New Activity' : '—' }}</span>
@else<span>{{ $shop->sales > $shop->previous_sales ? '▲' : ($shop->sales < $shop->previous_sales ? '▼' : '—') }} {{ sprintf('%+.1f%%',($shop->sales-$shop->previous_sales)/$shop->previous_sales*100) }}</span>@endif
