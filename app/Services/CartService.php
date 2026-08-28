<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getOrCreateCart($userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }

    public function items($userId)
    {
        return $this->getOrCreateCart($userId)
            ->items()
            ->with(['product.images', 'product.store', 'variant'])
            ->get()
            ->filter(fn ($item) => $item->product !== null);
    }

    public function add($userId, $productId, $variantId = null, $quantity = 1): array
    {
        $product = Product::findOrFail($productId);

        if (! $product->is_active) {
            throw new Exception('This product is not available.');
        }

        $variant = null;
        $maxStock = $product->stock;

        if ($variantId) {
            $variant = ProductVariant::where('product_id', $product->id)->findOrFail($variantId);
            $maxStock = $variant->stock;
        }

        $cart = $this->getOrCreateCart($userId);

        return DB::transaction(function () use ($cart, $product, $variant, $variantId, $quantity, $maxStock) {
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->first();

            $currentQty = $item ? $item->quantity : 0;
            $newQty = $currentQty + (int) $quantity;

            if ($newQty > $maxStock) {
                $newQty = (int) $maxStock;
            }
            if ($newQty <= 0) {
                throw new Exception('This product is out of stock.');
            }

            if ($item) {
                $item->update(['quantity' => $newQty, 'selected' => true]);
            } else {
                $item = $cart->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $newQty,
                    'selected' => true,
                ]);
            }

            return ['success' => true, 'cart_count' => $cart->items()->sum('quantity')];
        });
    }

    public function updateQuantity($userId, $itemId, $quantity): array
    {
        $cart = $this->getOrCreateCart($userId);
        $item = $cart->items()->findOrFail($itemId);

        $quantity = (int) $quantity;
        if ($quantity <= 0) {
            $item->delete();
            return ['success' => true, 'removed' => true];
        }

        $maxStock = $item->availableStock();
        if ($quantity > $maxStock) {
            $quantity = (int) $maxStock;
        }

        $item->update(['quantity' => $quantity]);

        return ['success' => true];
    }

    public function remove($userId, $itemId): void
    {
        $cart = $this->getOrCreateCart($userId);
        $cart->items()->where('id', $itemId)->delete();
    }

    public function toggleSelected($userId, $itemId): void
    {
        $cart = $this->getOrCreateCart($userId);
        $item = $cart->items()->findOrFail($itemId);
        $item->update(['selected' => ! $item->selected]);
    }

    /** Subtotal of selected items only (used for checkout / voucher math). */
    public function selectedSubtotal($userId)
    {
        return $this->items($userId)->filter->selected->sum(fn ($i) => $i->lineTotal());
    }

    public function shippingFee($subtotal): float
    {
        // Simple shipping rule: free above $500, otherwise $50.
        return $subtotal >= 500 ? 0 : 50;
    }

    public function clearSelected($userId): void
    {
        $cart = $this->getOrCreateCart($userId);
        $cart->items()->where('selected', true)->delete();
    }

    public function count($userId): int
    {
        $cart = $this->getOrCreateCart($userId);
        return $cart->items()->sum('quantity');
    }
}
