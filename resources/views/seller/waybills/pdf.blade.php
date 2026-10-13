<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SHOPPICK Shipping Waybill</title>
    @include('seller.waybills.styles')
    <style>
        @page{size:6in 4in;margin:.03in}
        html,body{width:6in;height:4in;margin:0;padding:0}
    </style>
</head>
<body class="waybill-pdf">
    <div class="seller-waybills waybill-pdf">
        @include('seller.waybills.document')
    </div>
</body>
</html>
