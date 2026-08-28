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
        $items = $this->cartService->items($user->id)->filter->selected;

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        foreach ($items as $item) {
            if ($item->availableStock() <= 0 || $item->quantity > $item->availableStock()) {
                return redirect()->route('cart.index')
                    ->with('error', "{$item->product->name} is out of stock or exceeds available stock.");
            }
        }

        $totals = $this->cartService->selectedSubtotal($user->id);
        $shipping = $this->cartService->shippingFee($totals);

        $addresses = $user->addresses;
        $paymentMethods = PaymentService::availableMethods();

        return view('storefront.checkout.index', compact('items', 'totals', 'shipping', 'addresses', 'paymentMethods'));
    }

    public function applyVoucher(Request $request)
    {
        $validated = $request->validate(['voucher_code' => ['required', 'string', 'max:50']]);

        try {
            $totals = $this->orderService->computeTotals(auth()->id(), $validated['voucher_code']);
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
        $validated = $request->validate([
            'address_id' => ['required', 'exists:addresses,id'],
            'payment_method' => ['required', 'string', 'in:cod,gcash,maya,card'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Ensure the address belongs to the authenticated user.
        if (! Address::where('id', $validated['address_id'])->where('user_id', auth()->id())->exists()) {
            return back()->with('error', 'Invalid shipping address.');
        }

        try {
            $result = $this->orderService->placeOrder(auth()->id(), $validated);
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
}
