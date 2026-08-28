<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Exception;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all');

        $statuses = [
            'to_pay' => ['confirmed', 'pending'],
            'to_ship' => ['processing', 'packed'],
            'to_receive' => ['shipped', 'delivered'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled', 'refunded'],
        ];

        $query = auth()->user()->orders()->with('items.product');

        if ($tab !== 'all' && isset($statuses[$tab])) {
            $query->whereIn('status', $statuses[$tab]);
        }

        $orders = $query->latest()->paginate(8)->withQueryString();

        return view('storefront.orders.index', compact('orders', 'tab'));
    }

    public function show($orderNumber)
    {
        $order = auth()->user()->orders()
            ->with(['items.product', 'items.variant', 'payments', 'voucher', 'reviews'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('storefront.orders.show', compact('order'));
    }

    public function cancel(Request $request, $orderNumber)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $order = auth()->user()->orders()->where('order_number', $orderNumber)->firstOrFail();

        try {
            $this->orderService->cancelOrder($order, $request->input('reason'));
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        \App\Services\NotificationService::send(
            auth()->id(),
            'Order cancelled',
            "Your order {$order->order_number} has been cancelled.",
            'order',
            route('orders.show', $order->order_number),
            ['order_number' => $order->order_number],
            'x'
        );

        return back()->with('success', 'Order cancelled.');
    }

    public function confirmReceived($orderNumber)
    {
        $order = auth()->user()->orders()->where('order_number', $orderNumber)->firstOrFail();

        if (! in_array($order->status, ['delivered', 'shipped'])) {
            return back()->with('error', 'This order cannot be confirmed yet.');
        }

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'payment_status' => 'paid',
        ]);
        $order->sellerOrders()->whereIn('status', ['shipped', 'delivered'])->update([
            'status' => 'completed', 'completed_at' => now(),
        ]);

        app(\App\Services\InventoryService::class)->fulfillOrder($order);

        return back()->with('success', 'Order completed. Thank you!');
    }
}
