<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RiderApplicationController extends Controller
{
    public function create() { return view('auth.register-rider', ['providers' => \App\Models\LogisticsProvider::approvedActive()->with(['hubs' => fn($q) => $q->where('status','active')->orderBy('name')])->orderBy('company_name')->get()]); }

    public function store(\App\Http\Requests\RiderWebsiteRegistrationRequest $request, \App\Services\RiderWebsiteRegistration $service)
    {
        try {
            $data=app(\App\Services\PhilippineGeographyService::class)->normalizeAddress($request->validated());
            $user=$service->register($data,$request->only(['profile_photo','valid_id','proof_of_address','driver_license_front','driver_license_back','vehicle_registration_document']));
        }
        catch(\Illuminate\Validation\ValidationException $error){throw $error;}
        catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){return back()->withInput($request->except('password','password_confirmation','valid_id','driver_license_front','driver_license_back'))->withErrors(['region'=>'We could not verify your address right now. Please try again.']);}
        catch(\Illuminate\Database\UniqueConstraintViolationException $error){throw \Illuminate\Validation\ValidationException::withMessages(['email'=>'An account or Rider application already uses these details. Please sign in or contact SHOPPICK support.']);}
        catch(\Throwable $error){report($error);return back()->withInput($request->except('password','password_confirmation','valid_id','driver_license_front','driver_license_back'))->withErrors(['email'=>'We could not submit your Rider registration. Please try again.']);}
        Auth::login($user);
        try { app(\App\Services\RegistrationVerification::class)->send($user, 'rider'); }
        catch (\Throwable $error) { report($error); return redirect()->route('registration.verification')->withErrors(['code'=>'We could not send your verification code. Please use Resend code to try again.']); }
        $request->session()->put('rider_registration_submitted',true);
        return redirect()->route('registration.verification')->with('status','Check your email for the verification code. Your Rider application remains pending provider approval.');
    }

    public function submitted(Request $request)
    {
        if(!$request->session()->get('rider_registration_submitted'))return redirect()->route('rider.register');
        return view('rider.registration-submitted');
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

}
