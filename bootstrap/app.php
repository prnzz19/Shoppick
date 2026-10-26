<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRegistrationApproved;
use App\Http\Middleware\EnsureApprovedLogistics;
use App\Http\Middleware\EnsureActiveRiderAccount;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => route($request->is('rider', 'rider/*') ? 'rider.login' : 'login'));
        // Cloudflared reaches Apache through the verified loopback boundary. Only trust forwarded client/proto/port data from that proxy.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Accept only the production hosts; forwarded host is intentionally not trusted.
        // Do not register host enforcement while PHPUnit is bootstrapping; individual tests may
        // temporarily emulate production environment state without a production request host.
        if (! app()->runningUnitTests()) {
            $middleware->trustHosts(
                at: ['^shoppickmarket\\.shop$', '^www\\.shoppickmarket\\.shop$'],
                subdomains: false,
            );
        }
        $middleware->web(append: [
            \App\Http\Middleware\MarketplacePreferences::class,
            \App\Http\Middleware\PreventAuthenticatedPageCaching::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'registration.approved' => EnsureRegistrationApproved::class,
            'logistics.approved' => EnsureApprovedLogistics::class,
            'rider.active' => EnsureActiveRiderAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $error, \Illuminate\Http\Request $request) {
            if (!$request->is('register','register/buyer','verify-buyer-email','verify-buyer-email/*','login')
                || $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $error instanceof \Illuminate\Validation\ValidationException
                || $error instanceof \Illuminate\Auth\AuthenticationException
                || $error instanceof \Illuminate\Auth\Access\AuthorizationException
                || $error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $error instanceof \Illuminate\Http\Exceptions\HttpResponseException) return null;
            return $request->expectsJson()?response()->json(['message'=>'Unable to complete your request. Please try again.'],503):response()->view('auth.buyer-unavailable',[],503);
        });
        $exceptions->render(function (\Throwable $error, \Illuminate\Http\Request $request) {
            if (!$request->is('rider', 'rider/*','register/rider')
                || $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                || $error instanceof \Illuminate\Validation\ValidationException
                || $error instanceof \Illuminate\Auth\AuthenticationException
                || $error instanceof \Illuminate\Auth\Access\AuthorizationException
                || $error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || $error instanceof \Illuminate\Http\Exceptions\HttpResponseException) return null;
            return $request->expectsJson()
                ? response()->json(['message'=>'Unable to complete your request. Please try again.'],503)
                : response()->view('rider.unavailable',[],503);
        });
    })->create();

