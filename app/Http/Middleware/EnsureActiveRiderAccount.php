<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRiderAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->riderProfile?->account_status === 'active' && $request->user()->is_active, 403, 'This Rider account is not active.');
        return $next($request);
    }
}
