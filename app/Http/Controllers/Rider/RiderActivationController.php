<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderActivationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class RiderActivationController extends Controller
{
    public function show(string $token)
    {
        $record = RiderActivationToken::with('riderProfile.user')->where('token_hash', hash('sha256', $token))->first();
        $valid = $record && $record->expires_at->isFuture() && $record->riderProfile?->account_status === 'pending_activation';
        return view('rider.activate', [
            'token' => $token,
            'rider' => $valid ? $record->riderProfile->user : null,
            'activationMessage' => $record && $record->expires_at->lessThanOrEqualTo(now())
                ? 'This activation link has expired. Ask your Logistics provider to resend the invitation.'
                : 'This activation link is invalid or has already been used.',
        ]);
    }

    public function activate(Request $request, string $token)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], ['password.confirmed' => 'The passwords do not match.']);

        $activated = DB::transaction(function () use ($token, $data) {
            $record = RiderActivationToken::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $record || $record->expires_at->isPast()) return false;
            $profile = $record->riderProfile()->lockForUpdate()->first();
            if (! $profile || $profile->account_status !== 'pending_activation' || $profile->user->is_active) return false;
            $profile->user->update(['password' => Hash::make($data['password']), 'is_active' => true, 'registration_status' => 'approved', 'email_verified_at' => now()]);
            $profile->update(['account_status' => 'active', 'availability' => 'unavailable']);
            $record->delete();
            return true;
        });

        if (! $activated) throw ValidationException::withMessages(['token' => 'This activation link is invalid, expired, or already used.']);
        return redirect()->route('rider.login')->with('success', 'Your Rider account is active. You can now log in.');
    }

}
