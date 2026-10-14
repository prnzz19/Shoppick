<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Support\RiderPortal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Validation\ValidationException;

class RiderLoginController extends Controller
{
    public function create() { return view('rider.login'); }

    public function store(Request $request)
    {
        if(is_string($request->input('email')))$request->merge(['email'=>mb_strtolower(trim($request->input('email')))]);
        $credentials = $request->validate(['email' => 'required|string|email|max:255', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
        $pendingRider = User::whereRaw('LOWER(email) = ?', [mb_strtolower($credentials['email'])])->whereHas('roles', fn($q) => $q->where('slug', 'rider'))->whereHas('riderProfile', fn($q) => $q->where('account_status', 'pending_activation'))->first();
        if ($pendingRider && ! $pendingRider->is_active) {
            throw ValidationException::withMessages(['email' => 'Your Rider account is pending activation. Open the activation link sent to your email before logging in.']);
        }
        $key = 'rider-login:'.hash('sha256', mb_strtolower($credentials['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        $guard = Auth::guard('web');
        if (!$guard->validate(collect($credentials)->only(['email', 'password'])->all())) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Unable to sign in with those credentials.']);
        }
        $user = $guard->getLastAttempted();
        if (RiderPortal::hasRole($user) && $user->riderProfile?->account_status === 'pending_activation' && ! $user->is_active) {
            RateLimiter::clear($key);
            throw ValidationException::withMessages(['email' => 'Your Rider account is pending activation. Open the activation link sent to your email before logging in.']);
        }
        if(RiderPortal::awaitingReview($user)){
            RateLimiter::clear($key);$guard->login($user,false);$request->session()->regenerate();$request->session()->forget('url.intended');
            return redirect()->route('rider.application.status');
        }
        if (!RiderPortal::hasRole($user) || !RiderPortal::active($user)) {
            RateLimiter::hit($key, 60);
            if(RiderPortal::hasRole($user))return redirect()->route('rider.access-unavailable');
            throw ValidationException::withMessages(['email' => !RiderPortal::hasRole($user)
                ? 'This account does not have Rider access.'
                : 'Your Rider account is currently unavailable. Please contact SHOPPICK support.']);
        }
        RateLimiter::clear($key);
        $guard->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        return redirect()->route('rider.dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('rider.login');
    }
}
