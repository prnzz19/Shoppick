<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\CommissionSetting;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        protected CartService $cartService,
        protected InventoryService $inventory
    ) {
    }

    public function computeTotals($userId, $voucherCode = null, $items = null): array
    {
        $selectedItems = $items ?? $this->cartService->items($userId)->filter->selected;

        if ($selectedItems->isEmpty()) {
            throw new Exception('Your cart is empty.');
        }

        $subtotal = $selectedItems->sum(fn ($i) => $i->lineTotal());

        // Validate stock before checkout
        foreach ($selectedItems as $item) {
            $this->cartService->validateItem($userId, $item);
            $maxStock = $item->availableStock();
            if ($maxStock <= 0) {
                throw new Exception("{$item->product->name} is out of stock.");
            }
            if ($item->quantity > $maxStock) {
                throw new Exception("{$item->product->name} only has {$maxStock} in stock.");
            }
        }

        $voucher = null;
        $voucherDiscount = 0.0;

        if ($voucherCode) {
            $voucher = Voucher::where('code', $voucherCode)->first();
            if (! $voucher || ! $voucher->isValidFor($userId, $subtotal)) {
                throw new Exception('This voucher is invalid or expired.');
            }
            $voucherDiscount = $voucher->computeDiscount($subtotal);
        }

        $shippingFee = $this->cartService->shippingFee($subtotal);
        $total = $subtotal - $voucherDiscount + $shippingFee;

        return [
            'subtotal' => round($subtotal, 2),
            'shipping_fee' => round($shippingFee, 2),
            'voucher_discount' => round($voucherDiscount, 2),
            'total' => round(max($total, 0), 2),
            'voucher' => $voucher,
        ];
    }

    public function placeOrder($userId, array $data, $items = null, bool $clearCart = true): array
    {
        $selectedItems = $items ?? $this->cartService->items($userId)->filter->selected;
        $totals = $this->computeTotals($userId, $data['voucher_code'] ?? null, $selectedItems);

        $address = \App\Models\Address::where('user_id', $userId)->findOrFail($data['address_id']);
        $paymentMethod = $data['payment_method'];
        $payment = PaymentService::driver($paymentMethod);

        $order = DB::transaction(function () use (
            $userId, $data, $totals, $address, $paymentMethod, $payment, $selectedItems, $clearCart
        ) {
            // Reserve inventory (transaction-safe, prevents overselling)
            $this->inventory->reserveItems($selectedItems);

            $order = \App\Models\Order::create([
                'user_id' => $userId,
                'order_number' => \App\Models\Order::generateOrderNumber(),
                'status' => $payment->initialOrderStatus(),
                'payment_method' => $paymentMethod,
                'payment_status' => 'unpaid',
                'subtotal' => $totals['subtotal'],
                'shipping_fee' => $totals['shipping_fee'],
                'voucher_discount' => $totals['voucher_discount'],
                'voucher_id' => $totals['voucher']?->id,
                'total' => $totals['total'],
                'buyer_name' => $address->full_name,
                'buyer_phone' => $address->phone,
                'shipping_address' => $address->toArray(),
                'note' => $data['note'] ?? null,
            ]);

            foreach ($selectedItems as $item) {
                // Items are attached to their seller partition below.
            }

            $commission = CommissionSetting::first();
            $rate = $commission?->is_enabled ? (float) $commission->default_rate : 0.0;
            $groups = $selectedItems->groupBy(fn ($item) => $item->product->store_id ?: 0);
            foreach ($groups as $storeId => $items) {
                $groupSubtotal = $items->sum(fn ($item) => $item->lineTotal());
                $groupShipping = round($totals['shipping_fee'] * ($groupSubtotal / max($totals['subtotal'], 0.01)), 2);
                $commissionAmount = round($groupSubtotal * $rate / 100, 2);
                $sellerOrder = $order->sellerOrders()->create([
                    'store_id' => $storeId ?: null,
                    'seller_order_number' => $order->order_number.'-S'.($order->sellerOrders()->count() + 1),
                    'status' => $order->status,
                    'subtotal' => $groupSubtotal,
                    'shipping_fee' => $groupShipping,
                    'commission_rate' => $rate,
                    'commission_amount' => $commissionAmount,
                    'seller_total' => $groupSubtotal - $commissionAmount,
                ]);
                $sellerOrder->histories()->create(['order_id' => $order->id, 'status' => $order->status, 'note' => 'Order placed']);
                foreach ($items as $item) {
                    $order->items()->create([
                    'seller_order_id' => $sellerOrder->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product->name,
                    'product_image' => $item->product->getMainImageAttribute(),
                    'variant_label' => $item->variant ? $item->variant->getLabelAttribute() : null,
                    'sku' => $item->variant?->sku ?? $item->product->sku,
                    'price' => $item->unitPrice(),
                    'quantity' => $item->quantity,
                    'total' => $item->lineTotal(),
                ]);
                }
                if ($sellerOrder->store) {
                    $itemCount = $items->sum('quantity');
                    NotificationService::send(
                        $sellerOrder->store->user_id,
                        'New order received!',
                        "Order #{$order->order_number} contains {$itemCount} item".($itemCount === 1 ? '' : 's')." from your store. Seller total: ₱".number_format($sellerOrder->seller_total, 2).'.',
                        'order',
                        route('seller.orders.show', $sellerOrder),
                        ['order_number' => $order->order_number, 'seller_order_id' => $sellerOrder->id, 'item_count' => $itemCount],
                        'package'
                    );
                }
            }

            // Record voucher usage
            if ($totals['voucher']) {
                $totals['voucher']->increment('used_count');
                $totals['voucher']->usages()->create([
                    'user_id' => $userId,
                    'order_id' => $order->id,
                ]);
            }

            // Create payment record
            $order->payments()->create([
                'method' => $paymentMethod,
                'status' => 'pending',
                'reference' => null,
                'gateway' => $paymentMethod,
                'amount' => $totals['total'],
            ]);

            // Execute payment
            $result = $payment->charge($order);

            // Update payment record with gateway result
            $settled = (bool) ($result['settled'] ?? false);
            $order->payments()->latest('id')->first()?->update([
                'status' => $result['payment_status'] ?? ($settled ? 'paid' : ($result['success'] ? 'pending' : 'failed')),
                'reference' => $result['reference'] ?? null,
                'transaction_id' => $result['reference'] ?? null,
                'details' => $result['details'] ?? [],
                'paid_at' => $settled ? now() : null,
            ]);

            // Clear selected cart items
            if ($clearCart) {
                $this->cartService->clearSelected($userId);
            }

            return $order;
        });

        return ['order' => $order->load('items', 'sellerOrders.store'), 'payment' => $payment];
    }

    public function cancelOrder($order, string $reason = null): void
    {
        if (! $order->canBeCancelled()) {
            throw new Exception('This order can no longer be cancelled.');
        }

        DB::transaction(function () use ($order, $reason) {
            $this->inventory->restoreItems($order->items);

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $order->sellerOrders()->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            if ($order->payment_method === 'cod' && ! in_array($order->payment_status, ['paid','refunded'], true)) {
                $order->update(['payment_status'=>'cancelled','paid_at'=>null]);
                $order->payments()->whereNotIn('status',['paid','refunded'])->update(['status'=>'cancelled','paid_at'=>null]);
            }

            // Release voucher usage count
            if ($order->voucher_id && $order->voucher) {
                $voucher = $order->voucher;
                if ($voucher->used_count > 0) {
                    $voucher->decrement('used_count');
                }
                $voucher->usages()->where('order_id', $order->id)->delete();
            }
        });
    }
}
