<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Waybill {{ $shipment->shipment_number }} · SHOPPICK</title>
    @include('seller.waybills.styles')
</head>
<body style="background:#f6f8fa;padding:24px">
    <div class="seller-waybills">
        <nav class="waybill-actions-bar"><a class="waybill-action waybill-back" href="{{ route('seller.waybills.show',$shipment) }}">Back to Waybill</a><button class="waybill-action waybill-action-primary" type="button" onclick="window.print()">Print Waybill</button><a class="waybill-action" href="{{ route('seller.waybills.pdf',$shipment) }}">Download PDF</a></nav>
        @include('seller.waybills.document')
    </div>
</body>
</html>
