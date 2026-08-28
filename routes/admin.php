<?php

use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminInventoryController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\SellerApplicationController;
use App\Http\Controllers\Admin\ReportManagementController;
use App\Http\Controllers\Admin\ModerationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,super_admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard', [AdminDashboardController::class, 'index']);
    Route::get('sellers/applications', [SellerApplicationController::class, 'index'])->name('sellers.applications.index')->middleware('permission:manage_sellers');
    Route::post('sellers/applications/{application}', [SellerApplicationController::class, 'review'])->name('sellers.applications.review')->middleware('permission:manage_sellers');

    // Products
    Route::get('products', [AdminProductController::class, 'index'])->name('products.index')
        ->middleware('permission:manage_products');
    Route::get('products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    Route::post('products/{product}/toggle', [AdminProductController::class, 'toggleActive'])->name('products.toggle');
    Route::post('products/{product}/images', [AdminProductController::class, 'storeImages'])->name('products.images.store');
    Route::delete('products/{product}/images/{imageId}', [AdminProductController::class, 'deleteImage'])->name('products.images.destroy');
    Route::post('products/{product}/images/{imageId}/primary', [AdminProductController::class, 'setPrimaryImage'])->name('products.images.primary');

    // Categories
    Route::get('categories', [AdminCategoryController::class, 'index'])->name('categories.index')
        ->middleware('permission:manage_categories');
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/{category}/toggle', [AdminCategoryController::class, 'toggleActive'])->name('categories.toggle');

    // Inventory
    Route::get('inventory', [AdminInventoryController::class, 'index'])->name('inventory.index')
        ->middleware('permission:manage_inventory');
    Route::post('inventory/{product}/stock', [AdminInventoryController::class, 'updateStock'])->name('inventory.stock');

    // Orders
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index')
        ->middleware('permission:manage_orders');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

    // Promotions
    Route::get('promotions', [AdminPromotionController::class, 'index'])->name('promotions.index')
        ->middleware('permission:manage_promotions');
    Route::get('promotions/create', [AdminPromotionController::class, 'create'])->name('promotions.create');
    Route::post('promotions', [AdminPromotionController::class, 'store'])->name('promotions.store');
    Route::get('promotions/{voucher}/edit', [AdminPromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('promotions/{voucher}', [AdminPromotionController::class, 'update'])->name('promotions.update');
    Route::delete('promotions/{voucher}', [AdminPromotionController::class, 'destroy'])->name('promotions.destroy');
    Route::post('promotions/{voucher}/toggle', [AdminPromotionController::class, 'toggleStatus'])->name('promotions.toggle');

    // Reports
    Route::get('analytics', [AdminReportController::class, 'index'])->name('analytics.index')
        ->middleware('permission:view_reports');
    Route::get('reports', [ReportManagementController::class, 'index'])->name('reports.index')->middleware('permission:manage_reports');
    Route::get('reports/{report}', [ReportManagementController::class, 'show'])->name('reports.show')->middleware('permission:manage_reports');
    Route::put('reports/{report}', [ReportManagementController::class, 'update'])->name('reports.update')->middleware('permission:manage_reports');
    Route::get('moderation', [ModerationController::class, 'index'])->name('moderation.index')->middleware('permission:moderate_products');
    Route::get('moderation/{scan}', [ModerationController::class, 'show'])->name('moderation.show')->middleware('permission:moderate_products');
    Route::post('moderation/{scan}/review', [ModerationController::class, 'review'])->name('moderation.review')->middleware('permission:moderate_products');
});
