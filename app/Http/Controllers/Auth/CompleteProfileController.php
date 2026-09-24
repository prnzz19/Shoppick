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
        $data=$this->personalData($request);
        DB::transaction(function()use($request,$data){$user=$request->user();$name=$this->name($data);$path=$request->file('valid_id')->store('registration-documents');$user->update(['name'=>$name,'first_name'=>$data['first_name'],'middle_initial'=>$data['middle_initial']??null,'last_name'=>$data['last_name'],'sex'=>$data['sex'],'birthday'=>$data['birthday'],'phone'=>$data['phone'],'valid_id_path'=>$path,'registration_type'=>'buyer','registration_status'=>'pending','is_active'=>false]);$user->addresses()->updateOrCreate(['is_default'=>true],['full_name'=>$name,'phone'=>$data['phone'],'address_line'=>$data['address_line'],'region'=>$data['region']??null,'region_code'=>$data['region_code']??null,'barangay'=>$data['barangay'],'barangay_code'=>$data['barangay_code']??null,'city'=>$data['city'],'city_code'=>$data['city_code']??null,'province'=>$data['province']??'','province_code'=>$data['province_code']??null,'postal_code'=>$data['postal_code'],'country'=>$data['country'],'label'=>'Home']);});
        Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
        return redirect()->route('login')->with('success','Registration submitted. Your Buyer account is waiting for administrator approval.');
    }

    private function personalData(Request $request):array
    {
        // Age is derived from Birthday and is never trusted from the browser.
        $request->request->remove('age');
        $request->merge(['phone'=>$this->normalizePhone($request->input('phone')),'country'=>strtoupper($request->input('country','PH'))]);
        return $request->validate(['first_name'=>['required','string','max:100'],'middle_initial'=>['nullable','string','max:5'],'last_name'=>['required','string','max:100'],'sex'=>['required','in:male,female'],'birthday'=>['required','date','before_or_equal:today'],'valid_id'=>['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],'phone'=>['required','regex:/^\+639\d{9}$/'],'address_line'=>['required','string','max:255'],'region'=>['nullable','string','max:100'],'region_code'=>['nullable','string','max:20'],'barangay'=>['required','string','max:100'],'barangay_code'=>['nullable','string','max:20'],'city'=>['required','string','max:100'],'city_code'=>['nullable','string','max:20'],'province'=>['required_without:region_code','nullable','string','max:100'],'province_code'=>['nullable','string','max:20'],'postal_code'=>['required','string','max:20'],'country'=>['required','string','size:2']], ['birthday.before_or_equal'=>'Birthday cannot be in the future.']);
    }
    private function name(array $data):string{return trim($data['first_name'].' '.(($data['middle_initial']??null)?$data['middle_initial'].'. ':'').$data['last_name']);}
    private function normalizePhone($value):string{$phone=preg_replace('/[^0-9+]/','',(string)$value);if(preg_match('/^09\d{9}$/',$phone))return '+63'.substr($phone,1);if(preg_match('/^639\d{9}$/',$phone))return '+'.$phone;return $phone;}
}
