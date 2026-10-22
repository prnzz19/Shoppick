<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\RegistrationVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class RegistrationVerificationController extends Controller
{
    private function role(Request $request): string
    {
        $role = $request->user()->registration_type;
        abort_unless(in_array($role, ['seller', 'logistics', 'rider'], true), 403);
        return $role;
    }

    public function show(Request $request)
    {
        $role = $this->role($request);
        $challenge = DB::table('registration_verifications')->where(['user_id' => $request->user()->id, 'purpose' => 'registration'])->first();
        [$local, $domain] = explode('@', $request->user()->email, 2);
        return view('auth.registration-verification', ['role' => $role, 'maskedEmail' => mb_substr($local, 0, 1).'***@'.$domain, 'expiresAt' => $challenge?->expires_at ? \Illuminate\Support\Carbon::parse($challenge->expires_at)->toIso8601String() : null, 'resendAt' => $challenge?->sent_at ? \Illuminate\Support\Carbon::parse($challenge->sent_at)->addSeconds(60)->toIso8601String() : null]);
    }

    public function verify(Request $request, RegistrationVerification $service)
    {
        $role = $this->role($request);
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']]);
        $service->verify($request->user(), $data['code'], $role);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('status', ucfirst($role).' email verified. Your application remains pending platform approval.');
    }

    public function resend(Request $request, RegistrationVerification $service)
    {
        $role = $this->role($request);
        $service->send($request->user(), $role);
        return back()->with('status', 'A new verification code was sent.');
    }
}
