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
        $wishlistProductIds = function (): array {
            $request = request();
            $cacheKey = 'shoppick_wishlist_product_ids';

            if (!$request->attributes->has($cacheKey)) {
                $ids = auth()->user()?->wishlist?->items()->pluck('product_id')->all() ?? [];
                $request->attributes->set($cacheKey, $ids);
            }

            return $request->attributes->get($cacheKey, []);
        };

        // Share marketplace data with the storefront & account layouts.
        View::composer(['layouts.storefront', 'layouts.account', 'components.storefront.header', 'components.storefront.mobile-nav'], function ($view) use ($wishlistProductIds) {
            $user = auth()->user();

            $view->with('sharedCartCount', $user ? app(CartService::class)->count($user->id) : 0);
            $ids = $wishlistProductIds();
            $view->with('sharedWishlistCount', count($ids));
            $view->with('sharedWishlistProductIds', $ids);
            $view->with('sharedUnreadNotifications', $user ? $user->notificationsData()->unread()->count() : 0);
            $view->with('sharedCategories', \App\Models\Category::whereNull('parent_id')->active()->orderBy('sort_order')->orderBy('name')->with('children')->get());
        });

        View::composer(['components.product-card', 'storefront.products.show'], function ($view) use ($wishlistProductIds) {
            $view->with('sharedWishlistProductIds', $wishlistProductIds());
        });
    }
}
