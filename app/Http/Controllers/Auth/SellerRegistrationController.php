<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRegistrationRequest;
use App\Models\{Category, SellerApplication, User};
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\{Auth, DB, Hash, Schema, Storage};
use Illuminate\Support\Str;

class SellerRegistrationController extends Controller
{
    public function create()
    {
        $categories = Category::active()->where('name', '!=', 'Logistics Demo')->orderBy('name')->get();
        return view('auth.register-seller', compact('categories'));
    }

    public function store(SellerRegistrationRequest $request)
    {
        $data = $request->validated();
        abort_unless(Schema::hasColumn('seller_applications','seller_type') && Schema::hasColumn('seller_applications','tin'), 503, 'Seller registration is temporarily unavailable while the registration schema is prepared.');
        $paths = [];
        $files = ['valid_id'=>'valid-id','business_permit'=>'business-permit','dti_certificate'=>'dti','sec_certificate'=>'sec','bir_2303'=>'bir-2303'];
        try {
            foreach ($files as $field => $folder) {
                if (! $request->hasFile($field)) continue;
                $file = $request->file($field);
                $paths[$field] = $file->storeAs('seller-registration/'.$folder, Str::uuid().'.'.$file->extension(), 'local');
                if (! $paths[$field]) throw new \RuntimeException('Unable to store a required Seller document.');
            }
            $user = DB::transaction(function () use ($data, $paths) {
                $name = trim($data['first_name'].' '.$data['last_name']);
                $user = User::create([
                    'name'=>$name,'first_name'=>$data['first_name'],'last_name'=>$data['last_name'],
                    'email'=>$data['email'],'phone'=>$data['phone'],'password'=>Hash::make($data['password']),
                    'registration_type'=>'seller','registration_status'=>'pending','is_active'=>false,'email_verified_at'=>null,
                ]);
                $user->assignRole('seller');
                $address = implode(', ', array_filter([$data['address_line'],$data['barangay'],$data['city'],$data['province'],$data['region'],$data['postal_code']]));
                $user->sellerApplications()->create([
                    'store_name'=>$data['store_name'],'category_id'=>$data['category_id'],'store_description'=>$data['store_description'],
                    'phone'=>$data['phone'],'address'=>$address,'business_information'=>$data['business_information']??null,
                    'seller_type'=>$data['seller_type'],'tin'=>$data['tin'],'region'=>$data['region'],'region_code'=>$data['region_code']??null,
                    'province'=>$data['province'],'province_code'=>$data['province_code']??null,'city'=>$data['city'],'city_code'=>$data['city_code']??null,
                    'barangay'=>$data['barangay'],'barangay_code'=>$data['barangay_code']??null,'postal_code'=>$data['postal_code'],
                    'valid_id_path'=>$paths['valid_id']??null,'business_permit_path'=>$paths['business_permit']??null,
                    'dti_certificate_path'=>$paths['dti_certificate']??null,'sec_certificate_path'=>$paths['sec_certificate']??null,
                    'bir_2303_path'=>$paths['bir_2303']??null,'status'=>'pending',
                ]);
                return $user;
            });
            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
            try { app(\App\Services\RegistrationVerification::class)->send($user, 'seller'); }
            catch (\Throwable $error) { report($error); return redirect()->route('registration.verification')->withErrors(['code'=>'We could not send your verification code. Please use Resend code to try again.']); }
        } catch (\Throwable $error) {
            foreach ($paths as $path) Storage::disk('local')->delete($path);
            report($error);
            return back()->withInput($request->except(array_merge(['password','password_confirmation'], array_keys($files))))->withErrors(['email'=>'We could not submit your Seller application. Please check the details and try again.']);
        }
        return redirect()->route('registration.verification')->with('status','Check your email for the verification code. Your Seller application remains pending Admin approval.');
    }
}
