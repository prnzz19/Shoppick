<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cartService, protected OrderService $orderService)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($user->isBuyer() && ! $user->hasCompleteBuyerProfile()) {
            return redirect()->route('profile.complete')->with('error', 'Complete your mobile number and address before checkout.');
        }
        $checkoutMode = $request->input('mode') === 'buy_now' ? 'buy_now' : 'cart';

        try {
            $items = $checkoutMode === 'buy_now'
                ? collect([$this->buyNowItem($user->id)])
                : $this->cartService->items($user->id)->filter->selected;
        } catch (Exception $e) {
            return redirect()->route('products.index')->with('error', $e->getMessage());
        }

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        try {
            $computed = $this->orderService->computeTotals($user->id, null, $items);
        } catch (Exception $e) {
            return redirect()->route($checkoutMode === 'buy_now' ? 'products.index' : 'cart.index')
                ->with('error', $e->getMessage());
        }
        $totals = $computed['subtotal'];
        $shipping = $computed['shipping_fee'];

        $addresses = $user->addresses;
        $paymentMethods = PaymentService::availableMethods();

        return view('storefront.checkout.index', compact('items', 'totals', 'shipping', 'addresses', 'paymentMethods', 'checkoutMode'));
    }

    public function applyVoucher(Request $request)
    {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string', 'max:50'],
            'checkout_mode' => ['nullable', 'in:cart,buy_now'],
        ]);

        try {
            $items = ($validated['checkout_mode'] ?? 'cart') === 'buy_now'
                ? collect([$this->buyNowItem(auth()->id())])
                : null;
            $totals = $this->orderService->computeTotals(auth()->id(), $validated['voucher_code'], $items);
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['voucher_code' => $e->getMessage()]);
        }

        return redirect()->back()->with([
            'applied_voucher' => $totals['voucher']->code,
            'voucher_discount' => $totals['voucher_discount'],
            'checkout_total' => $totals['total'],
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->isBuyer() && ! $request->user()->hasCompleteBuyerProfile()) {
            return redirect()->route('profile.complete')->with('error', 'Complete your mobile number and address before placing an order.');
        }
        $validated = $request->validate([
            'address_id' => ['required', 'exists:addresses,id'],
            'payment_method' => ['required', 'string', 'in:cod,gcash,maya,card'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
            'checkout_mode' => ['nullable', 'in:cart,buy_now'],
        ]);

        // Ensure the address belongs to the authenticated user.
        if (! Address::where('id', $validated['address_id'])->where('user_id', auth()->id())->exists()) {
            return back()->with('error', 'Invalid shipping address.');
        }

        try {
            $buyNow = ($validated['checkout_mode'] ?? 'cart') === 'buy_now';
            $items = $buyNow ? collect([$this->buyNowItem(auth()->id())]) : null;
            $result = $this->orderService->placeOrder(auth()->id(), $validated, $items, ! $buyNow);
            if ($buyNow) {
                session()->forget('buy_now');
            }
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        $order = $result['order'];

        $user = auth()->user();

        \App\Services\NotificationService::send(
            $user->id,
            'Order placed successfully',
            "Your order {$order->order_number} has been placed.",
            'order',
            route('orders.show', $order->order_number),
            ['order_number' => $order->order_number],
            'check'
        );

        return redirect()->route('orders.show', $order->order_number)
            ->with('success', 'Order placed successfully!');
    }

    protected function buyNowItem($userId)
    {
        $data = session('buy_now');
        if (! is_array($data)) {
            throw new Exception('Your Buy Now checkout has expired. Please select the product again.');
        }

        return $this->cartService->purchaseItem(
            $userId,
            $data['product_id'] ?? null,
            $data['product_variant_id'] ?? null,
            $data['quantity'] ?? 1
        );
    }
}
