<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\CompleteProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.submit');

    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::get('register/buyer', [RegisterController::class, 'showBuyerRegistrationForm'])->name('register.buyer');
    Route::post('register/buyer', [RegisterController::class, 'register'])->name('register.submit');
    Route::get('register/seller', [RegisterController::class, 'showSellerRegistrationForm'])->name('register.seller');
    Route::post('register/seller', [RegisterController::class, 'registerSeller'])->name('register.seller.submit');
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
Route::middleware('auth')->group(function () {
    Route::get('complete-profile', [CompleteProfileController::class, 'show'])->name('profile.complete');
    Route::post('complete-profile', [CompleteProfileController::class, 'update'])->name('profile.complete.update');
    Route::get('complete-seller-registration', [CompleteProfileController::class, 'showSeller'])->name('profile.complete.seller');
    Route::post('complete-seller-registration', [CompleteProfileController::class, 'updateSeller'])->name('profile.complete.seller.update');
});
