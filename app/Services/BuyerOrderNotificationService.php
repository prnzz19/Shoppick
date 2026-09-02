<?php

namespace App\Services;

use App\Models\{Order,SellerOrder};

class BuyerOrderNotificationService
{
    private const MESSAGES = [
        'confirmed' => ['Order Confirmed', 'Your order has been confirmed by the seller.'],
        'processing' => ['Order Processing', 'The seller is preparing your order.'],
        'packed' => ['Order Packed', 'Your order has been packed and is almost ready for shipment.'],
        'ready_to_ship' => ['Ready to Ship', 'Your order is ready for pickup by SHOPPICK Logistics.'],
        'shipped' => ['Order Shipped', 'Your order has been picked up and is on the way.'],
        'out_for_delivery' => ['Out for Delivery', 'Your order is out for delivery.'],
        'delivered' => ['Order Delivered', 'Your order has been delivered.'],
        'completed' => ['Order Completed', 'Your order has been completed. Thank you for shopping with SHOPPICK.'],
    ];

    public function send(Order $order,string $status,?SellerOrder $sellerOrder=null): void
    {
        if(!isset(self::MESSAGES[$status])) return;
        [$title,$message]=self::MESSAGES[$status];
        $sellerOrderId=$sellerOrder?->id;
        $query=$order->user->notificationsData()->where('type','buyer_order_progress')
            ->where('data->order_id',$order->id)->where('data->status',$status);
        $sellerOrderId ? $query->where('data->seller_order_id',$sellerOrderId) : $query->whereNull('data->seller_order_id');
        if($query->exists()) return;

        NotificationService::send($order->user_id,$title,"Order {$order->order_number}: {$message}",'buyer_order_progress',
            route('orders.show',$order->order_number),[
                'order_id'=>$order->id,'order_number'=>$order->order_number,
                'seller_order_id'=>$sellerOrderId,'status'=>$status,
            ],'package');
    }
}
