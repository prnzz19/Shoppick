<?php

namespace App\Services;

use App\Mail\RegistrationVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\{DB, Hash, Log, Mail};
use Illuminate\Validation\ValidationException;

class RegistrationVerification
{
    public function send(User $actor, string $role): void
    {
        $this->sendUsing($actor, $role, 'registration_verifications', RegistrationVerificationCode::class, true);
    }

    public function sendUsing(User $actor, string $role, string $table, string $mailableClass, bool $sharedPurpose = false): void
    {
        DB::transaction(function () use ($actor, $role, $table, $mailableClass, $sharedPurpose) {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->assertEligible($user, $role);
            $query = DB::table($table)->where('user_id', $user->id);
            if ($sharedPurpose) $query->where('purpose', 'registration');
            $previous = $query->lockForUpdate()->first();
            if ($previous && now()->lt(\Illuminate\Support\Carbon::parse($previous->sent_at)->addSeconds(60))) {
                throw ValidationException::withMessages(['code' => 'Please wait 60 seconds between code requests.']);
            }
            if (in_array(config('mail.default'), ['log', 'failover'], true) || (app()->isProduction() && config('mail.default') === 'array')) {
                throw new \RuntimeException('Registration verification delivery is not configured.');
            }
            $code = (string) random_int(100000, 999999);
            $key = ['user_id' => $user->id]; if ($sharedPurpose) $key['purpose'] = 'registration';
            $values = ['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(5), 'sent_at' => now(), 'attempts' => 0];
            if ($sharedPurpose) $values += ['verified_at' => null, 'updated_at' => now(), 'created_at' => $previous?->created_at ?? now()];
            DB::table($table)->updateOrInsert($key, $values);
            try {
                $mail = $mailableClass === RegistrationVerificationCode::class ? new $mailableClass($code, ucfirst($role)) : new $mailableClass($code);
                Mail::to($user->email)->send($mail);
            } catch (\Throwable $error) {
                if ($sharedPurpose) {
                    Log::warning('Registration verification delivery failed.', ['exception_class' => $error::class]);
                }
                throw $error;
            }
        });
    }

    public function verify(User $actor, string $code, string $role): User
    {
        return $this->verifyUsing($actor, $code, $role, 'registration_verifications', true, false);
    }

    public function verifyUsing(User $actor, string $code, string $role, string $table, bool $sharedPurpose = false, bool $activateBuyer = false): User
    {
        $result = DB::transaction(function () use ($actor, $code, $role, $table, $sharedPurpose, $activateBuyer) {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->assertEligible($user, $role);
            $query = DB::table($table)->where('user_id', $user->id); if ($sharedPurpose) $query->where('purpose', 'registration');
            $challenge = $query->lockForUpdate()->first();
            if (!$challenge || now()->gte(\Illuminate\Support\Carbon::parse($challenge->expires_at))) return 'This verification code has expired. Request a new code.';
            if ($challenge->attempts >= 5) return 'Too many incorrect attempts. Request a new code.';
            if (!Hash::check($code, $challenge->code_hash)) {
                DB::table($table)->where('user_id', $user->id)->increment('attempts');
                return 'Invalid verification code. Please try again.';
            }
            $user->forceFill(['email_verified_at' => now()]); if ($activateBuyer) $user->forceFill(['is_active' => true, 'registration_status' => 'approved']); else $user->forceFill(['is_active' => false]); $user->save();
            if ($sharedPurpose) DB::table($table)->where('id', $challenge->id)->update(['verified_at' => now(), 'updated_at' => now()]); else DB::table($table)->where('user_id', $user->id)->delete();
            return $user;
        });
        if (is_string($result)) throw ValidationException::withMessages(['code' => $result]);
        return $result;
    }

    public function assertEligible(User $user, string $role): void
    {
        abort_unless($user->registration_type === $role && $user->hasRole($role) && !$user->trashed() && !$user->email_verified_at, 403, 'This account is unavailable.');
    }
}
