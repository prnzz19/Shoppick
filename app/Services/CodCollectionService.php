<?php

namespace App\Services;

use App\Models\{Payment,Shipment,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CodCollectionService
{
    public function collect(Shipment $shipment,User $rider): Payment
    {
        abort_unless($rider->hasRole('rider')&&$shipment->rider_id===$rider->id,403);
        return DB::transaction(function()use($shipment,$rider){
            $shipment=Shipment::with('order')->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if($shipment->rider_id!==$rider->id)abort(403);
            if(!in_array($shipment->status,['out_for_delivery','delivery_attempted'],true))
                throw ValidationException::withMessages(['payment'=>'COD can only be collected at the delivery stage.']);
            $order=$shipment->order()->lockForUpdate()->firstOrFail();
            if($order->payment_method!=='cod')throw ValidationException::withMessages(['payment'=>'This Order is not Cash on Delivery.']);
            $payment=$order->payments()->where('method','cod')->lockForUpdate()->latest('id')->firstOrFail();
            if(in_array($payment->status,['cod_collected','paid'],true))return $payment;
            if(!in_array($payment->status,['pending','unpaid','cod'],true))throw ValidationException::withMessages(['payment'=>'This COD payment cannot be collected.']);
            $payment->update(['status'=>'cod_collected','collected_by'=>$rider->id,'collected_at'=>now(),'paid_at'=>null]);
            $order->update(['payment_status'=>'cod_collected','paid_at'=>null]);
            return $payment->fresh();
        });
    }

    public function settleForDelivery(Shipment $shipment): void
    {
        $order=$shipment->order;
        if($order->payment_method!=='cod')return;
        $payment=$order->payments()->where('method','cod')->lockForUpdate()->latest('id')->first();
        if(!$payment||!in_array($payment->status,['cod_collected','paid'],true))
            throw ValidationException::withMessages(['payment'=>'Confirm COD collection before marking this delivery as delivered.']);
        if($payment->status!=='paid')$payment->update(['status'=>'paid','paid_at'=>now()]);
        if($order->payment_status!=='paid')$order->update(['payment_status'=>'paid','paid_at'=>$payment->paid_at??now()]);
    }
}
