<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;

class OrderProgressService
{
    public const BUYER_STEPS = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'delivered', 'completed'];

    public const SHIPPED_SHIPMENT_STATUSES = [
        'picked_up', 'at_hub', 'hub_transfer', 'in_transit', 'out_for_delivery', 'delivery_attempted',
    ];

    public const BUYER_TABS = ['all','to_pay','to_ship','to_receive','completed','cancelled','history'];

    public function applyBuyerTab($query,string $tab)
    {
        return match($tab){
            'to_pay'=>$query->where('payment_method','!=','cod')->whereIn('payment_status',['unpaid','pending']),
            'to_ship'=>$query->where(function($orders){
                $orders->whereIn('status',['confirmed','processing','packed','ready_to_ship'])
                    ->orWhereHas('sellerOrders',fn($sellerOrders)=>$sellerOrders->whereIn('status',['pending','confirmed','processing','packed','ready_to_ship']));
            })->where(fn($orders)=>$orders->where('payment_method','cod')->orWhereNotIn('payment_status',['unpaid','pending'])),
            'to_receive'=>$query->where(function($orders){
                $orders->whereIn('status',['shipped','delivered'])
                    ->orWhereHas('sellerOrders',fn($sellerOrders)=>$sellerOrders->whereIn('status',['shipped','delivered']))
                    ->orWhereHas('shipments',fn($shipments)=>$shipments->whereIn('status',array_merge(self::SHIPPED_SHIPMENT_STATUSES,['delivered'])));
            }),
            'completed'=>$query->where('status','completed'),
            'cancelled'=>$query->where('status','cancelled'),
            'history'=>$query->whereIn('status',['completed','cancelled','refunded']),
            default=>$query,
        };
    }

    public function sellerOrderStatus(SellerOrder $sellerOrder): string
    {
        $shipmentStatus = $sellerOrder->shipment?->status;

        if ($shipmentStatus === 'delivered') {
            return 'delivered';
        }

        if (in_array($shipmentStatus, self::SHIPPED_SHIPMENT_STATUSES, true)) {
            return 'shipped';
        }

        return match ($sellerOrder->status) {
            'ready_to_ship' => 'packed',
            'cancelled' => 'cancelled',
            default => in_array($sellerOrder->status, self::BUYER_STEPS, true)
                ? $sellerOrder->status
                : 'pending',
        };
    }

    public function orderStatus(Order $order): string
    {
        $order->loadMissing('sellerOrders.shipment');
        $statuses = $order->sellerOrders->map(fn (SellerOrder $sellerOrder) => $this->sellerOrderStatus($sellerOrder));

        if ($statuses->isEmpty()) {
            return in_array($order->status, self::BUYER_STEPS, true) ? $order->status : 'pending';
        }

        if ($statuses->every(fn ($status) => $status === 'cancelled')) {
            return 'cancelled';
        }

        $rank = array_flip(self::BUYER_STEPS);

        return $statuses->reject(fn ($status) => $status === 'cancelled')
            ->sortBy(fn ($status) => $rank[$status] ?? 0)
            ->first() ?? 'pending';
    }

    public function tracker(Order $order): array
    {
        $status = $this->orderStatus($order);

        return [
            'steps' => self::BUYER_STEPS,
            'status' => $status,
            'index' => array_search($status, self::BUYER_STEPS, true),
            'visible' => ! in_array($status, ['cancelled', 'refunded'], true),
        ];
    }

    public function syncOrder(Order $order): string
    {
        $status = $this->orderStatus($order->fresh());
        $updates = ['status' => $status];

        if ($status === 'completed' && ! $order->completed_at) {
            $updates['completed_at'] = now();
        }
        if ($status === 'cancelled' && ! $order->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        $order->update($updates);

        return $status;
    }

    public function syncShipment(Shipment $shipment): string
    {
        $shipment->loadMissing('sellerOrder.shipment', 'order.sellerOrders.shipment');
        $sellerOrder = $shipment->sellerOrder;

        if ($shipment->status === 'delivered') {
            $sellerOrder->update(['status' => 'delivered']);
        } elseif (in_array($shipment->status, self::SHIPPED_SHIPMENT_STATUSES, true)) {
            $sellerOrder->update(['status' => 'shipped']);
        }

        return $this->syncOrder($shipment->order);
    }
}
