<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('category/{category}', function ($category) {
    return redirect()->route('products.index', ['category' => $category]);
})->name('products.category');

Route::get('search/autocomplete', [ProductController::class, 'autocomplete'])->name('search.autocomplete');
Route::get('product/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('shop/{slug}', [ShopController::class, 'show'])->name('shops.show');
