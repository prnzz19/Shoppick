<?php

use App\Http\Controllers\MarketplaceChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','registration.approved'])->prefix('chat')->name('chat.')->group(function () {
    Route::get('/', [MarketplaceChatController::class,'index'])->name('index');
    Route::post('buyer', [MarketplaceChatController::class,'startBuyer'])->name('buyer.start');
    Route::get('{conversation}', [MarketplaceChatController::class,'show'])->name('show');
    Route::post('{conversation}/messages', [MarketplaceChatController::class,'send'])->middleware('throttle:30,1')->name('messages.store');
});
