<?php
use App\Http\Controllers\Rider\{RiderController, RiderLoginController};use Illuminate\Support\Facades\Route;
Route::prefix('rider')->name('rider.')->middleware('guest')->group(function () {
 Route::get('login', [RiderLoginController::class, 'create'])->name('login');
 Route::post('login', [RiderLoginController::class, 'store'])->middleware('throttle:20,1')->name('login.submit');
});
Route::post('rider/logout', [RiderLoginController::class, 'destroy'])->middleware('auth')->name('rider.logout');
Route::prefix('rider')->name('rider.')->middleware(['auth',\App\Http\Middleware\EnsureRiderPortalAccess::class,'registration.approved','role:rider','rider.active'])->group(function(){
 Route::get('/', fn () => redirect()->route('rider.dashboard'));
 Route::get('active-delivery',[RiderController::class,'active'])->name('active');
 Route::post('notifications/{notification}',[RiderController::class,'openNotification'])->whereNumber('notification')->name('notifications.read');
 Route::get('tracking',[RiderController::class,'tracking'])->name('tracking');
 Route::get('shipments/{shipment}/tracking',[RiderController::class,'track'])->name('shipments.tracking');
 Route::get('dashboard',[RiderController::class,'dashboard'])->name('dashboard');Route::get('jobs',[RiderController::class,'jobs'])->name('jobs');Route::get('pickup-jobs',[RiderController::class,'jobs'])->defaults('type','pickup')->name('pickup-jobs');Route::get('delivery-jobs',[RiderController::class,'jobs'])->defaults('type','delivery')->name('delivery-jobs');Route::get('history',[RiderController::class,'history'])->name('history');Route::get('proof-of-delivery',[RiderController::class,'pods'])->name('pods');Route::get('proof-of-delivery/{pod}/file',[RiderController::class,'podFile'])->name('pods.file');Route::get('cod',[RiderController::class,'cod'])->name('cod');
 Route::get('notifications',[RiderController::class,'notifications'])->name('notifications');Route::get('notifications/{notification}',[RiderController::class,'openNotification'])->name('notifications.open');Route::post('notifications/read-all',[RiderController::class,'readAllNotifications'])->name('notifications.read-all');Route::get('messages',[RiderController::class,'messages'])->name('messages');Route::post('messages',[RiderController::class,'sendMessage'])->name('messages.send');
 Route::get('profile',[RiderController::class,'profile'])->name('profile');Route::put('profile',[RiderController::class,'updateProfile'])->name('profile.update');Route::get('profile/license/{side}',[RiderController::class,'licenseFile'])->name('profile.license.file');Route::get('settings',[RiderController::class,'settings'])->name('settings');Route::put('settings',[RiderController::class,'updateSettings'])->name('settings.update');Route::put('password',[RiderController::class,'password'])->name('password.update');Route::put('availability',[RiderController::class,'availability'])->name('availability');
 Route::get('shipments/{shipment}',[RiderController::class,'show'])->name('shipments.show');Route::post('shipments/{shipment}/decline',[RiderController::class,'decline'])->name('shipments.decline');Route::post('shipments/{shipment}/parcel-action',[RiderController::class,'parcelAction'])->name('shipments.parcel-action');Route::post('shipments/{shipment}/cod-collection',[RiderController::class,'collectCod'])->name('shipments.cod-collection');Route::post('shipments/{shipment}/pod',[RiderController::class,'pod'])->name('shipments.pod');
});
