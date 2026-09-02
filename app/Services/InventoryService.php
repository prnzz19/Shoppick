<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Decrement reserved stock for a set of items (transaction-safe).
     * Throws if any item would go negative.
     */
    public function reserveItems($items): void
    {
        foreach ($items as $item) {
            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
            if (! $product) {
                throw new Exception('One of the products no longer exists.');
            }

            if ($item->product_variant_id) {
                $variant = ProductVariant::where('id', $item->product_variant_id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();
                if (! $variant) {
                    throw new Exception("The selected option for {$product->name} is no longer available.");
                }
                $newStock = $variant->stock - $item->quantity;
                if ($newStock < 0) {
                    throw new Exception("Insufficient stock for {$product->name} ({$variant->getLabelAttribute()}).");
                }
                $variant->update(['stock' => $newStock]);
            }

            $newProductStock = $product->stock - $item->quantity;
            if ($newProductStock < 0) {
                throw new Exception("Insufficient stock for {$product->name}.");
            }
            $product->update(['stock' => $newProductStock]);
        }
    }

    /** Restore stock for order items when an order is cancelled. */
    public function restoreItems($orderItems): void
    {
        foreach ($orderItems as $orderItem) {
            $product = Product::withTrashed()->find($orderItem->product_id);
            if ($product) {
                $product->increment('stock', $orderItem->quantity);
            }

            if ($orderItem->product_variant_id) {
                $variant = ProductVariant::find($orderItem->product_variant_id);
                if ($variant) {
                    $variant->increment('stock', $orderItem->quantity);
                }
            }
        }
    }

    /** Mark a product's sold count up when an order is completed. */
    public function fulfillOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $orderItem) {
                Product::where('id', $orderItem->product_id)
                    ->increment('sold_count', $orderItem->quantity);
            }

            $order->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'completed_at' => now(),
            ]);
        });
    }
}
