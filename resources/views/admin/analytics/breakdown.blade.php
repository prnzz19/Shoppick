<div class="analytics-scroll"><table>
<thead><tr><th>{{ $kind==='product'?'Product':'Category' }}</th><th>Sales</th><th>Orders</th><th>Units</th>@if(!$dashboard)<th>Share</th>@endif<th>Trend</th></tr></thead>
<tbody>@forelse($rows as $row)
<tr><td>
    @if($kind==='product')
        @if($row->product_image)<img class="analytics-thumb" src="{{ asset('storage/'.$row->product_image) }}" alt="" loading="lazy">
        @else<span class="analytics-thumb analytics-thumb-placeholder" aria-hidden="true">{{ mb_substr($row->name,0,1) }}</span>@endif
    @endif
    <strong>{{ $row->name }}</strong>@if($kind==='product')<p class="report-note">{{ $row->shop_name }}</p>@endif
</td><td class="analytics-money">₱{{ number_format($row->sales,2) }}</td><td>{{ $row->orders }}</td><td>{{ $row->units }}</td>
@if(!$dashboard)<td>{{ number_format($row->share_percent,1) }}%</td>@endif
<td>@include('admin.analytics.trend')</td></tr>
@empty<tr><td colspan="6" class="analytics-empty">No completed item sales in this period.</td></tr>@endforelse</tbody></table></div>