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
            $address=$this->addressText($data,(bool)($data['same_address']??false));
            $profile=$user->sellerProfile()->firstOrCreate([], [
                'phone'=>$data['phone'],'address'=>$address,'business_information'=>$data['business_information']??null,'status'=>'pending',
            ]);
            $application=$user->sellerApplications()->create([
                'store_name'=>$data['store_name'],'category_id'=>$data['category_id']??null,'store_description'=>$data['store_description'],'phone'=>$data['phone'],
                'address'=>$address,'business_information'=>$data['business_information']??null,
                'valid_id_path'=>$data['valid_id_path']??$user->valid_id_path,'business_permit_path'=>$data['business_permit_path']??null,
                'logo'=>$data['logo']??null,'banner'=>$data['banner']??null,'status'=>'pending',
            ]);
            $store=Store::firstOrCreate(['user_id'=>$user->id], [
                'seller_profile_id'=>$profile->id,'name'=>$data['store_name'],'slug'=>$this->uniqueSlug($data['store_name']),
                'description'=>$data['store_description'],'logo'=>$data['logo']??null,'banner'=>$data['banner']??null,
                'location'=>$address,'status'=>'pending',
            ]);
            foreach (Role::where('slug','admin')->with(['users','permissions'])->get() as $role) {
                if(!$role->hasPermission('manage_sellers'))continue;
                foreach($role->users as $admin) NotificationService::send($admin->id,'New seller application received.',"{$user->name} submitted {$store->name} for review.",'seller_application',route('admin.sellers.applications.index'),['application_id'=>$application->id],'store');
            }
        });
    }

    protected function addressText(array $data,bool $same): string
    {
        $prefix=$same?'': 'store_';
        return implode(', ',array_filter([$data[$prefix.'address_line']??null,$data[$prefix.'barangay']??null,$data[$prefix.'city']??null,$data[$prefix.'province']??null,$data[$prefix.'postal_code']??null,$data['country']??'PH']));
    }

    protected function uniqueSlug(string $name): string
    {
        $base=Str::slug($name)?:'shop';$slug=$base;$number=2;
        while(Store::withTrashed()->where('slug',$slug)->exists())$slug=$base.'-'.$number++;
        return $slug;
    }
}
