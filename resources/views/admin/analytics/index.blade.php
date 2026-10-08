@extends('layouts.admin')
@section('title','Sales & Marketplace Analytics')
@section('content')
@include('admin.analytics.styles')
<main class="sales-workspace" data-admin-analytics>
    <header class="report-header"><div><p class="report-eyebrow">Analytics</p><h1>Sales &amp; Marketplace Analytics</h1><p class="report-subtitle">Understand sales performance, shop contribution, product demand, and order trends.</p></div></header>
    @include('admin.analytics.filters')
    <p class="report-note" data-analytics-loading role="status" hidden>Updating analytics…</p>
    <div class="analytics-error" data-analytics-error role="alert" hidden>Analytics could not be updated. <button type="button" data-analytics-retry class="underline">Retry</button></div>
    <div data-analytics-content>@include('admin.analytics.content')</div>
</main>
@include('admin.analytics.script')
@endsection
