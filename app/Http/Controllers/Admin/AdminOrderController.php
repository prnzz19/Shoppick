<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Services\InventoryService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $query->where('order_number', 'like', "%{$request->input('q')}%")
                ->orWhere('buyer_name', 'like', "%{$request->input('q')}%");
        }

        $orders = $query->latest()->paginate(12)->withQueryString();

        $statusCounts = collect(Order::STATUSES)->mapWithKeys(
            fn ($s) => [$s => Order::where('status', $s)->count()]
        );

        return view('admin.orders.index', compact('orders', 'statusCounts'));
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'items.variant', 'payments', 'user', 'voucher']);
        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => ['required', 'in:' . implode(',', Order::STATUSES)],
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
