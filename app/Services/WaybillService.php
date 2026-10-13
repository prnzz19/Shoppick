<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class WaybillService
{
    public function __construct(private SalesReportExportService $exports) {}

    public function index(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable','string','max:120'],
            'status' => ['nullable',Rule::in(Shipment::STATUSES)],
            'waybill_status' => ['nullable',Rule::in(['generated','ready'])],
            'date_from' => ['nullable','date_format:Y-m-d'],
            'date_to' => ['nullable','date_format:Y-m-d','after_or_equal:date_from'],
        ]);
        $store = $this->storeFor($request->user());
        $base = $this->sellerShipments($store);

        $stats = [
            'generated' => (clone $base)->whereNotNull('waybill_generated_at')->count(),
            'ready' => (clone $base)->whereNull('waybill_generated_at')
                ->whereHas('sellerOrder',fn ($query) => $query->where('status','ready_to_ship'))->count(),
            'pickup' => (clone $base)->where('status','ready_for_pickup')->count(),
            'transit' => (clone $base)->whereIn('status',['in_transit','out_for_delivery'])->count(),
        ];

        $query = (clone $base)->with([
            'sellerOrder.order:id,order_number,buyer_name,buyer_phone,shipping_address,payment_method,payment_status',
            'sellerOrder.store:id,name,location',
            'sellerOrder.items:id,seller_order_id,quantity',
            'rider:id,name',
        ]);
        if (filled($filters['q'] ?? null)) {
            $term = '%'.trim($filters['q']).'%';
            $query->where(fn ($shipment) => $shipment
                ->where('shipment_number','like',$term)
                ->orWhere('parcel_code','like',$term)
                ->orWhereHas('sellerOrder',fn ($sellerOrder) => $sellerOrder
                    ->where('seller_order_number','like',$term)
                    ->orWhereHas('order',fn ($order) => $order
                        ->where('order_number','like',$term)
                        ->orWhere('buyer_name','like',$term))));
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status',$filters['status']);
        }
        if (($filters['waybill_status'] ?? null) === 'generated') {
            $query->whereNotNull('waybill_generated_at');
        } elseif (($filters['waybill_status'] ?? null) === 'ready') {
            $query->whereNull('waybill_generated_at')
                ->whereHas('sellerOrder',fn ($sellerOrder) => $sellerOrder->where('status','ready_to_ship'));
        }
        if (filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)) {
            $query->whereHas('sellerOrder',fn ($sellerOrder) => $sellerOrder
                ->when(filled($filters['date_from'] ?? null),fn ($q) => $q->whereDate('created_at','>=',$filters['date_from']))
                ->when(filled($filters['date_to'] ?? null),fn ($q) => $q->whereDate('created_at','<=',$filters['date_to'])));
        }

        $shipments = $query->latest('shipments.created_at')->latest('shipments.id')->paginate(15)->withQueryString();

        return compact('store','shipments','stats','filters');
    }

    public function generate(User $actor, SellerOrder $sellerOrder): Shipment
    {
        $store = $this->storeFor($actor);
        abort_unless((int)$sellerOrder->store_id === (int)$store->id,404);

        return DB::transaction(function () use ($store,$sellerOrder) {
            $lockedOrder = SellerOrder::whereKey($sellerOrder->id)
                ->where('store_id',$store->id)
                ->lockForUpdate()
                ->firstOrFail();
            $shipment = Shipment::where('seller_order_id',$lockedOrder->id)->lockForUpdate()->first();

            if (!$shipment) {
                throw ValidationException::withMessages(['waybill'=>'No Logistics shipment exists for this order yet.']);
            }
            if ($shipment->waybill_generated_at) {
                return $shipment;
            }
            if ($lockedOrder->status !== 'ready_to_ship') {
                throw ValidationException::withMessages(['waybill'=>'This order is not ready for a waybill yet.']);
            }

            $shipment->update(['waybill_generated_at'=>now()]);

            return $shipment->fresh();
        });
    }

    public function document(User $actor, Shipment $shipment): array
    {
        $store = $this->storeFor($actor);
        $shipment = $this->sellerShipments($store)
            ->with([
                'sellerOrder.order.sellerOrders:id,order_id',
                'sellerOrder.order.items:id,order_id,seller_order_id',
                'sellerOrder.store',
                'sellerOrder.items',
                'rider:id,name',
            ])
            ->whereKey($shipment->id)
            ->firstOrFail();
        abort_unless($shipment->waybill_generated_at,404);

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder->order;
        $delivery = $shipment->delivery_address ?: $order->shipping_address ?: [];
        $addressLines = array_values(array_filter([
            data_get($delivery,'address_line') ?? data_get($delivery,'complete_address') ?? data_get($delivery,'address'),
            data_get($delivery,'barangay'),
            data_get($delivery,'city'),
            data_get($delivery,'province'),
            data_get($delivery,'postal_code'),
            data_get($delivery,'country'),
        ],fn ($line) => filled($line)));
        $pickup = data_get($shipment->pickup_address,'address') ?: $sellerOrder->store?->location;
        $isCod = $order->payment_method === 'cod';
        $barcodeData = (new TypeCode128())->getBarcode($shipment->shipment_number);
        $barcodeRenderer = (new SvgRenderer())
            ->setSvgType(SvgRenderer::TYPE_SVG_INLINE)
            ->setForegroundColor([17,24,39]);
        $barcodeSvg = $barcodeRenderer->render($barcodeData,$barcodeData->getWidth()*2.2,52);
        $codAmount = null;
        if (
            $isCod
            && $order->sellerOrders->count() === 1
            && $order->items->isNotEmpty()
            && $order->items->every(fn ($item) =>
                (int)$item->seller_order_id === (int)$sellerOrder->id
                && (int)$item->order_id === (int)$order->id)
            && is_numeric($order->total)
            && (float)$order->total >= 0
        ) {
            $codAmount = (float)$order->total;
        }

        return [
            'shipment'=>$shipment,
            'sellerOrder'=>$sellerOrder,
            'store'=>$sellerOrder->store,
            'order'=>$order,
            'recipient'=>data_get($delivery,'full_name') ?: $order->buyer_name,
            'recipientPhone'=>data_get($delivery,'phone') ?: $order->buyer_phone,
            'deliveryAddress'=>implode(', ',$addressLines),
            'pickupAddress'=>$pickup,
            'paymentMethod'=>$isCod ? 'Cash on Delivery' : $order->paymentMethodLabel(),
            'paymentStatus'=>$isCod ? null : $order->paymentStatusLabel(),
            'isCod'=>$isCod,
            'codAmount'=>$codAmount,
            'barcodeSvg'=>$barcodeSvg,
            'printedAt'=>now()->format('M d, Y g:i A'),
        ];
    }

    public function pdf(array $document)
    {
        $shipment = $document['shipment'];
        $filename = 'SHOPPICK_'.$shipment->shipment_number.'_Waybill.pdf';

        return $this->exports->pdf('seller.waybills.pdf',$document,$filename,[0,0,432,288],'portrait',false);
    }

    private function storeFor(User $user): Store
    {
        $store = $user->store;
        abort_unless($store,404);
        return $store;
    }

    private function sellerShipments(Store $store): Builder
    {
        return Shipment::query()->whereHas('sellerOrder',fn ($sellerOrder) => $sellerOrder->where('store_id',$store->id));
    }
}
