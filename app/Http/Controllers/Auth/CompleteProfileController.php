<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SellerRegistrationService;

class CompleteProfileController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->hasCompleteBuyerProfile()) return redirect()->route('home');
        return view('auth.complete-profile');
    }

    public function update(Request $request)
    {
        $request->merge(['phone' => $this->normalizePhone($request->input('phone')), 'country' => strtoupper($request->input('country', 'PH'))]);
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+639\d{9}$/'], 'address_line' => ['required','string','max:255'],
            'barangay' => ['required','string','max:100'], 'city' => ['required','string','max:100'],
            'province' => ['required','string','max:100'], 'postal_code' => ['required','string','max:20'],
            'country' => ['required','string','size:2'],
        ], ['phone.required'=>'Mobile number is required.','phone.regex'=>'Enter a valid Philippine mobile number.','address_line.required'=>'Address is required.']);

        DB::transaction(function () use ($request, $data) {
            $user = $request->user();
            $user->update(['phone' => $data['phone']]);
            $user->addresses()->create([
                'full_name'=>$user->name,'phone'=>$data['phone'],'address_line'=>$data['address_line'],
                'barangay'=>$data['barangay'],'city'=>$data['city'],'province'=>$data['province'],
                'postal_code'=>$data['postal_code'],'country'=>$data['country'],'label'=>'Home','is_default'=>true,
            ]);
        });

        return redirect()->route('home')->with('success', 'Your SHOPPICK profile is complete.');
    }

    public function showSeller(Request $request)
    {
        if($request->user()->isSeller())return redirect()->route('seller.dashboard');
        if($request->user()->sellerApplications()->where('status','pending')->exists())return redirect()->route('seller.apply');
        return view('auth.complete-seller-profile',['address'=>$request->user()->defaultAddress()]);
    }

    public function updateSeller(Request $request, SellerRegistrationService $sellerRegistration)
    {
        $request->merge(['phone'=>$this->normalizePhone($request->input('phone')),'country'=>strtoupper($request->input('country','PH'))]);
        $data=$request->validate([
            'phone'=>['required','regex:/^\+639\d{9}$/'],'address_line'=>['required','string','max:255'],'barangay'=>['required','string','max:100'],'city'=>['required','string','max:100'],'province'=>['required','string','max:100'],'postal_code'=>['required','string','max:20'],'country'=>['required','string','size:2'],
            'store_name'=>['required','string','max:120'],'store_description'=>['required','string','max:2000'],'business_information'=>['nullable','string','max:2000'],'same_address'=>['nullable','boolean'],'store_address_line'=>['required_unless:same_address,1','nullable','string','max:255'],'store_barangay'=>['required_unless:same_address,1','nullable','string','max:100'],'store_city'=>['required_unless:same_address,1','nullable','string','max:100'],'store_province'=>['required_unless:same_address,1','nullable','string','max:100'],'store_postal_code'=>['required_unless:same_address,1','nullable','string','max:20'],'logo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],'seller_terms'=>['accepted'],
        ]);
        foreach(['logo','banner'] as $file)if($request->hasFile($file))$data[$file]=$request->file($file)->store('stores','public');
        DB::transaction(function()use($request,$data,$sellerRegistration){$user=$request->user();$user->update(['phone'=>$data['phone']]);$user->addresses()->updateOrCreate(['is_default'=>true],['full_name'=>$user->name,'phone'=>$data['phone'],'address_line'=>$data['address_line'],'barangay'=>$data['barangay'],'city'=>$data['city'],'province'=>$data['province'],'postal_code'=>$data['postal_code'],'country'=>$data['country'],'label'=>'Home']);$sellerRegistration->submit($user,$data);});
        return redirect()->route('seller.apply')->with('success','Your seller application has been submitted for review.');
    }

    private function normalizePhone($value): string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $value);
        if (preg_match('/^09\d{9}$/', $phone)) return '+63'.substr($phone, 1);
        if (preg_match('/^639\d{9}$/', $phone)) return '+'.$phone;
        return $phone;
    }
}
