<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;

class MarketplacePreferences
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SystemSettings::class);
        // Keep operational teams and recovery/login routes available during maintenance.
        $operations = $request->routeIs('admin.*', 'logistics.*', 'rider.*', 'seller.*', 'login*', 'logout', 'password.*', 'auth.google.*', 'locations.*');
        if (!$operations && $settings->get('marketplace.status') === 'maintenance') {
            return response()->view('errors.marketplace-maintenance', [], 503)->header('Retry-After', '300');
        }
        $guard = match (true) {
            $request->routeIs('register', 'register.buyer', 'register.submit') => ['marketplace.allow_registration', 'New Buyer registration is temporarily unavailable.'],
            $request->routeIs('register.rider', 'register.rider.submit', 'rider.application.resubmit') => ['logistics.rider_applications', 'Rider applications are temporarily unavailable.'],
            $request->routeIs('seller.apply.store') => ['marketplace.allow_seller_applications', 'Seller applications are temporarily unavailable.'],
            $request->routeIs('review.create', 'review.store') => ['marketplace.allow_reviews', 'Product reviews are temporarily unavailable.'],
            default => null,
        };
        if ($guard) abort_unless($settings->get($guard[0]), 403, $guard[1]);
        return $next($request);
    }
}
