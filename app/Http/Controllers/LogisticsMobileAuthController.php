<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateLogisticsMobile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LogisticsMobileAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', strtolower($data['email']))->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 422, 'The email or password is incorrect.');
        abort_unless(AuthenticateLogisticsMobile::role($user), 403, 'This account does not have access to SHOPPICK Logistics.');
        $plain = bin2hex(random_bytes(32));
        DB::table('mobile_api_tokens')->insert(['user_id' => $user->id, 'name' => 'logistics-mobile', 'token_hash' => hash('sha256', $plain), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['token' => $plain, 'user' => $this->userData($user)], 201);
    }

    public function profile(Request $request)
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    public function logout(Request $request)
    {
        DB::table('mobile_api_tokens')->where('token_hash', hash('sha256', $request->bearerToken()))->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userData(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
            'avatar' => $user->avatar_url, 'role' => AuthenticateLogisticsMobile::role($user),
            'provider' => $user->riderProfile?->provider?->name,
            'status' => $user->riderProfile?->account_status, 'availability' => $user->riderProfile?->availability,
            'completed_deliveries' => $user->assignedShipments()->whereIn('status', ['delivered', 'completed'])->count(),
            'permissions' => $user->permissions()->pluck('slug')->values()];
    }
}
