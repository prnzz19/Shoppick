<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Services\InventoryService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SellerOrder::with(['order.user', 'store.user', 'items']);

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'refunded') {
                $query->whereHas('order', fn ($order) => $order->where('status', 'refunded'));
            } elseif ($status === 'cancelled') {
                $query->where(function ($sellerOrder) {
                    $sellerOrder->where('status', 'cancelled')
                        ->orWhereHas('order', fn ($order) => $order->where('status', 'cancelled'));
                });
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('shop_id')) {
            $request->input('shop_id') === 'unassigned'
                ? $query->whereNull('store_id')
                : $query->where('store_id', $request->integer('shop_id'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($sellerOrder) use ($search) {
                $sellerOrder->where('seller_order_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($order) use ($search) {
                        $order->where('order_number', 'like', "%{$search}%")
                            ->orWhere('buyer_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($buyer) => $buyer->where('email', 'like', "%{$search}%"));
                    });
            });
        }

        $orders = $query->latest()->paginate(12)->withQueryString();
        $shops = Store::with('user')->orderBy('name')->get();

        $statuses = array_values(array_unique(array_merge(SellerOrder::STATUSES, ['refunded'])));
        $statusCounts = collect($statuses)->mapWithKeys(function ($status) {
            $count = match ($status) {
                'refunded' => SellerOrder::whereHas('order', fn ($order) => $order->where('status', 'refunded'))->count(),
                'cancelled' => SellerOrder::where(fn ($sellerOrder) => $sellerOrder->where('status', 'cancelled')->orWhereHas('order', fn ($order) => $order->where('status', 'cancelled')))->count(),
                default => SellerOrder::where('status', $status)->count(),
            };
            return [$status => $count];
        });
        $allOrdersCount = SellerOrder::count();

        $storeIds = $orders->getCollection()->pluck('store_id')->filter()->unique()->values();
        $stats = SellerOrder::where(function ($query) use ($storeIds, $orders) {
                $query->whereIn('store_id', $storeIds);
                if ($orders->getCollection()->contains(fn ($order) => $order->store_id === null)) {
                    $query->orWhereNull('store_id');
                }
            })
            ->selectRaw('store_id, COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count")
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->groupBy('store_id')->get()
            ->keyBy(fn ($row) => $row->store_id === null ? 'unassigned' : (string) $row->store_id);

        $orderGroups = $orders->getCollection()
            ->groupBy(fn ($order) => $order->store_id === null ? 'unassigned' : (string) $order->store_id)
            ->map(function ($groupOrders, $key) use ($stats) {
                return (object) [
                    'key' => $key,
                    'store' => $groupOrders->first()->store,
                    'orders' => $groupOrders->sortByDesc('created_at')->values(),
                    'stats' => $stats->get($key),
                ];
            })
            ->sortBy(fn ($group) => $group->store?->name ?? '~~~ Unassigned');

        return view('admin.orders.index', compact('orders', 'orderGroups', 'shops', 'statusCounts', 'allOrdersCount'));
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'payments', 'user', 'voucher',
            'sellerOrders.store.user', 'sellerOrders.items.product', 'sellerOrders.items.variant',
            'shipments.store', 'shipments.rider', 'shipments.vehicle', 'shipments.proofOfDelivery', 'shipments.events.actor']);
        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => ['required', 'in:cancelled,refunded'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $newStatus = $request->input('status');

        // Refund on cancel/refund
        if (in_array($newStatus, ['cancelled', 'refunded']) && ! in_array($order->status, ['cancelled', 'refunded'])) {
            app(InventoryService::class)->restoreItems($order->items);
            $order->update([
                'status' => $newStatus,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason'),
            ]);
            if ($newStatus === 'cancelled') {
                $order->sellerOrders()->whereNotIn('status', ['cancelled', 'completed'])->update(['status' => 'cancelled']);
            }

            $this->notifyBuyer($order, 'Order ' . $newStatus, "Your order {$order->order_number} has been {$newStatus}.");
        } else {
            $order->update(['status' => $newStatus]);

            // Mark payment as paid when order is delivered/completed for COD
            if (in_array($newStatus, ['delivered', 'completed']) && $order->payment_status === 'cod') {
                $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
            }

            $this->notifyBuyer($order, 'Order ' . $newStatus, "Your order {$order->order_number} is now {$newStatus}.");
        }

        AdminActivityLog::record('order.status', 'order', $order->id, ['status' => $newStatus]);

        return back()->with('success', 'Order status updated.');
    }

    protected function notifyBuyer(Order $order, $title, $body): void
    {
        NotificationService::send(
            $order->user_id,
            $title,
            $body,
            'order',
            route('admin.orders.show', $order->id),
            ['order_number' => $order->order_number],
            'package'
        );
    }
}
