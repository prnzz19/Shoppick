<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

// Preserve the existing marketplace route name for shopping links and auth redirects.
Route::get('/shop', HomeController::class)->name('home');
Route::get('/', \App\Http\Controllers\LandingController::class)->name('landing');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('category/{category}', function ($category) {
    \App\Models\Category::active()->findOrFail($category);
    return redirect()->route('products.index', ['category' => $category]);
})->name('products.category');

Route::get('search/autocomplete', [ProductController::class, 'autocomplete'])->name('search.autocomplete');
Route::get('product/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('shop/{slug}', [ShopController::class, 'show'])->name('shops.show');
