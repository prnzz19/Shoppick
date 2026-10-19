<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\CompleteProfileController;
use App\Http\Controllers\Auth\RiderApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('terms',fn()=>view('auth.buyer-policy',['title'=>'Terms','content'=>app(\App\Services\SystemSettings::class)->get('legal.terms')]))->name('buyer.terms');
Route::get('privacy',fn()=>view('auth.buyer-policy',['title'=>'Privacy Policy','content'=>app(\App\Services\SystemSettings::class)->get('legal.privacy')]))->name('buyer.privacy');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.submit');

    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::get('register/buyer', [RegisterController::class, 'showBuyerRegistrationForm'])->name('register.buyer');
    Route::post('register/buyer', [RegisterController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');
    Route::get('register/rider', fn () => redirect()->route('rider.register'))->name('register.rider');
    Route::post('register/rider', [RiderApplicationController::class, 'store'])->middleware('throttle:10,1')->name('register.rider.submit');
    Route::get('register/logistics', [\App\Http\Controllers\Auth\LogisticsRegistrationController::class, 'create'])->name('register.logistics');
    Route::post('register/logistics', [\App\Http\Controllers\Auth\LogisticsRegistrationController::class, 'store'])->middleware('throttle:5,1')->name('register.logistics.submit');
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
Route::middleware('auth')->group(function () {
    Route::get('verify-buyer-email',[\App\Http\Controllers\Auth\BuyerVerificationController::class,'show'])->name('buyer.verification');
    Route::post('verify-buyer-email',[\App\Http\Controllers\Auth\BuyerVerificationController::class,'verify'])->middleware('throttle:10,1')->name('buyer.verification.verify');
    Route::post('verify-buyer-email/resend',[\App\Http\Controllers\Auth\BuyerVerificationController::class,'resend'])->middleware('throttle:5,1')->name('buyer.verification.resend');
    Route::get('verify-registration', [\App\Http\Controllers\Auth\RegistrationVerificationController::class, 'show'])->name('registration.verification');
    Route::post('verify-registration', [\App\Http\Controllers\Auth\RegistrationVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('registration.verification.verify');
    Route::post('verify-registration/resend', [\App\Http\Controllers\Auth\RegistrationVerificationController::class, 'resend'])->middleware('throttle:5,1')->name('registration.verification.resend');
    Route::get('rider-application/status', [RiderApplicationController::class, 'status'])->name('rider.application.status');
    Route::post('rider-application/resubmit', [RiderApplicationController::class, 'resubmit'])->name('rider.application.resubmit');
    Route::get('complete-profile', [CompleteProfileController::class, 'show'])->name('profile.complete');
    Route::post('complete-profile', [CompleteProfileController::class, 'update'])->name('profile.complete.update');
});

// Compatibility bookmarks lead to the authenticated application, never a second account.
Route::get('register/seller', fn () => redirect()->route('seller.apply'))->name('register.seller');
Route::post('register/seller', fn () => abort(410, 'Register as a Buyer, then apply from your account.'))->name('register.seller.submit');
Route::get('complete-seller-registration', fn () => redirect()->route('seller.apply'))->middleware('auth')->name('profile.complete.seller');
Route::post('complete-seller-registration', fn () => abort(410, 'Use the seller application from your Buyer account.'))->middleware('auth')->name('profile.complete.seller.update');
