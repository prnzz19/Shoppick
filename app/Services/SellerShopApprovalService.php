<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerShopApprovalService
{
    public function review(SellerApplication $application, User $reviewer, string $decision, ?string $reason = null): Store
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) throw ValidationException::withMessages(['status' => 'Invalid review decision.']);
        abort_unless($reviewer->hasRole('admin'), 403);
        abort_unless($reviewer->hasPermissionTo($decision === 'approved' ? 'approve_shops' : 'reject_shops'), 403);
        abort_unless(in_array($application->status, ['pending', 'escalated', 'awaiting_final_review'], true), 403,
            'Admin may only decide applications awaiting review.');
        if ($decision === 'rejected' && blank($reason)) throw ValidationException::withMessages(['review_notes' => 'A rejection reason is required.']);

        return DB::transaction(function () use ($application, $reviewer, $decision, $reason) {
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if (! in_array($application->status, ['pending', 'escalated', 'awaiting_final_review'], true)) {
                throw ValidationException::withMessages(['status' => 'This application is no longer available for this decision.']);
            }

            $application->update(['status' => $decision, 'review_notes' => $reason, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);
            $profile = SellerProfile::updateOrCreate(['user_id' => $application->user_id], [
                'phone' => $application->phone, 'address' => $application->address,
                'business_information' => $application->business_information,
                'status' => $decision === 'approved' ? 'approved' : 'pending',
                'approved_at' => $decision === 'approved' ? now() : null,
            ]);
            $existing = Store::where('user_id', $application->user_id)->first();
            $store = Store::updateOrCreate(['user_id' => $application->user_id], [
                'seller_profile_id' => $profile->id, 'name' => $application->store_name,
                'slug' => $existing?->slug ?? $this->uniqueSlug($application->store_name),
                'description' => $application->store_description, 'logo' => $application->logo,
                'banner' => $application->banner, 'location' => $application->address,
                'status' => $decision === 'approved' ? 'active' : 'rejected',
                'status_reason' => $decision === 'rejected' ? $reason : null, 'status_changed_by' => $reviewer->id,
            ]);
            if ($decision === 'approved') $application->user->assignRole('seller');
            else $application->user->removeRole('seller');
            if ($application->user->registration_type === 'seller' && $application->user->registration_status === 'pending') {
                $application->user->update([
                    'registration_status'=>$decision, 'is_active'=>$decision==='approved',
                    'registration_review_notes'=>$reason, 'registration_reviewed_by'=>$reviewer->id,
                    'registration_reviewed_at'=>now(),
                ]);
            }

            NotificationService::registrationDecision($application->user,
                $decision === 'approved' ? 'Your seller application has been approved.' : 'Your seller application was rejected.',
                $decision === 'approved' ? "Your SHOPPICK store {$store->name} is active. You may now log in." : "Reason: {$reason}",
                $application->user->registration_type==='seller' ? route('login') : ($decision==='approved'?route('seller.dashboard'):route('seller.apply')),
                ['application_id'=>$application->id,'decision'=>$decision]);
            AdminActivityLog::record('seller_shop.admin_'.$decision,
                Store::class, $store->id, ['seller_id' => $application->user_id, 'application_id' => $application->id,
                    'reviewer_role' => 'admin', 'reason' => $reason]);
            return $store;
        });
    }

    public function reviewShop(Store $shop, User $reviewer, string $decision, ?string $reason = null): Store
    {
        $application = $shop->user->sellerApplications()
            ->whereIn('status', ['pending', 'escalated', 'awaiting_final_review'])->latest()->first();
        if (! $application) throw ValidationException::withMessages(['action' => 'No pending seller application is connected to this shop.']);
        return $this->review($application, $reviewer, $decision, $reason);
    }

    protected function pendingStore(SellerApplication $application): Store
    {
        $profile = SellerProfile::updateOrCreate(['user_id' => $application->user_id], [
            'phone' => $application->phone, 'address' => $application->address,
            'business_information' => $application->business_information, 'status' => 'pending', 'approved_at' => null,
        ]);
        $existing = Store::where('user_id', $application->user_id)->first();
        return Store::updateOrCreate(['user_id' => $application->user_id], [
            'seller_profile_id' => $profile->id, 'name' => $application->store_name,
            'slug' => $existing?->slug ?? $this->uniqueSlug($application->store_name),
            'description' => $application->store_description, 'logo' => $application->logo,
            'banner' => $application->banner, 'location' => $application->address,
            'status' => 'pending', 'status_reason' => null,
        ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop'; $slug = $base; $number = 2;
        while (Store::withTrashed()->where('slug', $slug)->exists()) $slug = $base.'-'.$number++;
        return $slug;
    }
}
