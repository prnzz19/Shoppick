<?php

use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\Admin\SellerApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('superadmin')->name('superadmin.')->middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard', [SuperAdminDashboardController::class, 'index']);
    Route::get('sellers/applications', [SellerApplicationController::class, 'index'])->name('sellers.applications.index');
    Route::post('sellers/applications/{application}', [SellerApplicationController::class, 'review'])->name('sellers.applications.review');

    // User management
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
    Route::get('users/{user}/reset-password', [UserController::class, 'resetPasswordForm'])->name('users.reset-password');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password.store');

    // Admin management
    Route::get('admins', [UserController::class, 'admins'])->name('admins.index');

    // Role management
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});
