@extends('layouts.seller')
@section('title','Sales')
@section('content')
@include('seller.sales.styles')
<div class="seller-sales {{ $tab==='overview' ? 'sales-refined' : '' }}" data-sales-workspace>
<header class="sales-heading"><div class="sales-heading-copy"><p class="sales-eyebrow">Seller Performance</p><h1>Sales</h1>@if(!empty($store->name))<p class="sales-welcome">Welcome back, {{ $store->name }}</p>@endif<p>Track your shop sales, earnings, and order performance.</p></div>@include('seller.sales.toolbar')</header>
<nav class="sales-tabs" aria-label="Sales sections">@foreach(['overview'=>'Overview','transactions'=>'Transactions'] as $key=>$label)<a href="{{ route('seller.sales.index',($key==='transactions' ? $filters['query'] : array_diff_key($filters['query'],array_flip(['q','status'])))+['tab'=>$key],false) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>

@if($errors->any())<p role="alert" class="sales-error">{{ $errors->first() }}</p>@endif
@if($tab==='overview')
@include('seller.sales.overview')
@elseif($tab==='transactions')
<article class="sales-card"><header><div><h2>Transactions</h2><p>{{ $orders->total() }} orders &middot; {{ $period }}</p></div></header>@include('seller.sales.table')</article><div class="sales-pagination">{{ $orders->links() }}</div>
@endif
<p class="sales-footnote">Earnings use SHOPPICK's stored net and commission amounts. Pending and cancelled orders are not recognized earnings.</p>
<script type="application/json" data-sales-data>{!! json_encode($chart,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
</div>
@endsection
