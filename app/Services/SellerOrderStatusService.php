<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerOrderStatusService
{
    public function __construct(protected InventoryService $inventory)
    {
    }

    public function transition(SellerOrder $sellerOrder, User $actor, string $newStatus, ?string $note = null): void
    {
        $allowed = SellerOrder::SELLER_TRANSITIONS[$sellerOrder->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'That status transition is not allowed.']);
        }

        if ($newStatus === 'cancelled' && blank($note)) {
            throw ValidationException::withMessages(['note' => 'A cancellation reason is required.']);
        }

        DB::transaction(function () use ($sellerOrder, $actor, $newStatus, $note) {
            $sellerOrder = SellerOrder::whereKey($sellerOrder->id)->lockForUpdate()->firstOrFail();
            $previousStatus = $sellerOrder->status;

            if (! in_array($newStatus, SellerOrder::SELLER_TRANSITIONS[$previousStatus] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'That status transition is not allowed.']);
            }

            if ($newStatus === 'cancelled') {
                $this->inventory->restoreItems($sellerOrder->items);
            }

            $sellerOrder->update([
                'status' => $newStatus,
                'cancelled_at' => $newStatus === 'cancelled' ? now() : $sellerOrder->cancelled_at,
                'cancellation_reason' => $newStatus === 'cancelled' ? $note : $sellerOrder->cancellation_reason,
            ]);

            $sellerOrder->histories()->create([
                'order_id' => $sellerOrder->order_id,
                'changed_by' => $actor->id,
                'status' => $newStatus,
                'note' => $note ?: ucfirst(str_replace('_', ' ', $previousStatus)).' to '.ucfirst(str_replace('_', ' ', $newStatus)),
            ]);

            $this->syncMainOrder($sellerOrder);

            if ($newStatus === 'ready_to_ship') {
                app(ShipmentService::class)->createForSellerOrder($sellerOrder, $actor);
            }

            if ($newStatus === 'cancelled') {
                NotificationService::send($sellerOrder->order->user_id,'Your order was cancelled.','Reason: '.$note,'order',
                    route('orders.show',$sellerOrder->order->order_number),['order_number'=>$sellerOrder->order->order_number,'seller_order_id'=>$sellerOrder->id],'package');
            } else {
                app(BuyerOrderNotificationService::class)->send($sellerOrder->order,$newStatus,$sellerOrder);
            }

            AdminActivityLog::record('seller_order.'.$newStatus, SellerOrder::class, $sellerOrder->id, [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'note' => $note,
            ]);
        });
    }

    protected function syncMainOrder(SellerOrder $sellerOrder): void
    {
        app(OrderProgressService::class)->syncOrder($sellerOrder->order);
    }
}
