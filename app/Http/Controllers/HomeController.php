<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $categories = Category::whereNull('parent_id')->active()->orderBy('sort_order')->with('children')->get();

        $flashDeals = Product::active()
            ->where('discount', '>', 0)
            ->where('stock', '>', 0)
            ->orderBy('discount', 'desc')
            ->with('images')
            ->take(8)
            ->get();

        $featured = Product::active()->where('is_featured', true)->with('images')->take(8)->get();

        $popular = Product::active()->orderBy('sold_count', 'desc')->with('images')->take(8)->get();

        $newArrivals = Product::active()->latest()->with('images')->take(8)->get();

        $vouchers = Voucher::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->take(4)
            ->get();

        return view('storefront.home', compact(
            'categories', 'flashDeals', 'featured', 'popular', 'newArrivals', 'vouchers'
        ));
    }
}
