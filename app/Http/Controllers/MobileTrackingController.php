<?php

namespace App\Http\Controllers;

use App\Models\SellerOrder;
use App\Services\MarketplaceShipmentTracking;
use Illuminate\Http\Request;

class MobileTrackingController extends Controller
{
    public function buyer(Request $r, string $order, MarketplaceShipmentTracking $tracking)
    {
        abort_unless($r->user()->isBuyer(), 403);
        $o = $r->user()->orders()->where('order_number', $order)->firstOrFail();
        $shipments = $o->shipments()->with(['store', 'events'])->get();

        return response()->json(['order_number' => $o->order_number, 'status' => $o->status,
            'shipments' => $shipments->map(fn ($s) => $tracking->data($s)),
            'poll' => ! in_array($o->status, ['cancelled', 'completed', 'delivered'], true) && $shipments->contains(fn ($s) => ! in_array($s->status, ['delivered', 'completed', 'delivery_failed', 'returned', 'exception', 'delivery_attempted', 'cancelled'], true))]);
    }

    private function sellerScope(Request $r)
    {
        abort_unless($r->user()->hasApprovedSellerAccess(), 403);

        return SellerOrder::query()->whereHas('store', fn ($q) => $q->where('user_id', $r->user()->id)->where('status', 'active'));
    }

    public function sellerOrders(Request $r, MarketplaceShipmentTracking $tracking)
    {
        return $this->sellerScope($r)->with(['order', 'store', 'shipment.events', 'shipment.store'])->latest()->paginate(20)
            ->through(fn ($so) => ['id' => $so->id, 'seller_order_number' => $so->seller_order_number,
                'order_number' => $so->order?->order_number, 'shop' => $so->store?->name, 'status' => $so->status,
                'created_at' => $so->created_at?->toIso8601String(),
                'shipments' => $so->shipment ? [$tracking->summary($so->shipment)] : []]);
    }

    public function seller(Request $r, int $sellerOrder, MarketplaceShipmentTracking $tracking)
    {
        $so = $this->sellerScope($r)->with(['shipment.store', 'shipment.events'])->findOrFail($sellerOrder);
        $shipment = $so->shipment ? $tracking->data($so->shipment) : null;

        return response()->json(['order_number' => $so->seller_order_number, 'status' => $so->status,
            'shipments' => $shipment ? [$shipment] : [],
            'poll' => ! in_array($so->status, ['cancelled', 'completed', 'delivered'], true) && $shipment && $shipment['poll']]);
    }
}
