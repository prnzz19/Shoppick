<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApprovedLogistics
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user?->hasRole('logistics') && $user->is_active
            && in_array($user->registration_status, ['approved', null], true), 403,
            'Only an active, approved Logistics account can manage Riders.');
        return $next($request);
    }
}
