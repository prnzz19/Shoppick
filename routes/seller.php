<?php

use App\Http\Controllers\Admin\SellerApplicationController as AdminSellerApplicationController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\ProductController;
use App\Http\Controllers\Seller\StoreController;
use App\Http\Controllers\Seller\InventoryController;
use App\Http\Controllers\Seller\ReviewController as SellerReviewController;
use App\Http\Controllers\Seller\SalesController;
use App\Http\Controllers\Seller\MarketingController;
use App\Http\Controllers\Seller\NotificationController as SellerNotificationController;
use App\Http\Controllers\Seller\SettingsController;
use App\Http\Controllers\Seller\CenterController;
use App\Http\Controllers\SellerApplicationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('seller/apply', [SellerApplicationController::class, 'create'])->name('seller.apply');
    Route::post('seller/apply', [SellerApplicationController::class, 'store'])->name('seller.apply.store');
});

Route::prefix('seller')->name('seller.')->middleware(['auth', 'role:seller'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('products', [CenterController::class, 'products'])->name('products.index');
    Route::resource('products', ProductController::class)->except(['show','index']);
    Route::get('orders', [CenterController::class, 'orders'])->name('orders.index');
    Route::get('orders/{sellerOrder}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{sellerOrder}/status', [OrderController::class, 'update'])->name('orders.status');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('inventory/{product}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::get('reviews', [CenterController::class, 'reviews'])->name('reviews.index');
    Route::post('reviews/{review}/reply', [SellerReviewController::class, 'reply'])->name('reviews.reply');
    Route::get('sales', [CenterController::class, 'sales'])->name('sales.index');
    Route::get('marketing', [MarketingController::class, 'index'])->name('marketing.index');
    Route::post('marketing/vouchers', [MarketingController::class, 'store'])->name('marketing.vouchers.store');
    Route::post('marketing/vouchers/{voucher}/toggle', [MarketingController::class, 'toggle'])->name('marketing.vouchers.toggle');
    Route::get('notifications', [SellerNotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [SellerNotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [SellerNotificationController::class, 'read'])->name('notifications.read');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings/account', [SettingsController::class, 'account'])->name('settings.account');
    Route::put('settings/shipping', [SettingsController::class, 'shipping'])->name('settings.shipping');
    Route::put('settings/password', [SettingsController::class, 'password'])->name('settings.password');
    Route::get('store/settings', [StoreController::class, 'edit'])->name('store.edit');
    Route::put('store/settings', [StoreController::class, 'update'])->name('store.update');
});
