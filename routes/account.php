<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ProductReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Cart
    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('cart/items/{itemId}/quantity', [CartController::class, 'updateQuantity'])->name('cart.update');
    Route::post('cart/items/{itemId}/remove', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('cart/items/{itemId}/selected', [CartController::class, 'toggleSelected'])->name('cart.toggle');

    // Wishlist
    Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('wishlist/items/{itemId}', [WishlistController::class, 'remove'])->name('wishlist.remove');
    Route::post('wishlist/items/{itemId}/move-to-cart', [WishlistController::class, 'moveToCart'])->name('wishlist.move-to-cart');

    // Checkout
    Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout');
    Route::post('checkout/voucher', [CheckoutController::class, 'applyVoucher'])->name('checkout.voucher');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // Orders
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{orderNumber}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{orderNumber}/confirm', [OrderController::class, 'confirmReceived'])->name('orders.confirm');

    // Reviews
    Route::get('orders/{orderNumber}/review/{productId}', [ReviewController::class, 'create'])->name('review.create');
    Route::post('orders/{orderNumber}/review/{productId}', [ReviewController::class, 'store'])->name('review.store');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Account / profile
    Route::get('account', [AccountController::class, 'profile'])->name('account.profile');
    Route::post('account', [AccountController::class, 'updateProfile'])->name('account.update');
    Route::get('account/password', [AccountController::class, 'changePasswordForm'])->name('account.password');
    Route::post('account/password', [AccountController::class, 'changePassword'])->name('account.password.update');

    // Addresses
    Route::get('account/addresses', [AddressController::class, 'index'])->name('account.addresses');
    Route::post('account/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::put('account/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('account/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::post('account/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('account.addresses.default');
    Route::post('products/{product}/report', [ProductReportController::class, 'store'])->name('products.report');
});
