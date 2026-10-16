<?php
namespace App\Services;

use App\Models\{RiderProfile,User};
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\{DB,Hash,Storage};
use Illuminate\Support\Str;

class RiderWebsiteRegistration
{
    public function register(array $data,array $uploads): User
    {
        $paths=[];
        try {
            foreach(['valid_id'=>'valid-ids','driver_license_front'=>'licenses','driver_license_back'=>'licenses'] as $field=>$folder){
                $file=$uploads[$field];$paths[$field]=$file->storeAs('rider-documents/'.$folder,Str::uuid().'.'.$file->extension(),'local');
                if(!$paths[$field])throw new \RuntimeException('Unable to store a Rider document.');
            }
            return DB::transaction(function()use($data,$paths){
                $name=trim($data['first_name'].' '.(($data['middle_initial']??null)?$data['middle_initial'].'. ':'').$data['last_name']);
                $user=User::create(['name'=>$name,'first_name'=>$data['first_name'],'middle_initial'=>$data['middle_initial']??null,'last_name'=>$data['last_name'],'sex'=>$data['sex'],'birthday'=>$data['birthday'],'email'=>$data['email'],'phone'=>$data['phone'],'password'=>Hash::make($data['password']),'valid_id_path'=>$paths['valid_id'],'registration_type'=>'rider','registration_status'=>'pending','is_active'=>false]);
                // Reuse the existing unique website identity keys; these do not assign any role.
                $user->forceFill(['buyer_web_email'=>$data['email'],'buyer_web_phone'=>$data['phone']])->save();
                $user->assignRole('rider');
                $user->addresses()->create(['full_name'=>$name,'phone'=>$data['phone'],'address_line'=>$data['address_line'],'region'=>$data['region'],'region_code'=>$data['region_code']??null,'province'=>$data['province']??'','province_code'=>$data['province_code']??null,'city'=>$data['city'],'city_code'=>$data['city_code']??null,'barangay'=>$data['barangay'],'barangay_code'=>$data['barangay_code']??null,'postal_code'=>$data['postal_code']??'','country'=>'PH','label'=>'Home','is_default'=>true]);
                $profile=RiderProfile::create(['user_id'=>$user->id,'account_status'=>'inactive','availability'=>'unavailable','vehicle_type'=>$data['preferred_vehicle_type']??null,'emergency_contact_name'=>$data['emergency_contact_name']??null,'emergency_contact_phone'=>$data['emergency_contact_phone']??null,'driver_license_number'=>$data['driver_license_number'],'driver_license_classification'=>$data['driver_license_classification'],'driver_license_expires_at'=>$data['driver_license_expires_at'],'driver_license_front_path'=>$paths['driver_license_front'],'driver_license_back_path'=>$paths['driver_license_back'],'driver_license_status'=>'pending_review']);
                $profile->licenseAudits()->create(['actor_id'=>$user->id,'event'=>'uploaded','status'=>'pending_review','metadata'=>['source'=>'public_application']]);
                User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','logistics'))->each(fn($manager)=>NotificationService::send($manager->id,'New Rider application received',"{$user->name} is waiting for Logistics review.",'rider_application',route('logistics.rider-applications',['status'=>'pending','view'=>$profile->id]),['rider_profile_id'=>$profile->id],'user'));
                event(new Registered($user));
                return $user;
            });
        } catch(\Throwable $error){foreach($paths as $path)if($path)Storage::disk('local')->delete($path);throw $error;}
    }
}
