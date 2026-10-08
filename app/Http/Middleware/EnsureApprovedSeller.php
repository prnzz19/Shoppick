<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsureApprovedSeller
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->user()?->sellerProfile?->archived_at || $request->user()?->store?->archived_at,
            403, 'Your seller access is currently archived. Please contact SHOPPICK support.');
        abort_unless($request->user()?->hasApprovedSellerAccess(), 403, 'An approved seller profile and active shop are required.');
        return $next($request);
    }
}
