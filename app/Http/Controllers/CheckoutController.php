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

        $voucherSessionKey='checkout_voucher_codes.'.$checkoutMode;
        $appliedCodes=collect(session($voucherSessionKey,[]))->filter()->values()->all();
        try {$computed = $this->orderService->computeTotals($user->id, $appliedCodes, $items);}
        catch (Exception $e) {
            session()->forget($voucherSessionKey);
            $computed=$this->orderService->computeTotals($user->id,null,$items);$appliedCodes=[];
        }
        $totals = $computed['subtotal'];
        $shipping = $computed['shipping_fee'];

        $addresses = $user->addresses;
        $paymentMethods = PaymentService::availableMethods();
        $storeIds=$items->pluck('product.store_id')->filter()->unique();
        $voucherOptions=\App\Models\Voucher::with(['store','products'])->where(function($q)use($storeIds){$q->where(fn($shop)=>$shop->where('source_type','shop')->whereIn('store_id',$storeIds))->orWhere(fn($platform)=>$platform->where('source_type','platform')->where('platform_scope','all_shops')->whereNull('store_id'));})->where('status','active')->whereNull('archived_at')->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->get()->map(function($voucher)use($user,$items,$appliedCodes){
            try{$quote=$this->orderService->computeTotals($user->id,[$voucher->code],$items);$eligible=true;$message=null;}catch(Exception $e){$quote=null;$eligible=false;$eligibleSubtotal=$voucher->source_type==='platform'?(float)$items->sum(fn($item)=>$item->lineTotal()):(float)$items->where('product.store_id',$voucher->store_id)->sum(fn($item)=>$item->lineTotal());$message=$voucher->min_purchase>$eligibleSubtotal?'Spend ₱'.number_format($voucher->min_purchase-$eligibleSubtotal,2).' more to use this voucher.':$e->getMessage();}
            return ['voucher'=>$voucher,'eligible'=>$eligible,'message'=>$message,'applied'=>in_array($voucher->code,$appliedCodes,true),'quote'=>$quote];
        });

        return view('storefront.checkout.index', compact('items', 'totals', 'shipping', 'addresses', 'paymentMethods', 'checkoutMode','computed','voucherOptions','appliedCodes'));
    }

    public function applyVoucher(Request $request)
    {
        $validated = $request->validate([
            'voucher_id'=>['nullable','integer','exists:vouchers,id','required_without:voucher_code'],
            'voucher_code' => ['nullable', 'string', 'max:50','required_without:voucher_id'],
            'checkout_mode' => ['nullable', 'in:cart,buy_now'],
            'action' => ['nullable','in:apply,remove'],
        ]);

        try {
            $mode=$validated['checkout_mode']??'cart';
            $items = ($validated['checkout_mode'] ?? 'cart') === 'buy_now'
                ? collect([$this->buyNowItem(auth()->id())])
                : null;
            $selected=isset($validated['voucher_id'])?\App\Models\Voucher::findOrFail($validated['voucher_id']):\App\Models\Voucher::where('code',strtoupper($validated['voucher_code']))->first();
            if(!$selected)throw new Exception('This voucher was not found.');
            $sessionKey='checkout_voucher_codes.'.$mode;
            $codes=collect(session($sessionKey,[]))->filter();
            if(($validated['action']??'apply')==='remove')$codes=$codes->reject(fn($code)=>strcasecmp($code,$selected->code)===0);
            else {
                $existing=\App\Models\Voucher::whereIn('code',$codes)->get();
                $codes=$codes->reject(function($code)use($existing,$selected){$voucher=$existing->firstWhere('code',$code);return $voucher&&($selected->source_type==='platform'?$voucher->source_type==='platform':$voucher->source_type==='shop'&&(int)$voucher->store_id===(int)$selected->store_id);});
                $codes->push($selected->code);
            }
            $codes=$codes->unique()->values();
            $totals = $this->orderService->computeTotals(auth()->id(), $codes->all(), $items);
            session()->put($sessionKey,$codes->all());
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['voucher_code' => $e->getMessage()]);
        }

        return redirect()->back()->with('success',($validated['action']??'apply')==='remove'?'Voucher removed.':'Voucher applied successfully.');
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
            'voucher_codes' => ['nullable','array'],
            'voucher_codes.*' => ['string','max:50'],
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
            session()->forget('checkout_voucher_codes.'.($buyNow?'buy_now':'cart'));
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
