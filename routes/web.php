<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhilippineLocationController;

Route::prefix('api/philippine-locations')->middleware('throttle:120,1')->group(function () {
    Route::get('regions', [PhilippineLocationController::class, 'regions'])->name('locations.regions');
    Route::get('regions/{region}/provinces', [PhilippineLocationController::class, 'provinces'])->name('locations.provinces');
    Route::get('regions/{region}/cities-municipalities', [PhilippineLocationController::class, 'regionCitiesMunicipalities'])->name('locations.region-cities');
    Route::get('provinces/{province}/cities-municipalities', [PhilippineLocationController::class, 'provinceCitiesMunicipalities'])->name('locations.province-cities');
    Route::get('cities-municipalities/{cityMunicipality}/barangays', [PhilippineLocationController::class, 'barangays'])->name('locations.barangays');
});

// Public storefront auth
require __DIR__.'/auth.php';

// Public storefront routes
require __DIR__.'/storefront.php';

// Authenticated buyer routes
require __DIR__.'/account.php';

// Authenticated admin panel
require __DIR__.'/admin.php';

// Temporary authenticated compatibility redirects for legacy bookmarks.
// Current application navigation and actions use /admin exclusively.
Route::middleware(['auth', 'role:admin'])->prefix('superadmin')->group(function () {
    Route::get('/{path?}', function (?string $path = null) {
        $path = trim($path ?? 'dashboard', '/');
        abort_unless(preg_match('#^(dashboard|users(?:/.*)?|admins|roles(?:/.*)?|shops(?:/.*)?|categories(?:/.*)?|reports(?:/.*)?|moderation(?:/.*)?)$#', $path), 404);

        return redirect('/admin/'.$path, 302);
    })->where('path', '.*');
});

require __DIR__.'/seller.php';
require __DIR__.'/logistics.php';
require __DIR__.'/rider.php';
