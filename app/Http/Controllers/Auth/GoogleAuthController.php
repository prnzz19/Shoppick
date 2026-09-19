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

        $type=$request->query('account_type','buyer');
        session(['registration_type'=>in_array($type,['buyer','seller'],true)?$type:'buyer']);
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
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

        if (! $google->getEmail()) {
            return $this->authenticationError('Google did not provide a verified email address.');
        }

        $email = strtolower($google->getEmail());
        $registrationType = session()->pull('registration_type', 'buyer');
        $account = SocialAccount::with('user.roles')->where('provider', 'google')->where('provider_id', $google->getId())->first();
        $user = $account?->user;

        if (! $user) {
            $googleData = $google->getRaw();
            $emailVerified = filter_var($googleData['email_verified'] ?? $googleData['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
            if (! $emailVerified) {
                return $this->authenticationError('Google did not provide a verified email address.');
            }

            $user = User::with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user && ($user->isAdmin() || ! $user->isBuyer())) {
                return $this->authenticationError('This account cannot be linked through public Google login.');
            }

            $user = DB::transaction(function () use ($user, $google, $email, $registrationType) {
                if (! $user) {
                    $user = User::create([
                        'name' => $google->getName() ?: Str::before($email, '@'),
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => Hash::make(Str::random(64)),
                        'is_active' => false,
                        'registration_type' => $registrationType,
                        'registration_status' => 'incomplete',
                    ]);
                    $user->assignRole('buyer');
                }
                $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => $google->getId()]);
                return $user;
            });
        }

        if ($user->registration_status === 'incomplete') {
            Auth::login($user, true);
            request()->session()->regenerate();

            return redirect()->route($user->registration_type === 'seller' ? 'profile.complete.seller' : 'profile.complete');
        }

        if (! $user->is_active) {
            if ($user->registration_status === 'pending') {
                return $this->authenticationError('Your registration is still waiting for administrator approval.');
            }

            if ($user->registration_status === 'rejected') {
                $reason = $user->registration_review_notes ? ' Reason: '.$user->registration_review_notes : '';

                return $this->authenticationError('Your registration was rejected.'.$reason);
            }

            return $this->authenticationError('Your account has been deactivated.');
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if ($registrationType === 'seller' && ! $user->isSeller()) {
            return redirect()->route('profile.complete.seller');
        }

        return $user->hasCompleteBuyerProfile()
            ? redirect()->intended(route('home'))
            : redirect()->route('profile.complete');
    }

    private function authenticationError(string $message)
    {
        return redirect()->route('login')->with('authentication_error', $message);
    }
}
