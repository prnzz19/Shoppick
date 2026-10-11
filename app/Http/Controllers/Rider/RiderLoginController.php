<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Support\RiderPortal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Validation\ValidationException;

class RiderLoginController extends Controller
{
    public function create() { return view('rider.login'); }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
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
        if (!RiderPortal::hasRole($user) || !RiderPortal::active($user)) {
            RateLimiter::hit($key, 60);
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
