<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Role;
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
        if ($reviewer->isSuperAdmin()) {
            abort_unless($application->status === 'escalated', 403, 'Super Admin may only decide escalated applications.');
        } else {
            abort_unless($reviewer->hasRole('admin'), 403);
            abort_unless($reviewer->hasPermissionTo($decision === 'approved' ? 'approve_shops' : 'reject_shops'), 403);
            abort_unless($application->status === 'pending', 403, 'Admin may only decide pending applications.');
        }
        if ($decision === 'rejected' && blank($reason)) throw ValidationException::withMessages(['review_notes' => 'A rejection reason is required.']);

        return DB::transaction(function () use ($application, $reviewer, $decision, $reason) {
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $requiredStatus = $reviewer->isSuperAdmin() ? 'escalated' : 'pending';
            if ($application->status !== $requiredStatus) throw ValidationException::withMessages(['status' => 'This application is no longer available for this decision.']);

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

            NotificationService::send($application->user_id,
                $decision === 'approved' ? 'Your seller application has been approved.' : 'Your seller application was rejected.',
                $decision === 'approved' ? "Your SHOPPICK store {$store->name} is active. You can start selling on SHOPPICK." : "Your application for {$store->name} was rejected. Reason: {$reason}",
                'seller_application', $decision === 'approved' ? route('seller.dashboard') : route('seller.apply'),
                ['application_id' => $application->id, 'shop_id' => $store->id, 'decision' => $decision], 'store');
            AdminActivityLog::record($reviewer->isSuperAdmin() ? 'seller_shop.escalated_'.$decision : 'seller_shop.admin_'.$decision,
                Store::class, $store->id, ['seller_id' => $application->user_id, 'application_id' => $application->id,
                    'reviewer_role' => $reviewer->isSuperAdmin() ? 'super_admin' : 'admin', 'reason' => $reason]);
            return $store;
        });
    }

    public function escalate(SellerApplication $application, User $admin, string $reason): Store
    {
        abort_unless($admin->hasRole('admin') && ! $admin->isSuperAdmin() && $admin->hasPermissionTo('review_shops'), 403);
        if (blank($reason)) throw ValidationException::withMessages(['reason' => 'An escalation reason is required.']);

        return DB::transaction(function () use ($application, $admin, $reason) {
            $application = SellerApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') throw ValidationException::withMessages(['status' => 'Only a pending application can be escalated.']);
            $application->update(['status' => 'escalated', 'escalated_by' => $admin->id, 'escalation_reason' => $reason, 'escalated_at' => now()]);
            $store = $this->pendingStore($application);
            foreach (Role::where('slug', 'super_admin')->with('users')->first()?->users ?? [] as $superAdmin) {
                NotificationService::send($superAdmin->id, 'Seller application requires Super Admin review.',
                    "{$store->name} was escalated for further review.", 'seller_application', route('superadmin.shops.show', $store),
                    ['application_id' => $application->id], 'alert');
            }
            NotificationService::send($application->user_id, 'Your application requires additional review.',
                'Your seller application is receiving an additional internal review.', 'seller_application', route('seller.apply'),
                ['application_id' => $application->id], 'store');
            AdminActivityLog::record('seller_shop.admin_escalated', Store::class, $store->id,
                ['seller_id' => $application->user_id, 'application_id' => $application->id, 'reason' => $reason]);
            return $store;
        });
    }

    public function reviewShop(Store $shop, User $reviewer, string $decision, ?string $reason = null): Store
    {
        $status = $reviewer->isSuperAdmin() ? 'escalated' : 'pending';
        $application = $shop->user->sellerApplications()->where('status', $status)->latest()->first();
        if (! $application) throw ValidationException::withMessages(['action' => $reviewer->isSuperAdmin()
            ? 'No escalated seller application is connected to this shop.' : 'No pending seller application is connected to this shop.']);
        return $this->review($application, $reviewer, $decision, $reason);
    }

    public function escalateShop(Store $shop, User $admin, string $reason): Store
    {
        $application = $shop->user->sellerApplications()->where('status', 'pending')->latest()->first();
        if (! $application) throw ValidationException::withMessages(['action' => 'No pending seller application is connected to this shop.']);
        return $this->escalate($application, $admin, $reason);
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
