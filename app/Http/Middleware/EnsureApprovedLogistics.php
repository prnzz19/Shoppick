<?php

namespace App\Http\Middleware;

use App\Support\LogisticsProviderContext;
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
        $request->attributes->set('logistics_provider', app(LogisticsProviderContext::class)->requireFor($user));
        return $next($request);
    }
}
