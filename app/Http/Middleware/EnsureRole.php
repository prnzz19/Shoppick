<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            auth()->logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Contact support.',
            ]);
        }

        // Super admin always has access to every admin area
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
