<div class="report-metrics">
@foreach(['sales'=>'Total Sales','orders'=>isset($changes) ? 'Completed Orders' : 'Total Orders','units'=>'Units Sold','average'=>'Average Order Value'] as $key=>$label)
<section class="report-card report-metric" aria-label="{{ $label }}">
    <div class="report-metric-top"><span class="report-icon-tile">@include('admin.sales-reports.ui.icon',['icon'=>$key])</span><h2 class="report-metric-label">{{ $label }}</h2></div>
    <p class="report-value">{{ in_array($key,['sales','average']) ? '₱'.number_format($summary[$key],2) : number_format($summary[$key]) }}</p>
    @if(isset($changes) && $previous)
        @include('admin.sales-reports.ui.change',['change'=>$changes[$key]])<p class="report-note">vs previous period</p>
    @else
        <p class="report-note">{{ match($key) {'sales'=>$summary['sales'] == 0 ? 'No completed sales yet' : 'Completed seller-order net sales','orders'=>'Completed seller orders','units'=>'Items in completed orders',default=>'Net sales per completed order'} }}</p>
    @endif
</section>
@endforeach
</div>
