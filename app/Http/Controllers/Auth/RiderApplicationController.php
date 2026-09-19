<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{RiderProfile, User};
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\Rules\Password;

class RiderApplicationController extends Controller
{
    public function create() { return view('auth.register-rider'); }

    public function store(Request $request)
    {
        $request->request->remove('age');
        $request->merge(['driver_license_number'=>strtoupper(trim((string)$request->driver_license_number))]);
        $max=config('riders.document_max_kilobytes',5120);
        $data=$request->validate($this->rules($max)+[
            'password'=>['required','confirmed',Password::defaults()],
            'valid_id'=>"required|file|mimes:pdf,jpg,jpeg,png|max:$max",
            'driver_license_front'=>"required|file|mimes:pdf,jpg,jpeg,png|max:$max",
            'driver_license_back'=>"required|file|mimes:pdf,jpg,jpeg,png|max:$max",
            'terms'=>'accepted',
        ]);

        $user=DB::transaction(function()use($request,$data){
            $name=trim($data['first_name'].' '.(($data['middle_initial']??null)?$data['middle_initial'].'. ':'').$data['last_name']);
            $user=User::create(['name'=>$name,'first_name'=>$data['first_name'],'middle_initial'=>$data['middle_initial']??null,'last_name'=>$data['last_name'],'sex'=>$data['sex'],'birthday'=>$data['birthday'],'email'=>strtolower($data['email']),'phone'=>$data['phone'],'valid_id_path'=>$request->file('valid_id')->store('rider-documents/valid-ids','local'),'registration_type'=>'rider','registration_status'=>'pending','password'=>Hash::make($data['password']),'is_active'=>false]);
            $user->assignRole('rider');
            $user->addresses()->create(['full_name'=>$name,'phone'=>$data['phone'],'address_line'=>$data['address_line'],'region'=>$data['region']??null,'region_code'=>$data['region_code']??null,'province'=>$data['province']??'','province_code'=>$data['province_code']??null,'city'=>$data['city'],'city_code'=>$data['city_code']??null,'barangay'=>$data['barangay'],'barangay_code'=>$data['barangay_code']??null,'postal_code'=>$data['postal_code']??'','country'=>'PH','label'=>'Home','is_default'=>true]);
            $profile=RiderProfile::create(['user_id'=>$user->id,'account_status'=>'inactive','availability'=>'unavailable','vehicle_type'=>$data['preferred_vehicle_type']??null,'emergency_contact_name'=>$data['emergency_contact_name']??null,'emergency_contact_phone'=>$data['emergency_contact_phone']??null,'driver_license_number'=>$data['driver_license_number'],'driver_license_classification'=>$data['driver_license_classification'],'driver_license_expires_at'=>$data['driver_license_expires_at'],'driver_license_front_path'=>$request->file('driver_license_front')->store('rider-documents/licenses','local'),'driver_license_back_path'=>$request->file('driver_license_back')->store('rider-documents/licenses','local'),'driver_license_status'=>'pending_review']);
            $profile->licenseAudits()->create(['actor_id'=>$user->id,'event'=>'uploaded','status'=>'pending_review','metadata'=>['source'=>'public_application']]);
            return $user;
        });
        event(new Registered($user));
        User::where('is_active',true)->whereHas('roles',fn($q)=>$q->where('slug','logistics'))->each(fn($manager)=>NotificationService::send($manager->id,'New Rider application received',"{$user->name} is waiting for Logistics review.",'rider_application',route('logistics.rider-applications',['status'=>'pending','view'=>$user->riderProfile->id]),['rider_profile_id'=>$user->riderProfile->id],'user'));
        return redirect()->route('login')->with('success','Your Rider application has been submitted and is waiting for SHOPPICK Logistics review.');
    }

    public function status(Request $request)
    {
        abort_unless($request->user()?->registration_type==='rider'&&$request->user()->hasRole('rider'),403);
        if($request->user()->registration_status==='approved')return redirect()->route('rider.dashboard');
        return view('auth.rider-application-status',['user'=>$request->user()->load('riderProfile')]);
    }

    public function resubmit(Request $request)
    {
        $user=$request->user();
        abort_unless($user?->registration_type==='rider'&&$user->registration_status==='needs_resubmission',403);
        $max=config('riders.document_max_kilobytes',5120);
        $data=$request->validate(['valid_id'=>"nullable|file|mimes:pdf,jpg,jpeg,png|max:$max",'driver_license_number'=>['nullable','string','min:5','max:80','regex:/^[A-Za-z0-9 .\/-]+$/','unique:rider_profiles,driver_license_number,'.$user->riderProfile->id],'driver_license_classification'=>'nullable|string|max:100','driver_license_expires_at'=>'nullable|date','driver_license_front'=>"nullable|file|mimes:pdf,jpg,jpeg,png|max:$max",'driver_license_back'=>"nullable|file|mimes:pdf,jpg,jpeg,png|max:$max"]);
        DB::transaction(function()use($request,$user,$data){$profile=$user->riderProfile;$changes=['driver_license_status'=>'pending_review','driver_license_rejection_reason'=>null,'driver_license_reviewed_by'=>null,'driver_license_reviewed_at'=>null,'availability'=>'unavailable'];foreach(['driver_license_number','driver_license_classification','driver_license_expires_at'] as $key)if(filled($data[$key]??null))$changes[$key]=$key==='driver_license_number'?strtoupper(trim($data[$key])):$data[$key];foreach(['front','back'] as $side)if($request->hasFile('driver_license_'.$side))$changes['driver_license_'.$side.'_path']=$request->file('driver_license_'.$side)->store('rider-documents/licenses','local');if($request->hasFile('valid_id'))$user->update(['valid_id_path'=>$request->file('valid_id')->store('rider-documents/valid-ids','local')]);$profile->update($changes);$user->update(['registration_status'=>'pending','registration_review_notes'=>null,'registration_reviewed_by'=>null,'registration_reviewed_at'=>null]);$profile->licenseAudits()->create(['actor_id'=>$user->id,'event'=>'resubmitted','status'=>'pending_review']);});
        return back()->with('success','Your updated documents were submitted for Logistics review.');
    }

    private function rules(int $max): array
    {
        return ['first_name'=>'required|string|max:100','middle_initial'=>'nullable|string|max:5','last_name'=>'required|string|max:100','sex'=>'required|in:male,female','birthday'=>'required|date|before_or_equal:today','email'=>'required|email|unique:users,email','phone'=>'required|string|max:30','address_line'=>'required|string|max:255','region'=>'required|string|max:100','region_code'=>'nullable|string|max:20','province'=>'nullable|string|max:100','province_code'=>'nullable|string|max:20','city'=>'required|string|max:100','city_code'=>'nullable|string|max:20','barangay'=>'required|string|max:100','barangay_code'=>'nullable|string|max:20','postal_code'=>'nullable|string|max:20','emergency_contact_name'=>'nullable|string|max:100','emergency_contact_phone'=>'nullable|string|max:30','preferred_vehicle_type'=>'nullable|in:motorcycle,scooter,car,van','driver_license_number'=>['required','string','min:5','max:80','regex:/^[A-Za-z0-9 .\/-]+$/','unique:rider_profiles,driver_license_number'],'driver_license_classification'=>'required|string|max:100','driver_license_expires_at'=>'required|date'];
    }
}
