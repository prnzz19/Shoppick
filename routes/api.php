<?php

use App\Http\Controllers\MobileApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::post('login', [MobileApiController::class, 'login'])->middleware('throttle:10,1');
    Route::post('register', [MobileApiController::class, 'register'])->middleware('throttle:5,1');
    Route::get('products', [MobileApiController::class, 'products']);
    Route::get('products/{product}', [MobileApiController::class, 'product']);
    Route::get('categories', [MobileApiController::class, 'categories']);
    Route::get('home', [MobileApiController::class, 'home']);
    Route::middleware(App\Http\Middleware\AuthenticateMobileToken::class)->group(function () {
        Route::post('logout', [MobileApiController::class, 'logout']);
        Route::get('profile', [MobileApiController::class, 'profile']);
        Route::get('cart', [MobileApiController::class, 'cart']);
        Route::post('cart', [MobileApiController::class, 'addCart']);
        Route::patch('cart/{item}', [MobileApiController::class, 'updateCart']);
        Route::delete('cart/{item}', [MobileApiController::class, 'removeCart']);
        Route::post('checkout', [MobileApiController::class, 'checkout']);
        Route::get('orders', [MobileApiController::class, 'orders']);
        Route::get('orders/{order}', [MobileApiController::class, 'order']);
        Route::get('seller/application', [MobileApiController::class, 'sellerApplication']);
        Route::post('seller/application', [MobileApiController::class, 'submitSellerApplication']);
    });
});
