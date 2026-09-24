<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if (! $plain) return response()->json(['message' => 'Unauthenticated.'], 401);
        $token = DB::table('mobile_api_tokens')->where('token_hash', hash('sha256', $plain))->first();
        $user = $token ? User::find($token->user_id) : null;
        if (! $user || ! $user->is_active) return response()->json(['message' => 'Unauthenticated.'], 401);
        DB::table('mobile_api_tokens')->where('id', $token->id)->update(['last_used_at' => now(), 'updated_at' => now()]);
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
}
