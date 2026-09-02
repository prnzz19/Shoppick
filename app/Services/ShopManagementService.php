<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopManagementService
{
    public function __construct(protected SellerShopApprovalService $sellerApprovals) {}
    public const ACTION_PERMISSIONS = [
        'approve'=>'approve_shops', 'reject'=>'reject_shops', 'restrict'=>'restrict_shops',
        'suspend'=>'suspend_shops', 'reactivate'=>'reactivate_shops',
    ];

    public function apply(Store $shop, User $actor, string $action, ?string $reason = null): void
    {
        $permission = self::ACTION_PERMISSIONS[$action] ?? null;
        abort_unless($permission && ($actor->isSuperAdmin() || $actor->hasPermissionTo($permission)), 403);

        $allowed = [
            'approve'=>['pending'], 'reject'=>['pending'], 'restrict'=>['active'],
            'suspend'=>['active','restricted'], 'reactivate'=>['restricted','suspended'],
        ];
        if (! in_array($shop->status, $allowed[$action], true)) {
            throw ValidationException::withMessages(['action'=>'This action is not available for the shop’s current status.']);
        }
        if (in_array($action,['reject','restrict','suspend'],true) && blank($reason)) {
            throw ValidationException::withMessages(['reason'=>'A reason is required for this action.']);
        }
        if (! $actor->isSuperAdmin() && in_array($shop->status,['restricted','suspended'],true) && $shop->statusChangedBy?->isSuperAdmin()) {
            abort(403, 'This shop is restricted by Super Admin and cannot be overridden by an Admin.');
        }

        if(in_array($action,['approve','reject'],true)) {
            $this->sellerApprovals->reviewShop($shop,$actor,$action==='approve'?'approved':'rejected',$reason);
            return;
        }

        $newStatus = match ($action) {
            'reactivate' => 'active',
            'restrict' => 'restricted', 'suspend' => 'suspended',
        };

        DB::transaction(function () use ($shop,$actor,$action,$reason,$newStatus) {
            $previousStatus=$shop->status;
            $shop->update(['status'=>$newStatus,'status_reason'=>$reason,'status_changed_by'=>$actor->id]);
            NotificationService::send(
                $shop->user_id,
                match($action){'restrict'=>'Your SHOPPICK store has been restricted.','suspend'=>'Your SHOPPICK store has been suspended.',default=>'Your SHOPPICK store has been reactivated.'},
                $reason ?: 'You can review the updated store status in Seller Center.',
                'moderation', route('seller.notifications.index'), ['shop_id'=>$shop->id,'status'=>$newStatus], 'store'
            );
            AdminActivityLog::record('shop.'.$action, Store::class, $shop->id, [
                'seller_id'=>$shop->user_id,'previous_status'=>$previousStatus,'new_status'=>$newStatus,'reason'=>$reason,
            ]);
        });
    }

    public function addNote(Store $shop, User $actor, string $note): void
    {
        abort_unless($actor->isSuperAdmin() || $actor->hasPermissionTo('add_shop_notes'), 403);
        $this->appendNote($shop,$actor,$note);
        AdminActivityLog::record('shop.note_added', Store::class, $shop->id, ['seller_id'=>$shop->user_id,'note'=>$note]);
    }

    protected function appendNote(Store $shop, User $actor, string $note): void
    {
        $entry='['.now()->format('Y-m-d H:i').'] '.$actor->name.': '.$note;
        $shop->update(['administrative_notes'=>trim(($shop->administrative_notes ? $shop->administrative_notes."\n" : '').$entry)]);
    }

    public function escalate(Store $shop, User $actor, string $reason): void
    {
        abort_unless($actor->hasRole('admin') && ! $actor->isSuperAdmin() && $actor->hasPermissionTo('review_shops'), 403);
        $this->sellerApprovals->escalateShop($shop, $actor, $reason);
    }
}
