<?php

use App\Http\Controllers\MobileApiController;
use App\Http\Controllers\Api\SortingCenterScannerController;
use App\Http\Controllers\Api\RiderMobileApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::post('login', [MobileApiController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register', [MobileApiController::class, 'register'])->middleware('throttle:5,1');
    Route::get('products', [MobileApiController::class, 'products']);
    Route::get('products/{product}', [MobileApiController::class, 'product']);
    Route::get('categories', [MobileApiController::class, 'categories']);
    Route::get('shops/{shop}', [MobileApiController::class, 'shop']);
    Route::get('home', [MobileApiController::class, 'home']);
    Route::middleware(App\Http\Middleware\AuthenticateMobileToken::class)->group(function () {
        Route::post('logout', [MobileApiController::class, 'logout']);
        Route::prefix('rider')->middleware('throttle:60,1')->group(function () {
            Route::get('jobs', [RiderMobileApiController::class, 'jobs'])->name('api.rider.jobs');
            Route::get('history', [RiderMobileApiController::class, 'history'])->name('api.rider.history');
            Route::get('jobs/{shipment}', [RiderMobileApiController::class, 'show'])->name('api.rider.jobs.show')->whereNumber('shipment');
            Route::patch('availability', [RiderMobileApiController::class, 'availability'])->name('api.rider.availability');
            Route::post('shipments/{shipment}/accept', [RiderMobileApiController::class, 'accept'])->name('api.rider.shipments.accept')->whereNumber('shipment');
            Route::post('shipments/{shipment}/action', [RiderMobileApiController::class, 'action'])->name('api.rider.shipments.action')->whereNumber('shipment');
            Route::post('shipments/{shipment}/decline', [RiderMobileApiController::class, 'decline'])->name('api.rider.shipments.decline')->whereNumber('shipment');
            Route::post('shipments/{shipment}/pod', [RiderMobileApiController::class, 'pod'])->name('api.rider.shipments.pod')->whereNumber('shipment');
            Route::post('shipments/{shipment}/cod', [RiderMobileApiController::class, 'cod'])->name('api.rider.shipments.cod')->whereNumber('shipment');
        });
        Route::prefix('logistics/sorting-center')->group(function () {
            Route::get('context', [SortingCenterScannerController::class, 'context'])->name('api.logistics.sorting-center.context');
            Route::post('scan', [SortingCenterScannerController::class, 'scan'])->name('api.logistics.sorting-center.scan');
            Route::get('scans', [SortingCenterScannerController::class, 'recent'])->name('api.logistics.sorting-center.scans');
        });
        Route::get('profile', [MobileApiController::class, 'profile']);
        Route::get('cart', [MobileApiController::class, 'cart']);
        Route::post('cart', [MobileApiController::class, 'addCart']);
        Route::patch('cart/{item}', [MobileApiController::class, 'updateCart']);
        Route::delete('cart/{item}', [MobileApiController::class, 'removeCart']);
        Route::get('checkout', [MobileApiController::class, 'checkoutPreview']);
        Route::post('checkout', [MobileApiController::class, 'checkout']);
        Route::get('orders', [MobileApiController::class, 'orders']);
        Route::get('orders/{order}', [MobileApiController::class, 'order']);
        Route::get('orders/{order}/tracking', [\App\Http\Controllers\MobileTrackingController::class, 'buyer']);
        Route::get('seller/orders', [\App\Http\Controllers\MobileTrackingController::class, 'sellerOrders']);
        Route::get('seller/orders/{sellerOrder}/tracking', [\App\Http\Controllers\MobileTrackingController::class, 'seller'])->whereNumber('sellerOrder');
        Route::get('seller/application', [MobileApiController::class, 'sellerApplication']);
        Route::post('seller/application', [MobileApiController::class, 'submitSellerApplication']);
    });
});
