<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in is not configured yet. Add the Google credentials to the local environment.']);
        }

        $type=$request->query('account_type','buyer');
        session(['registration_type'=>in_array($type,['buyer','seller'],true)?$type:'buyer']);
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback()
    {
        if (request()->input('error') === 'access_denied') {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled.']);
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            return redirect()->route('login')->withErrors(['email' => 'Your Google sign-in session expired. Please try again.']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('login')->withErrors(['email' => 'Unable to sign in with Google. Please try again.']);
        }

        if (! $google->getEmail()) {
            return redirect()->route('login')->withErrors(['email' => 'Google did not provide a verified email address.']);
        }

        $email = strtolower($google->getEmail());
        $account = SocialAccount::with('user.roles')->where('provider', 'google')->where('provider_id', $google->getId())->first();
        $user = $account?->user;

        if (! $user) {
            $user = User::with('roles')->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user && ($user->isAdmin() || ! $user->isBuyer())) {
                return redirect()->route('login')->withErrors(['email' => 'This account cannot be linked through public Google login.']);
            }

            $user = DB::transaction(function () use ($user, $google, $email) {
                if (! $user) {
                    $user = User::create([
                        'name' => $google->getName() ?: Str::before($email, '@'),
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => Hash::make(Str::random(64)),
                        'is_active' => true,
                    ]);
                    $user->assignRole('buyer');
                }
                $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => $google->getId()]);
                return $user;
            });
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if (session()->pull('registration_type','buyer') === 'seller' && ! $user->isSeller()) {
            return redirect()->route('profile.complete.seller');
        }

        return $user->hasCompleteBuyerProfile()
            ? redirect()->intended(route('home'))
            : redirect()->route('profile.complete');
    }
}
