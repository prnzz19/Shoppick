<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\ShipmentTrackingService;
use App\Services\OrderProgressService;
use App\Models\Shipment;
use App\Models\OrderItem;
use App\Services\CartService;
use Exception;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request, OrderProgressService $progress)
    {
        $tab = $request->query('tab', 'all');

        if (! in_array($tab, OrderProgressService::BUYER_TABS, true)) $tab='all';

        $query = $request->user()->orders()->with([
            'items.product.images', 'items.variant', 'sellerOrders.store.user',
            'payments', 'reviews',
        ]);

        $progress->applyBuyerTab($query,$tab);

        if ($tab === 'history') {
            $historyStatus = $request->query('history_status', 'all');
            if (in_array($historyStatus, ['completed', 'cancelled', 'refunded'], true)) {
                $query->where('status', $historyStatus);
            }

            if ($search = trim((string) $request->query('q'))) {
                $query->where(function ($orders) use ($search) {
                    $orders->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', "%{$search}%"))
                        ->orWhereHas('sellerOrders.store', fn ($stores) => $stores->where('name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($users) => $users->where('name', 'like', "%{$search}%")));
                });
            }

            match ($request->query('date', 'all')) {
                '30_days' => $query->where('created_at', '>=', now()->subDays(30)),
                '3_months' => $query->where('created_at', '>=', now()->subMonths(3)),
                '6_months' => $query->where('created_at', '>=', now()->subMonths(6)),
                'this_year' => $query->whereYear('created_at', now()->year),
                default => null,
            };
        }

        match ($tab === 'history' ? $request->query('sort', 'newest') : 'newest') {
            'oldest' => $query->oldest(),
            'total_high' => $query->orderByDesc('total'),
            'total_low' => $query->orderBy('total'),
            default => $query->latest(),
        };

        $orders = $query->paginate(12)->withQueryString();
        $tabCounts=collect(OrderProgressService::BUYER_TABS)->mapWithKeys(function($key)use($request,$progress){
            $query=$request->user()->orders()->getQuery();
            return [$key=>$progress->applyBuyerTab($query,$key)->count()];
        });
        $unreadOrderIds = $request->user()->notificationsData()->unread()
            ->where('type', 'buyer_order_progress')->get()->pluck('data.order_id')->filter()->map(fn ($id) => (int) $id)->unique();

        return view('storefront.orders.index', compact('orders', 'tab', 'unreadOrderIds','tabCounts'));
    }

    public function buyAgain(Request $request, $orderNumber, OrderItem $item, CartService $cart)
    {
        $order = $request->user()->orders()
            ->where('order_number', $orderNumber)
            ->whereIn('status', ['completed', 'cancelled', 'refunded'])
            ->firstOrFail();

        abort_unless((int) $item->order_id === (int) $order->id, 404);

        try {
            $cart->add($request->user()->id, $item->product_id, $item->product_variant_id, 1);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('cart.index')->with('success', 'Product added to cart using its current price and availability.');
    }

    public function show($orderNumber, OrderProgressService $progress)
    {
        $order = auth()->user()->orders()
            ->with(['items.product', 'items.variant', 'payments', 'voucher', 'reviews',
                'sellerOrders.shipment', 'sellerOrders.histories.changedBy.roles',
                'shipments.store', 'shipments.rider', 'shipments.vehicle', 'shipments.proofOfDelivery', 'shipments.events'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        auth()->user()->notificationsData()->unread()->where('type','buyer_order_progress')
            ->where('data->order_id',$order->id)->update(['read_at'=>now()]);

        $tracker = $progress->tracker($order);

        return view('storefront.orders.show', compact('order', 'tracker'));
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
        ]);
        $completedSellerOrders=$order->sellerOrders()->whereIn('status',['shipped','delivered'])->get();
        foreach($completedSellerOrders as $sellerOrder){
            $sellerOrder->update(['status'=>'completed','completed_at'=>now()]);
            $sellerOrder->histories()->firstOrCreate(['status'=>'completed'],[
                'order_id'=>$order->id,'changed_by'=>auth()->id(),'note'=>'Buyer confirmed the Order was received.',
            ]);
        }

        app(\App\Services\InventoryService::class)->fulfillOrder($order);
        app(\App\Services\BuyerOrderNotificationService::class)->send($order->fresh('user'), 'completed');

        return back()->with('success', 'Order completed. Thank you!');
    }

    public function tracking(Request $request, $orderNumber, Shipment $shipment, ShipmentTrackingService $tracking)
    {
        $order=$request->user()->orders()->where('order_number',$orderNumber)->firstOrFail();
        return response()->json($tracking->buyerFeed($order,$shipment,$request->user()));
    }
}
