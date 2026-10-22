<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteBuyerRegistrationRequest;
use App\Http\Requests\SellerRegistrationRequest;
use App\Models\User;
use App\Services\SellerRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register-choice');
    }

    public function showBuyerRegistrationForm()
    {
        $registrationToken = bin2hex(random_bytes(32));
        session()->put('buyer_signup_token', $registrationToken);
        return view('auth.register', compact('registrationToken'));
    }

    public function register(WebsiteBuyerRegistrationRequest $request)
    {
        $data = $request->validated();
        $created=true;

        $retry=$request->retryUser();
        $validIdPath = $retry?->valid_id_path??$request->file('valid_id')->store('registration-documents','local');
        try {
        $user = DB::transaction(function () use ($data, $validIdPath, $request, &$created) {
            if($retry=$request->retryUser()){$created=false;return $retry;}
            $name = trim($data['first_name'].' '.(($data['middle_initial'] ?? null) ? $data['middle_initial'].'. ' : '').$data['last_name']);
            $user = User::create([
                'name' => $name, 'first_name'=>$data['first_name'], 'middle_initial'=>$data['middle_initial']??null,
                'last_name'=>$data['last_name'], 'sex'=>$data['sex'], 'birthday'=>$data['birthday'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'valid_id_path'=>$validIdPath,
                'password' => Hash::make($data['password']),
                'registration_type'=>'buyer','registration_status'=>'unverified','is_active'=>false,'email_verified_at'=>null,
            ]);
            $user->forceFill(['buyer_web_email'=>$data['email'],'buyer_web_phone'=>$data['phone'],'buyer_web_signup_key'=>isset($data['registration_token'])?hash('sha256',$data['registration_token']):null])->save();
            $user->assignRole('buyer');
            $user->addresses()->create([
                'full_name' => $name, 'phone' => $data['phone'],
                'address_line' => $data['address_line'], 'region' => $data['region'] ?? null, 'region_code' => $data['region_code'] ?? null,
                'barangay' => $data['barangay'], 'barangay_code' => $data['barangay_code'] ?? null,
                'city' => $data['city'], 'city_code' => $data['city_code'] ?? null,
                'province' => $data['province'] ?? '', 'province_code' => $data['province_code'] ?? null,
                'postal_code' => $data['postal_code'], 'country' => $data['country'],
                'label' => 'Home', 'is_default' => true,
            ]);
            return $user;
        });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $error) {
            if($retry=$request->retryUser()){$created=false;$user=$retry;if($validIdPath!==$user->valid_id_path)\Illuminate\Support\Facades\Storage::disk('local')->delete($validIdPath);}
            else {\Illuminate\Support\Facades\Storage::disk('local')->delete($validIdPath);throw \Illuminate\Validation\ValidationException::withMessages(['email'=>'An account with those contact details already exists. Please sign in.']);}
        } catch (\Throwable $error) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($validIdPath);
            report($error);
            return back()->withInput($request->except('password','password_confirmation','valid_id'))->withErrors(['email'=>'Unable to create your account. Please try again.']);
        }

        if($created)event(new Registered($user));elseif($validIdPath!==$user->valid_id_path)\Illuminate\Support\Facades\Storage::disk('local')->delete($validIdPath);

        Auth::login($user,false);
        $request->session()->regenerate();
        try {if($created)app(\App\Services\BuyerEmailVerification::class)->send($user);}
        catch (\Throwable $error) {report($error);return redirect()->route('buyer.verification')->withErrors(['code'=>'We could not send your code. Please use Resend code to try again.']);}
        return redirect()->route('buyer.verification')->with('status',$created?'Account created. Check your email for the verification code.':'This account is waiting for email verification. Enter your code or request a new one.');
    }
}
