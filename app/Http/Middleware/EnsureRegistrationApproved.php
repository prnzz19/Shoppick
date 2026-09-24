<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Accounts created before registration approval existed have no registration metadata.
        // Keep those legitimate legacy users usable while enforcing the flow for new applicants.
        if (! $user || ! $user->registration_type || ! $user->registration_status || $user->registration_status === 'approved') {
            return $next($request);
        }

        if ($user->registration_status === 'incomplete') {
            return redirect()->route('profile.complete');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $user->registration_status === 'rejected'
            ? 'Your registration was not approved.'.($user->registration_review_notes ? ' Reason: '.$user->registration_review_notes : '')
            : ($user->registration_type === 'rider' ? 'Your Rider application is still waiting for SHOPPICK Logistics approval.' : 'Your registration is still waiting for administrator approval.');

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
