<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Services\WaybillService;
use Illuminate\Http\Request;

class WaybillController extends Controller
{
    public function index(Request $request, WaybillService $waybills)
    {
        return view('seller.waybills.index',$waybills->index($request));
    }

    public function generate(Request $request, SellerOrder $sellerOrder, WaybillService $waybills)
    {
        $shipment = $waybills->generate($request->user(),$sellerOrder);

        return redirect()->route('seller.waybills.show',$shipment)
            ->with('success','Waybill generated. The Logistics shipment reference has been retained.');
    }

    public function show(Request $request, Shipment $shipment, WaybillService $waybills)
    {
        return view('seller.waybills.show',$waybills->document($request->user(),$shipment));
    }

    public function print(Request $request, Shipment $shipment, WaybillService $waybills)
    {
        return view('seller.waybills.print',$waybills->document($request->user(),$shipment));
    }

    public function pdf(Request $request, Shipment $shipment, WaybillService $waybills)
    {
        return $waybills->pdf($waybills->document($request->user(),$shipment));
    }
}
