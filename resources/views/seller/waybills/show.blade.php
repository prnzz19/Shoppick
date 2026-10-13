@extends('layouts.seller')
@section('title','Waybill '.$shipment->shipment_number)
@section('content')
@include('seller.waybills.styles')
<div class="seller-waybills">
    @if(session('success'))<p class="waybill-alert waybill-alert-success" role="status">{{ session('success') }}</p>@endif
    <nav class="waybill-actions-bar" aria-label="Waybill actions">
        <a class="waybill-action waybill-back" href="{{ route('seller.waybills.index') }}">Back to Waybills</a>
        <a class="waybill-action" href="{{ route('seller.waybills.print',$shipment) }}">Print Waybill</a>
        <a class="waybill-action waybill-action-primary" href="{{ route('seller.waybills.pdf',$shipment) }}">Download PDF</a>
    </nav>
    @include('seller.waybills.document')
</div>
@endsection
