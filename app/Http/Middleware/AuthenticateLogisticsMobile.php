<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuthenticateLogisticsMobile
{
    public static function role(User $user): ?string
    {
        if (! $user->is_active || ($user->registration_type && $user->registration_status && $user->registration_status !== 'approved')) {
            return null;
        }
        if ($user->hasRole('logistics')) {
            return 'logistics';
        }
        if ($user->hasRole('rider') && $user->riderProfile?->account_status === 'active') {
            return 'rider';
        }

        return null;
    }

    public function handle(Request $request, Closure $next, ?string $role = null)
    {
        $token = $request->bearerToken() ? DB::table('mobile_api_tokens')->where('name', 'logistics-mobile')->where('token_hash', hash('sha256', $request->bearerToken()))->first() : null;
        $user = $token ? User::find($token->user_id) : null;
        if (! $user || Carbon::parse($token->created_at)->addDays(30)->isPast()) {
            return response()->json(['message' => 'Your session has expired. Please sign in again.'], 401);
        }
        $actual = self::role($user);
        if (! $actual || ($role && $actual !== $role)) {
            return response()->json(['message' => 'This account does not have access to SHOPPICK Logistics.'], 403);
        }
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('logistics_role', $actual);
        DB::table('mobile_api_tokens')->where('id', $token->id)->update(['last_used_at' => now(), 'updated_at' => now()]);

        return $next($request);
    }
}
