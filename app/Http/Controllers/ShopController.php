<?php
namespace App\Http\Controllers;
use App\Models\Store;
class ShopController extends Controller { public function show(string $slug) { $store=Store::active()->where('slug',$slug)->firstOrFail(); $products=$store->products()->active()->with(['images','category'])->paginate(20); return view('storefront.shops.show',compact('store','products')); } }
