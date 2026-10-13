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
        if ($request->user()&&RiderPortal::awaitingReview($request->user())) {
            return redirect()->route('rider.application.status');
        }
        if($request->user()&&RiderPortal::hasRole($request->user())&&!RiderPortal::active($request->user()))return response()->view('rider.account-unavailable',[],403);
        abort_unless($request->user() && RiderPortal::active($request->user()), 403,
            'Your Rider account is currently unavailable. Please contact SHOPPICK support.');
        return $next($request);
    }
}
