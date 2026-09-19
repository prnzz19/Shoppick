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

    public function computeTotals($userId, $voucherCode = null, $items = null, bool $lockVouchers = false): array
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

        $shippingFee = $this->cartService->shippingFee($subtotal);
        $groups = $selectedItems->groupBy(fn($item)=>(int)($item->product->store_id ?: 0));
        $breakdown = $groups->map(function($items,$storeId)use($subtotal,$shippingFee){
            $shopSubtotal=(float)$items->sum(fn($item)=>$item->lineTotal());
            return ['store_id'=>(int)$storeId,'subtotal'=>$shopSubtotal,'shipping_fee'=>round($shippingFee*($shopSubtotal/max($subtotal,.01)),2),'voucher_discount'=>0.0,'shipping_discount'=>0.0,'platform_shipping_discount'=>0.0];
        })->all();
        $codes=collect(is_array($voucherCode)?$voucherCode:[$voucherCode])->filter()->map(fn($code)=>strtoupper(trim((string)$code)))->unique()->values();
        $applied=[];$usedSlots=[];$voucherDiscount=0.0;$shippingDiscount=0.0;$shopDiscount=0.0;$platformDiscount=0.0;$platformShippingDiscount=0.0;
        foreach($codes as $code){
            $query=Voucher::with(['products','store'])->where('code',$code);
            if($lockVouchers)$query->lockForUpdate();
            $voucher=$query->first();
            if(!$voucher)throw new Exception("Voucher {$code} was not found.");
            if(!in_array($voucher->source_type,['shop','platform'],true))throw new Exception("Voucher {$code} has an invalid source.");
            if($voucher->source_type==='shop'&&!$voucher->store_id)throw new Exception("Voucher {$code} has no Shop owner.");
            if($voucher->source_type==='platform'&&$voucher->platform_scope!=='all_shops')throw new Exception("Voucher {$code} is not available to all Shops.");
            $storeId=(int)($voucher->store_id?:0);
            if($voucher->store_id && (!$groups->has($storeId)||$voucher->store?->status!=='active'))throw new Exception("Voucher {$code} does not apply to this checkout.");
            $scopeItems=$voucher->store_id?$groups->get($storeId,collect()):$selectedItems;
            if($voucher->application_scope==='selected_products'){
                $productIds=$voucher->products->pluck('id');
                $scopeItems=$scopeItems->whereIn('product_id',$productIds);
            }
            $eligibleSubtotal=(float)$scopeItems->sum(fn($item)=>$item->lineTotal());
            if(!$scopeItems->count())throw new Exception("Voucher {$code} does not apply to the selected products.");
            if(!$voucher->isValidFor($userId,$eligibleSubtotal))throw new Exception("Voucher {$code} is not eligible for this checkout.");
            $kind=$voucher->type==='free_shipping'?'shipping':'product';
            $slot=$voucher->source_type==='platform'?'platform':'shop:'.$storeId;
            if(isset($usedSlots[$slot]))throw new Exception($voucher->source_type==='platform'?'Only one Platform Voucher may be used per Order.':'Only one Shop Voucher may be used per Shop.');
            $usedSlots[$slot]=true;
            $discount=0.0;$shipDiscount=0.0;
            if($kind==='shipping'){
                $eligibleShipping=$voucher->store_id?($breakdown[$storeId]['shipping_fee']??0):$shippingFee;
                $cap=$voucher->max_discount!==null?(float)$voucher->max_discount:$eligibleShipping;
                $shipDiscount=round(min($eligibleShipping,$cap),2);
                $shippingDiscount+=$shipDiscount;
                if($voucher->source_type==='shop')$breakdown[$storeId]['shipping_discount']+=$shipDiscount;
                else {
                    $platformShippingDiscount+=$shipDiscount;$remaining=$shipDiscount;$keys=array_keys($breakdown);
                    foreach($keys as $index=>$key){$share=$index===array_key_last($keys)?$remaining:round($shipDiscount*(($breakdown[$key]['shipping_fee']??0)/max($shippingFee,.01)),2);$share=min($share,$breakdown[$key]['shipping_fee']);$breakdown[$key]['platform_shipping_discount']+=$share;$remaining=round($remaining-$share,2);}
                }
            }else{
                $discount=$voucher->computeDiscount($eligibleSubtotal);
                $voucherDiscount+=$discount;
                if($voucher->source_type==='shop'){$shopDiscount+=$discount;$breakdown[$storeId]['voucher_discount']+=$discount;}else $platformDiscount+=$discount;
            }
            $applied[]=['voucher'=>$voucher,'store_id'=>$voucher->store_id,'source_type'=>$voucher->source_type,'discount'=>$discount,'shipping_discount'=>$shipDiscount];
        }
        $total = $subtotal - $voucherDiscount + $shippingFee - $shippingDiscount;

        return [
            'subtotal' => round($subtotal, 2),
            'shipping_fee' => round($shippingFee, 2),
            'voucher_discount' => round($voucherDiscount, 2),
            'shipping_discount' => round($shippingDiscount, 2),
            'shop_discount'=>round($shopDiscount,2),'platform_discount'=>round($platformDiscount,2),'platform_shipping_discount'=>round($platformShippingDiscount,2),
            'total' => round(max($total, 0), 2),
            'voucher' => data_get($applied,'0.voucher'),
            'vouchers' => $applied,
            'seller_breakdown' => $breakdown,
        ];
    }

    public function placeOrder($userId, array $data, $items = null, bool $clearCart = true): array
    {
        $selectedItems = $items ?? $this->cartService->items($userId)->filter->selected;

        $address = \App\Models\Address::where('user_id', $userId)->findOrFail($data['address_id']);
        $paymentMethod = $data['payment_method'];
        $payment = PaymentService::driver($paymentMethod);

        $order = DB::transaction(function () use (
            $userId, $data, $address, $paymentMethod, $payment, $selectedItems, $clearCart
        ) {
            $codes=$data['voucher_codes']??($data['voucher_code']??null);
            $totals = $this->computeTotals($userId, $codes, $selectedItems, true);
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
                'shipping_discount' => $totals['shipping_discount'],
                'shop_discount'=>$totals['shop_discount'],'platform_discount'=>$totals['platform_discount'],'platform_shipping_discount'=>$totals['platform_shipping_discount'],
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
            $sellerOrders=[];
            foreach ($groups as $storeId => $items) {
                $groupSubtotal = $items->sum(fn ($item) => $item->lineTotal());
                $groupShipping = round($totals['shipping_fee'] * ($groupSubtotal / max($totals['subtotal'], 0.01)), 2);
                $groupTotals=$totals['seller_breakdown'][(int)$storeId]??['voucher_discount'=>0,'shipping_discount'=>0,'platform_shipping_discount'=>0];
                $commissionAmount = round($groupSubtotal * $rate / 100, 2);
                $sellerOrder = $order->sellerOrders()->create([
                    'store_id' => $storeId ?: null,
                    'seller_order_number' => $order->order_number.'-S'.($order->sellerOrders()->count() + 1),
                    'status' => $order->status,
                    'subtotal' => $groupSubtotal,
                    'shipping_fee' => $groupShipping,
                    'voucher_discount' => $groupTotals['voucher_discount'],
                    'shipping_discount' => $groupTotals['shipping_discount'],
                    'platform_shipping_discount'=>$groupTotals['platform_shipping_discount'],
                    'commission_rate' => $rate,
                    'commission_amount' => $commissionAmount,
                    'seller_total' => max(0,$groupSubtotal - $groupTotals['voucher_discount'] - $groupTotals['shipping_discount'] - $commissionAmount),
                ]);
                $sellerOrders[(int)$storeId]=$sellerOrder;
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

            // Record immutable voucher usage only after the order and seller partitions exist.
            foreach($totals['vouchers'] as $applied){
                $voucher=$applied['voucher'];
                $voucher->increment('used_count');
                $voucher->usages()->create([
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'seller_order_id'=>$applied['store_id']?($sellerOrders[(int)$applied['store_id']]->id??null):null,
                    'discount_amount'=>$applied['discount'],
                    'shipping_discount_amount'=>$applied['shipping_discount'],
                    'voucher_code'=>$voucher->code,
                    'voucher_title'=>$voucher->title,
                    'source_type'=>$voucher->source_type,
                    'funding_source'=>$voucher->source_type==='platform'?'platform':'seller',
                    'used_at'=>now(),
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
            foreach($order->voucherUsages()->with('voucher')->get() as $usage){
                if($usage->voucher && $usage->voucher->used_count>0)$usage->voucher->decrement('used_count');
                $usage->delete();
            }
        });
    }
}
