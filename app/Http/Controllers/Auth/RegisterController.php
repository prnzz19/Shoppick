<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyerRegistrationRequest;
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

    public function showBuyerRegistrationForm() { return view('auth.register'); }
    public function showSellerRegistrationForm() { return view('auth.register-seller'); }

    public function register(BuyerRegistrationRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $user->assignRole('buyer');
            $user->addresses()->create([
                'full_name' => $data['name'], 'phone' => $data['phone'],
                'address_line' => $data['address_line'], 'barangay' => $data['barangay'],
                'city' => $data['city'], 'province' => $data['province'],
                'postal_code' => $data['postal_code'], 'country' => $data['country'],
                'label' => 'Home', 'is_default' => true,
            ]);
            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->
            with('success', 'Welcome to SHOPPICK! Your account has been created.');
    }

    public function registerSeller(SellerRegistrationRequest $request, SellerRegistrationService $sellerRegistration)
    {
        $data=$request->validated();
        foreach(['logo','banner'] as $file) if($request->hasFile($file))$data[$file]=$request->file($file)->store('stores','public');
        $user=DB::transaction(function()use($data,$sellerRegistration){
            $user=User::create(['name'=>$data['name'],'email'=>strtolower($data['email']),'phone'=>$data['phone'],'password'=>Hash::make($data['password']),'is_active'=>true]);
            $user->assignRole('buyer');
            $user->addresses()->create(['full_name'=>$data['name'],'phone'=>$data['phone'],'address_line'=>$data['address_line'],'barangay'=>$data['barangay'],'city'=>$data['city'],'province'=>$data['province'],'postal_code'=>$data['postal_code'],'country'=>$data['country'],'label'=>'Home','is_default'=>true]);
            $sellerRegistration->submit($user,$data);
            return $user;
        });
        event(new Registered($user));Auth::login($user);$request->session()->regenerate();
        return redirect()->route('seller.apply')->with('success','Your seller application has been submitted for review.');
    }
}
