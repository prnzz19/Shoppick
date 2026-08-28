<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Services\Moderation\ImageModerationService;
use App\Services\Moderation\ConfigurableImageModerationService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\OrderService::class);
        $this->app->bind(ImageModerationService::class, ConfigurableImageModerationService::class);
    }

    public function boot(): void
    {
        // Share marketplace data with the storefront & account layouts.
        View::composer(['layouts.storefront', 'layouts.account', 'components.storefront.header', 'components.storefront.mobile-nav'], function ($view) {
            $user = auth()->user();

            $view->with('sharedCartCount', $user ? app(CartService::class)->count($user->id) : 0);
            $view->with('sharedWishlistCount', $user ? (int) ($user->wishlist?->items()->count() ?? 0) : 0);
            $view->with('sharedUnreadNotifications', $user ? $user->notificationsData()->unread()->count() : 0);
            $view->with('sharedCategories', \App\Models\Category::whereNull('parent_id')->active()->orderBy('sort_order')->with('children')->get());
        });
    }
}
