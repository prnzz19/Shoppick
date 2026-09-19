<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\AdminSidebarCounts;
use App\Services\CartService;
use App\Services\LogisticsSidebarCounts;
use App\Services\Moderation\ConfigurableImageModerationService;
use App\Services\Moderation\ImageModerationService;
use App\Services\OrderService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderService::class);
        $this->app->bind(ImageModerationService::class, ConfigurableImageModerationService::class);
    }

    public function boot(): void
    {
        $wishlistProductIds = function (): array {
            $request = request();
            $cacheKey = 'shoppick_wishlist_product_ids';

            if (! $request->attributes->has($cacheKey)) {
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
            $view->with('sharedCategories', Category::whereNull('parent_id')->active()->orderBy('sort_order')->orderBy('name')->with('children')->get());
        });

        View::composer(['components.product-card', 'storefront.products.show'], function ($view) use ($wishlistProductIds) {
            $view->with('sharedWishlistProductIds', $wishlistProductIds());
        });

        View::composer('layouts.logistics', function ($view) {
            $user = auth()->user();
            $view->with('logisticsSidebarCounts', $user
                ? app(LogisticsSidebarCounts::class)->for($user)
                : []);
        });

        View::composer('layouts.admin', function ($view) {
            $view->with('adminSidebarCounts', auth()->check()
                ? app(AdminSidebarCounts::class)->get()
                : []);
        });

        View::composer('layouts.rider', function ($view) {
            $view->with('riderUnread', auth()->check() ? auth()->user()->notificationsData()->unread()->count() : 0);
        });
    }
}
