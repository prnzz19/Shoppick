<?php

use App\Http\Controllers\LogisticsMobileAuthController;
use App\Http\Controllers\LogisticsMobileController;
use App\Http\Middleware\AuthenticateLogisticsMobile;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::post('logistics/login', [LogisticsMobileAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::prefix('logistics')->middleware(AuthenticateLogisticsMobile::class)->group(function () {
        Route::get('profile', [LogisticsMobileAuthController::class, 'profile']);
        Route::post('logout', [LogisticsMobileAuthController::class, 'logout']);
    });
    foreach (['logistics', 'rider'] as $role) {
        Route::prefix($role)->middleware(AuthenticateLogisticsMobile::class.':'.$role)->group(function () use ($role) {
            Route::get('dashboard', [LogisticsMobileController::class, 'dashboard']);
            Route::get('deliveries', [LogisticsMobileController::class, 'deliveries']);
            Route::get('deliveries/{delivery}', [LogisticsMobileController::class, 'show'])->whereNumber('delivery');
            Route::patch('deliveries/{delivery}/status', [LogisticsMobileController::class, 'status'])->whereNumber('delivery');
            Route::get('notifications', [LogisticsMobileController::class, 'notifications']);
            Route::post('notifications/read-all', [LogisticsMobileController::class, 'readNotifications']);
            if ($role === 'rider') {
                Route::post('deliveries/{delivery}/proof', [LogisticsMobileController::class, 'proof'])->whereNumber('delivery');
            } else {
                Route::patch('deliveries/{delivery}/assign-rider', [LogisticsMobileController::class, 'assign'])->whereNumber('delivery');
                Route::patch('deliveries/{delivery}/provider', [LogisticsMobileController::class, 'linkProvider'])->whereNumber('delivery');
                Route::get('riders', [LogisticsMobileController::class, 'riders']);
                Route::patch('riders/{rider}/provider', [LogisticsMobileController::class, 'riderProvider'])->whereNumber('rider');
                Route::get('providers', [LogisticsMobileController::class, 'providers']);
                Route::post('providers', [LogisticsMobileController::class, 'saveProvider']);
                Route::get('delivery-areas', [LogisticsMobileController::class, 'areas']);
            }
        });
    }
});
