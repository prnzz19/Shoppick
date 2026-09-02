<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
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
        [$product, $variant, $maxStock] = $this->validatePurchase($userId, $productId, $variantId, $quantity);

        $cart = $this->getOrCreateCart($userId);

        return DB::transaction(function () use ($cart, $product, $variant, $variantId, $quantity, $maxStock) {
            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->first();

            $currentQty = $item ? $item->quantity : 0;
            $newQty = $currentQty + (int) $quantity;

            if ($newQty > $maxStock) {
                throw new Exception("Only {$maxStock} items are currently available.");
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

    public function purchaseItem($userId, $productId, $variantId = null, $quantity = 1): CartItem
    {
        [$product, $variant] = $this->validatePurchase($userId, $productId, $variantId, $quantity);

        $item = new CartItem([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => (int) $quantity,
            'selected' => true,
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('variant', $variant);

        return $item;
    }

    public function validateItem($userId, CartItem $item): void
    {
        $this->validatePurchase(
            $userId,
            $item->product_id,
            $item->product_variant_id,
            $item->quantity
        );
    }

    protected function validatePurchase($userId, $productId, $variantId, $quantity): array
    {
        $user = User::with('roles')->findOrFail($userId);
        if (! $user->isBuyer()) {
            throw new Exception('Only Buyer accounts can place marketplace orders.');
        }

        $product = Product::active()->with(['variants', 'images', 'store.user', 'store.sellerProfile'])->find($productId);
        if (! $product) {
            throw new Exception('This product is currently unavailable.');
        }
        if ($product->store?->user_id === $user->id) {
            throw new Exception('You cannot purchase your own product.');
        }

        $variant = null;
        if ($product->variants->isNotEmpty()) {
            if (! $variantId) {
                throw new Exception('Please select a product option.');
            }
            $variant = $product->variants->firstWhere('id', (int) $variantId);
            if (! $variant) {
                throw new Exception('The selected product option is invalid.');
            }
        } elseif ($variantId) {
            throw new Exception('The selected product option is invalid.');
        }

        $maxStock = (int) ($variant?->stock ?? $product->stock);
        if ($maxStock < 1) {
            throw new Exception('This product is out of stock.');
        }
        if ((int) $quantity < 1 || (int) $quantity > $maxStock) {
            throw new Exception("Only {$maxStock} items are currently available.");
        }

        return [$product, $variant, $maxStock];
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
            throw new Exception("Only {$maxStock} items are currently available.");
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
