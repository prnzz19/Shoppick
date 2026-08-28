<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Exception;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService)
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $items = $this->cartService->items(auth()->id())->values();
        $subtotal = $items->filter->selected->sum(fn ($i) => $i->lineTotal());
        $shipping = $this->cartService->shippingFee($subtotal);

        return view('storefront.cart.index', compact('items', 'subtotal', 'shipping'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->cartService->add(
                auth()->id(),
                $data['product_id'],
                $data['product_variant_id'] ?? null,
                $data['quantity']
            );
        } catch (Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        session()->flash('cart_toast', 'Added to cart');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'cart_count' => $result['cart_count']]);
        }

        if ($request->input('checkout_now')) {
            return redirect()->route('checkout');
        }

        return redirect()->route('cart.index');
    }

    public function updateQuantity(Request $request, $itemId)
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        $result = $this->cartService->updateQuantity(auth()->id(), $itemId, $request->input('quantity'));

        if ($request->expectsJson()) {
            return $this->cartJson();
        }

        return back()->with('success', 'Cart updated');
    }

    public function remove(Request $request, $itemId)
    {
        $this->cartService->remove(auth()->id(), $itemId);

        if ($request->expectsJson()) {
            return $this->cartJson();
        }

        return back()->with('success', 'Item removed');
    }

    public function toggleSelected(Request $request, $itemId)
    {
        $this->cartService->toggleSelected(auth()->id(), $itemId);

        if ($request->expectsJson()) {
            return $this->cartJson();
        }

        return back();
    }

    protected function cartJson()
    {
        $items = $this->cartService->items(auth()->id())->values();
        $subtotal = $items->filter->selected->sum(fn ($i) => $i->lineTotal());
        $shipping = $this->cartService->shippingFee($subtotal);

        return response()->json([
            'success' => true,
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'quantity' => $i->quantity,
                'max_stock' => $i->availableStock(),
                'line_total' => (float) $i->lineTotal(),
            ]),
            'subtotal' => (float) $subtotal,
            'shipping' => (float) $shipping,
            'total' => (float) ($subtotal + $shipping),
            'cart_count' => $this->cartService->count(auth()->id()),
        ]);
    }
}
