<?php

namespace App\Http\Middleware;

use App\Support\RiderPortal;
use Closure;
use Illuminate\Http\Request;

class EnsureRiderPortalAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Preserve the existing application-status flow for Riders awaiting approval.
        if ($request->user()?->registration_type === 'rider'
            && in_array($request->user()->registration_status, ['pending','rejected','needs_resubmission','incomplete'], true)) {
            return app(EnsureRegistrationApproved::class)->handle($request, $next);
        }
        abort_unless($request->user() && RiderPortal::active($request->user()), 403,
            'Your Rider account is currently unavailable. Please contact SHOPPICK support.');
        return $next($request);
    }
}
