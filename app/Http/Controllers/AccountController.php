<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function profile()
    {
        $user = auth()->user();
        $orders = $user->orders()->with(['items.product.images', 'sellerOrders.store'])->latest()->take(5)->get();
        return view('storefront.account.profile', compact('user', 'orders'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $webBuyer=$user->isNormalBuyerRegistration()&&$user->getAttribute('buyer_web_email')!==null;
        if($webBuyer){
            if(is_string($request->input('email')))$request->merge(['email'=>mb_strtolower(trim($request->input('email')))]);
            if(is_string($request->input('phone')))$request->merge(['phone'=>\App\Support\BuyerIdentity::phone($request->input('phone'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => $webBuyer?['bail','required','string','regex:/^\\+639[0-9]{9}$/D',\App\Support\BuyerIdentity::phoneRule($user->id)]:['nullable', 'string', 'max:20'],
            'email' => $webBuyer?['bail','required','string','email','regex:/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/u','max:255',\App\Support\BuyerIdentity::emailRule($user->id)]:['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($request->hasFile('avatar')) {
            $request->validate(['avatar' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']]);
            $previousAvatar = $user->avatar;
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;

            if ($previousAvatar && str_starts_with($previousAvatar, 'avatars/')) {
                Storage::disk('public')->delete($previousAvatar);
            }
        }

        $changedEmail=$webBuyer&&$data['email']!==$user->email;
        try {
            \Illuminate\Support\Facades\DB::transaction(function()use($user,$data,$webBuyer,$changedEmail){
                $user->update($data);
                if($webBuyer)$user->forceFill(['buyer_web_email'=>$data['email'],'buyer_web_phone'=>$data['phone']])->save();
                if($changedEmail)$user->update(['email_verified_at'=>null,'registration_status'=>'unverified','is_active'=>false]);
            });
        } catch(\Illuminate\Database\UniqueConstraintViolationException $error){return back()->withErrors(['email'=>'An account with those contact details already exists.']);}
        if($changedEmail){
            \Illuminate\Support\Facades\DB::table('buyer_email_verifications')->where('user_id',$user->id)->delete();
            try{app(\App\Services\BuyerEmailVerification::class)->send($user->fresh());}catch(\Throwable $error){report($error);return redirect()->route('buyer.verification')->withErrors(['code'=>'We could not send your code. Please try Resend code.']);}
            return redirect()->route('buyer.verification')->with('status','Verify your new email address to continue.');
        }

        return back()->with('success', $request->hasFile('avatar') ? 'Profile photo updated successfully.' : 'Profile updated.');
    }

    public function changePasswordForm()
    {
        return view('storefront.account.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => auth()->user()->isNormalBuyerRegistration()?\App\Support\BuyerIdentity::passwordRules():['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->input('current_password'), auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        auth()->user()->update(['password' => Hash::make($request->input('password'))]);

        return back()->with('success', 'Password changed successfully.');
    }

}
