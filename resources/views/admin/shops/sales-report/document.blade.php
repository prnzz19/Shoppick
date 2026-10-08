<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><title>{{ $shop->name }} · SHOPPICK Sales Report</title>@include('admin.shops.sales-report.styles')</head>
<body><main class="shop-sales-report-content">@include('admin.shops.sales-report.content')</main>
@if(!$pdf)<script>window.addEventListener('load', () => window.print());</script>@endif
</body></html>
