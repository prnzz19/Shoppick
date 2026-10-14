<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm(Request $request)
    {
        $role = in_array($request->query('role'), ['seller', 'logistics'], true) ? $request->query('role') : null;
        return view('auth.login', compact('role'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }
        $user = Auth::user();
        if ($user->trashed()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your account has been deactivated. Please contact support.']);
        }
        if ($user->registration_type === 'rider' && in_array($user->registration_status, ['pending', 'rejected', 'needs_resubmission'], true)) {
            $request->session()->regenerate();
            return redirect()->route('rider.application.status');
        }
        if (in_array($user->registration_status, ['pending', 'rejected'], true)
            && ! ($user->isNormalBuyerRegistration() && $user->registration_status === 'pending')) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => $this->registrationMessage($user)]);
        }
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your account has been deactivated. Please contact support.']);
        }
        $request->session()->regenerate();
        return $this->redirectTo($user);
    }

    private function registrationMessage($user): string
    {
        $reason = $user->registration_review_notes ?: 'Please contact SHOPPICK support.';
        if ($user->registration_status === 'pending') {
            if ($user->hasRole('seller')) return 'Your seller application is currently under review.';
            if ($user->hasRole('logistics')) return 'Your logistics application is currently under review.';
            return 'Your account is still waiting for administrator approval.';
        }
        if ($user->hasRole('seller')) return 'Your seller application was not approved. Reason: '.$reason;
        if ($user->hasRole('logistics')) return 'Your logistics application was not approved. Reason: '.$reason;
        return 'Your registration was not approved. Reason: '.$reason;
    }

    protected function redirectTo($user)
    {
        if ($user->hasRole('admin')) return redirect()->route('admin.dashboard');
        if ($user->hasRole('logistics')) return redirect()->route('logistics.dashboard');
        if ($user->hasRole('rider')) return redirect()->route('rider.dashboard');
        if ($user->hasApprovedSellerAccess()) return redirect()->route('seller.dashboard');
        if ($user->sellerApplications()->whereIn('status', ['pending', 'escalated', 'awaiting_final_review', 'needs_resubmission', 'rejected'])->exists()) return redirect()->route('seller.apply');
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