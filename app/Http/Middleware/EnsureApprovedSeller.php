<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureApprovedSeller
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasApprovedSellerAccess(), 403, 'An approved seller profile and active shop are required.');
        return $next($request);
    }
}
