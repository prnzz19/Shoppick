<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\SellerRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompleteProfileController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->registration_status !== 'incomplete' && $request->user()->hasCompleteBuyerProfile()) return redirect()->route('home');
        return view('auth.complete-profile');
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->is_active && ! $request->user()->trashed()
            && $request->user()->isNormalBuyerRegistration()
            && in_array($request->user()->registration_status, ['incomplete', 'approved'], true), 403);
        $data=app(\App\Services\PhilippineGeographyService::class)->normalizeAddress($this->personalData($request));
        DB::transaction(function()use($request,$data){$user=$request->user();$name=$this->name($data);$path=$request->file('valid_id')->store('registration-documents');$user->update(['name'=>$name,'first_name'=>$data['first_name'],'middle_initial'=>$data['middle_initial']??null,'last_name'=>$data['last_name'],'sex'=>$data['sex'],'birthday'=>$data['birthday'],'phone'=>$data['phone'],'valid_id_path'=>$path]+\App\Services\BuyerAccountState::attributes());$user->forceFill(['buyer_web_email'=>mb_strtolower(trim($user->email)),'buyer_web_phone'=>$data['phone']])->save();$user->addresses()->updateOrCreate(['is_default'=>true],['full_name'=>$name,'phone'=>$data['phone'],'address_line'=>$data['address_line'],'region'=>$data['region']??null,'region_code'=>$data['region_code']??null,'barangay'=>$data['barangay'],'barangay_code'=>$data['barangay_code']??null,'city'=>$data['city'],'city_code'=>$data['city_code']??null,'province'=>$data['province']??'','province_code'=>$data['province_code']??null,'postal_code'=>$data['postal_code'],'country'=>$data['country'],'label'=>'Home']);});
        $request->session()->regenerate();$request->session()->forget('url.intended');
        return redirect()->route('home')->with('success','Profile completed. Your Buyer account is ready.');
    }

    private function personalData(Request $request):array
    {
        // Age is derived from Birthday and is never trusted from the browser.
        $request->request->remove('age');
        $request->merge(['phone'=>$this->normalizePhone($request->input('phone')),'country'=>strtoupper($request->input('country','PH'))]);
        return $request->validate(['terms'=>['accepted'],'first_name'=>\App\Support\BuyerIdentity::nameRules(),'middle_initial'=>['nullable','string','max:5'],'last_name'=>\App\Support\BuyerIdentity::nameRules(),'sex'=>['required','in:male,female'],'birthday'=>['required','date_format:Y-m-d','before_or_equal:'.\App\Support\BuyerIdentity::birthdayCutoff()],'valid_id'=>['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],'phone'=>['required','regex:/^\+639\d{9}$/',\App\Support\BuyerIdentity::phoneRule($request->user()->id)],'address_line'=>['required','string','max:255'],'region'=>['nullable','string','max:100'],'region_code'=>['nullable','string','max:20'],'barangay'=>['required','string','max:100'],'barangay_code'=>['nullable','string','max:20'],'city'=>['required','string','max:100'],'city_code'=>['nullable','string','max:20'],'province'=>['required_without:region_code','nullable','string','max:100'],'province_code'=>['nullable','string','max:20'],'postal_code'=>['required','string','regex:/^[0-9]{4}$/D'],'country'=>['required','string','size:2']], ['birthday.before_or_equal'=>'You must be at least 18 years old.','terms.accepted'=>'You must accept the Terms and Privacy Policy.']);
    }
    private function name(array $data):string{return trim($data['first_name'].' '.(($data['middle_initial']??null)?$data['middle_initial'].'. ':'').$data['last_name']);}
    private function normalizePhone($value):string{return is_string($value)?\App\Support\BuyerIdentity::phone($value):'';}
}
