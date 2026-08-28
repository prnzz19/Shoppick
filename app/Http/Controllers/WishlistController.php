<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $wishlist = Wishlist::firstOrCreate(['user_id' => auth()->id()]);
        $items = $wishlist->items()->with('product.images')->get()
            ->filter(fn ($i) => $i->product !== null);

        return view('storefront.wishlist.index', compact('items'));
    }

    public function toggle(Request $request)
    {
        $request->validate(['product_id' => ['required', 'exists:products,id']]);

        $wishlist = Wishlist::firstOrCreate(['user_id' => auth()->id()]);
        $exists = $wishlist->items()->where('product_id', $request->input('product_id'))->exists();

        if ($exists) {
            $wishlist->items()->where('product_id', $request->input('product_id'))->delete();
            $added = false;
        } else {
            $wishlist->items()->create(['product_id' => $request->input('product_id')]);
            $added = true;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'added' => $added,
                'count' => $wishlist->items()->count(),
            ]);
        }

        return back()->with('success', $added ? 'Added to wishlist' : 'Removed from wishlist');
    }

    public function remove($itemId)
    {
        Wishlist::firstOrCreate(['user_id' => auth()->id()])
            ->items()->where('id', $itemId)->delete();

        return back()->with('success', 'Removed from wishlist');
    }

    public function moveToCart(Request $request, $itemId)
    {
        $request->validate(['quantity' => ['integer', 'min:1']]);

        $wishlist = Wishlist::firstOrCreate(['user_id' => auth()->id()]);
        $item = $wishlist->items()->with('product')->findOrFail($itemId);

        $product = $item->product;
        if (! $product || ! $product->is_active) {
            return back()->with('error', 'This product is not available.');
        }

        app(\App\Services\CartService::class)->add(
            auth()->id(),
            $product->id,
            null,
            min($request->input('quantity', 1), $product->stock)
        );

        $item->delete();

        return back()->with('success', 'Moved to cart');
    }
}
