<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BuyerStatusReconciliation
{
    public function candidates(): Builder
    {
        return User::withTrashed()->where('registration_type', 'buyer')
            ->where('registration_status', 'pending')->with(['roles', 'sellerProfile', 'store']);
    }

    /** Be conservative: ambiguous historical records require an individual review. */
    public function exclusion(User $user): ?string
    {
        if ($user->trashed() || $user->getAttribute('archived_at') !== null) {
            return 'Archived or deleted account';
        }
        if ($user->is_active) {
            return 'Already active; no activation needed';
        }
        if ($user->registration_type !== 'buyer' || $user->registration_status !== 'pending') {
            return 'Not a pending normal Buyer registration';
        }
        $roles = $user->roles()->pluck('slug')->all();
        if ($roles !== ['buyer']) {
            return 'Not an exclusively Buyer account';
        }
        if (filled($user->registration_review_notes) || $user->registration_reviewed_by || $user->registration_reviewed_at) {
            return 'Existing review/restriction metadata';
        }
        if (! str_starts_with((string) $user->valid_id_path, 'registration-documents/')
            || blank($user->first_name) || blank($user->last_name) || ! $user->birthday || ! $user->hasCompleteBuyerProfile()) {
            return 'Normal completed-registration evidence is missing';
        }
        if ($user->riderProfile()->exists()) {
            return 'Rider workflow exists';
        }
        foreach ([$user->sellerProfile, $user->store] as $record) {
            if ($record && ($record->getAttribute('archived_at') || in_array($record->status, ['suspended', 'disabled', 'archived'], true))) {
                return 'Seller/shop restriction exists';
            }
        }
        if (AdminActivityLog::whereIn('target_type', ['user', User::class])->where('target_id', $user->id)->exists()) {
            return 'Administrator intervention exists; review individually';
        }
        if (! $user->created_at || ! $user->updated_at || ! $user->created_at->equalTo($user->updated_at)) {
            return 'Account changed since registration; review individually';
        }

        return null;
    }

    public function apply(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $user = User::withTrashed()->lockForUpdate()->find($id);
            if (! $user || $this->exclusion($user) !== null) {
                return false;
            }
            $user->update(BuyerAccountState::attributes());
            AdminActivityLog::record('buyer.registration_status_reconciled', User::class, $user->id, [
                'previous_status' => 'pending', 'previous_is_active' => false,
                'reason' => 'Completed normal Buyer registration has no review or restriction evidence.',
            ]);

            return true;
        });
    }
}
