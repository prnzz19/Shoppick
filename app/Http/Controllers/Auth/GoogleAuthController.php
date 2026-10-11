<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\GoogleOAuth;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        if (! GoogleOAuth::isAvailable()) {
            return redirect()->route('login')->with('google_unavailable', true);
        }

        session(['registration_type'=>'buyer']);
        try {
            return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
        } catch (\Throwable $error) {
            Log::warning('Google OAuth redirect failed.', ['exception_class'=>$error::class]);
            return $this->authenticationError("We couldn't start Google sign-in. Please try again.");
        }
    }

    public function callback()
    {
        if (request()->input('error') === 'access_denied') {
            return $this->authenticationError('Google sign-in was cancelled.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            return $this->authenticationError('Your Google sign-in session expired. Please try again.');
        } catch (ClientException $e) {
            $response = json_decode((string) $e->getResponse()?->getBody(), true);
            Log::warning('Google OAuth token exchange failed.', [
                'exception_class' => $e::class,
                'http_status' => $e->getResponse()?->getStatusCode(),
                'provider_error' => $response['error'] ?? 'unknown',
            ]);

            return $this->authenticationError("We couldn't complete Google sign-in. Please try again.");
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed.', ['exception_class' => $e::class]);
            return $this->authenticationError("We couldn't complete Google sign-in. Please try again.");
        }

        $googleData = $google->getRaw();
        $emailVerified = filter_var($googleData['email_verified'] ?? $googleData['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
        $email = mb_strtolower(trim((string)$google->getEmail()));
        $subject = (string)$google->getId();
        if (!$emailVerified || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>255 || $subject==='' || strlen($subject)>255) {
            return $this->authenticationError('Google did not provide a verified email address.');
        }

        session()->forget('registration_type');
        try {
            $account = SocialAccount::with('user.roles')->where('provider', 'google')->where('provider_id', $subject)->first();
            $user = $account?->user;

            // Never reassign an orphaned provider identity to a different account.
            if($account&&!$user)return $this->authenticationError('Your account is unavailable. Please contact support.');

            if (! $user) {
                $user = User::withTrashed()->with('roles')->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
                if ($user && ($user->isAdmin() || ! $user->isBuyer())) {
                    return $this->authenticationError('This account cannot be linked through public Google login.');
                }

                if($user){
                    if($user->trashed()||$user->getAttribute('archived_at')||(!$user->is_active&&!\App\Support\BuyerIdentity::needsVerification($user)))return $this->authenticationError('Your account has been deactivated. Please contact support.');
                    if(\App\Support\BuyerIdentity::needsVerification($user))app(\App\Services\BuyerEmailVerification::class)->assertEligible($user);
                    // Google is authoritative for Gmail or a verified Workspace domain.
                    // Other domains need existing-account authentication before linking.
                    if(!str_ends_with($email,'@gmail.com')&&empty($googleData['hd']))return $this->authenticationError('Please sign in with your SHOPPICK password to use this existing account.');
                    if($user->socialAccounts()->where('provider','google')->where('provider_id','!=',$subject)->exists())return $this->authenticationError('This account is already linked to a different Google identity. Please sign in.');
                }

                if (!$user && (!app(\App\Services\SystemSettings::class)->get('marketplace.allow_registration') || app(\App\Services\SystemSettings::class)->get('marketplace.status') === 'maintenance')) {
                    return $this->authenticationError('New Buyer registration is temporarily unavailable.');
                }

                $user = DB::transaction(function () use ($user, $google, $email, $googleData) {
                    if (! $user) {
                        $user = User::create([
                            'name' => $google->getName() ?: Str::before($email, '@'),
                            'email' => $email,
                            'email_verified_at' => now(),
                            'first_name' => $googleData['given_name'] ?? null,
                            'last_name' => $googleData['family_name'] ?? null,
                            'password' => Hash::make(Str::random(64)),
                        ] + \App\Services\BuyerAccountState::attributes(false));
                        $user->assignRole('buyer');
                        $user->forceFill(['buyer_web_email'=>$email])->save();
                    }
                    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => $google->getId()]);
                    return $user;
                });
            }

            if($user->isAdmin()||!$user->isBuyer())return $this->authenticationError('This account cannot be linked through public Google login.');

            if(! $user->trashed()&&!$user->getAttribute('archived_at')&&\App\Support\BuyerIdentity::needsVerification($user)){
                app(\App\Services\BuyerEmailVerification::class)->assertEligible($user);
                // Password registrations retain their SHOPPICK OTP requirement.
                Auth::login($user,false);request()->session()->regenerate();
                return redirect()->route('buyer.verification')->with('status','This account is waiting for email verification.');
            }

            if ($user->trashed() || $user->getAttribute('archived_at') || ! $user->is_active) {
                return $this->authenticationError('Your account has been deactivated. Please contact support.');
            }

            if ($user->registration_status === 'incomplete') {
                Auth::login($user, true);
                request()->session()->regenerate();

                return redirect()->route('profile.complete');
            }

            if (in_array($user->registration_status, ['pending', 'rejected'], true)
                && ! ($user->isNormalBuyerRegistration() && $user->registration_status === 'pending')) {
                if ($user->registration_status === 'pending') {
                    return $this->authenticationError('Your registration is still waiting for administrator approval.');
                }

                if ($user->registration_status === 'rejected') {
                    $reason = $user->registration_review_notes ? ' Reason: '.$user->registration_review_notes : '';

                    return $this->authenticationError('Your registration was rejected.'.$reason);
                }

            }

            Auth::login($user, true);
            request()->session()->regenerate();


            return $user->hasCompleteBuyerProfile()
                ? redirect()->intended(route('home'))
                : redirect()->route('profile.complete');
        } catch(\Throwable $error) {
            Log::warning('Google account sign-in failed.', ['exception_class'=>$error::class]);
            return $this->authenticationError("We couldn't complete Google sign-in. Please try again.");
        }
    }

    private function authenticationError(string $message)
    {
        return redirect()->route('login')->with('authentication_error', $message);
    }
}
