<form action="{{ route($dashboard?'admin.dashboard':'admin.analytics.index') }}" method="get" class="report-card report-filters" data-analytics-form>
    <div class="report-filter-grid">
        <label>Date Range<select class="input" name="range">@foreach(\App\Services\ShopSalesReportService::RANGES as $value=>$label)@if(!$dashboard||in_array($value,['today','week','7days','30days','month']))<option value="{{ $value }}" @selected($filters['range']===$value)>{{ $label }}</option>@endif @endforeach</select></label>
        @if(!$dashboard)
        <label>Shop<select class="input" name="shop"><option value="">All Shops</option>@foreach($shopOptions as $shop)<option value="{{ $shop->id }}" @selected(($filters['query']['shop']??'')==$shop->id)>{{ $shop->name }}</option>@endforeach</select></label>
        <label>Category<select class="input" name="category"><option value="">All Categories</option>@foreach($categoryOptions as $category)<option value="{{ $category->id }}" @selected(($filters['query']['category']??'')==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label>Order Status<select class="input" name="status"><option value="">All Statuses</option>@foreach(\App\Models\SellerOrder::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['query']['status']??'')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
        <div class="report-custom" data-analytics-custom @if($filters['range']!=='custom') hidden @endif><label>From<input type="date" class="input" name="from" value="{{ $filters['query']['from']??'' }}" @disabled($filters['range']!=='custom')></label><label>To<input type="date" class="input" name="to" value="{{ $filters['query']['to']??'' }}" @disabled($filters['range']!=='custom')></label></div>
        <input type="hidden" name="sort" value="{{ $filters['query']['sort']??'sales_desc' }}">
        @endif
    </div>
    <noscript><button class="btn-primary mt-3" type="submit">Update Analytics</button></noscript>
</form>
