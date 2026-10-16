<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        if(is_string($request->input('email')))$request->merge(['email'=>mb_strtolower(trim($request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');
        $key='website-login:'.hash('sha256',$credentials['email'].'|'.$request->ip());
        if(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key,5))throw ValidationException::withMessages(['email'=>'Too many sign-in attempts. Please try again shortly.']);

        if (! Auth::attempt($credentials, $remember)) {
            \Illuminate\Support\Facades\RateLimiter::hit($key,60);
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $user = Auth::user();
        \Illuminate\Support\Facades\RateLimiter::clear($key);
        if (! $user->trashed() && \App\Support\BuyerIdentity::needsVerification($user)) {
            Auth::logout();Auth::login($user,false);
            $request->session()->regenerate();
            $request->session()->put('buyer_verification_remember',$remember);
            return redirect()->route('buyer.verification');
        }

        if ($user->trashed()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        if ($user->registration_type === 'rider' && in_array($user->registration_status, ['pending','rejected','needs_resubmission'], true)) {
            $request->session()->regenerate();
            return redirect()->route('rider.application.status');
        }

        if (in_array($user->registration_status, ['pending', 'rejected'], true)
            && ! ($user->isNormalBuyerRegistration() && $user->registration_status === 'pending')) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => $user->registration_status === 'pending'
                    ? 'Your account is still waiting for administrator approval.'
                    : 'Your registration was not approved. Reason: '.($user->registration_review_notes ?: 'Please contact SHOPPICK support.'),
            ]);
        }

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact support.',
            ]);
        }

        $request->session()->regenerate();

        return $this->redirectTo($user);
    }

    protected function redirectTo($user)
    {
        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->hasRole('logistics')) return redirect()->route('logistics.dashboard');
        if ($user->hasRole('rider')) return redirect()->route('rider.dashboard');
        if ($user->hasApprovedSellerAccess()) {
            return redirect()->route('seller.dashboard');
        }
        if ($user->sellerApplications()->whereIn('status', ['pending','escalated','awaiting_final_review','needs_resubmission','rejected'])->exists()) {
            return redirect()->route('seller.apply');
        }
        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
