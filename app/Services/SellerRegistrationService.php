<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerRegistrationService
{
    public function submit(User $user, array $data): void
    {
        DB::transaction(function () use ($user,$data) {
            // Serialize by account, including the first application where no row exists yet.
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_active && $user->isBuyer(), 403);
            abort_if($user->isSeller() || $user->sellerProfile()->where('status', 'approved')->exists(), 422, 'You already have a seller profile.');
            $previous = $user->sellerApplications()->latest('id')->first();
            if ($previous && !in_array($previous->status, ['rejected', 'needs_resubmission'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['application' => 'Your application is already under review.']);
            }
            if ($previous) \App\Models\AdminActivityLog::record('seller_application.resubmitted', \App\Models\SellerApplication::class, $previous->id, ['previous_application' => $previous->getAttributes()]);
            $address=$this->addressText($data,(bool)($data['same_address']??false));
            $profile=$user->sellerProfile()->updateOrCreate([], [
                'phone'=>$data['phone'],'address'=>$address,'business_information'=>$data['business_information']??null,'status'=>'pending',
            ]);
            $applicationData = [
                'store_name'=>$data['store_name'],'category_id'=>$data['category_id']??null,'store_description'=>$data['store_description']??null,'phone'=>$data['phone'],
                'address'=>$address,'business_information'=>$data['business_information']??null,
                'seller_type'=>$data['seller_type']??null,'tin'=>$data['tin']??null,
                'region'=>$data['region']??null,'region_code'=>$data['region_code']??null,'province'=>$data['province']??null,'province_code'=>$data['province_code']??null,
                'city'=>$data['city']??null,'city_code'=>$data['city_code']??null,'barangay'=>$data['barangay']??null,'barangay_code'=>$data['barangay_code']??null,'postal_code'=>$data['postal_code']??null,
                'valid_id_path'=>$data['valid_id_path']??$previous?->valid_id_path??$user->valid_id_path,
                'business_permit_path'=>$data['business_permit_path']??$previous?->business_permit_path,
                'dti_certificate_path'=>$data['dti_certificate_path']??$previous?->dti_certificate_path,
                'sec_certificate_path'=>$data['sec_certificate_path']??$previous?->sec_certificate_path,
                'bir_2303_path'=>$data['bir_2303_path']??$previous?->bir_2303_path,
                'logo'=>$data['logo']??$previous?->logo,'banner'=>$data['banner']??$previous?->banner,'status'=>'pending',
                'review_notes'=>null,'reviewed_by'=>null,'reviewed_at'=>null,
            ];
            $application = $previous
                ? tap($previous)->update($applicationData)
                : $user->sellerApplications()->create($applicationData);
            $store=Store::updateOrCreate(['user_id'=>$user->id], [
                'seller_profile_id'=>$profile->id,'name'=>$data['store_name'],'slug'=>$user->store?->slug ?? $this->uniqueSlug($data['store_name']),
                'description'=>$data['store_description']??null,'logo'=>$data['logo']??null,'banner'=>$data['banner']??null,
                'location'=>$address,'status'=>'pending',
            ]);
            NotificationService::send($user->id, 'Seller application submitted', 'Your application is pending Admin review. Your Buyer account stays available.', 'seller_application', route('seller.apply'), ['application_id'=>$application->id], 'store');
            foreach (Role::where('slug','admin')->with(['users','permissions'])->get() as $role) {
                if(!$role->hasPermission('manage_sellers'))continue;
                foreach($role->users as $admin) NotificationService::send($admin->id,'New seller application received.',"{$user->name} submitted {$store->name} for review.",'seller_application',route('admin.sellers.applications.index'),['application_id'=>$application->id],'store');
            }
        });
    }

    protected function addressText(array $data,bool $same): string
    {
        $prefix = $same ? '' : 'store_';
        $line = $data[$prefix.'address_line'] ?? $data['address_line'] ?? $data['address'] ?? null;
        return implode(', ', array_filter([
            $line,
            $data[$prefix.'barangay'] ?? $data['barangay'] ?? null,
            $data[$prefix.'city'] ?? $data['city'] ?? null,
            $data[$prefix.'province'] ?? $data['province'] ?? null,
            $data[$prefix.'region'] ?? $data['region'] ?? null,
            $data[$prefix.'postal_code'] ?? $data['postal_code'] ?? null,
            $data['country'] ?? 'PH',
        ]));
    }

    protected function uniqueSlug(string $name): string
    {
        $base=Str::slug($name)?:'shop';$slug=$base;$number=2;
        while(Store::withTrashed()->where('slug',$slug)->exists())$slug=$base.'-'.$number++;
        return $slug;
    }
}
