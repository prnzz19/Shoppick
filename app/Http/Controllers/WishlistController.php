<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Exception;
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
        $items = $wishlist->items()->with(['product.images', 'product.store', 'product.variants'])->get()
            ->filter(fn ($i) => $i->product !== null);
        $availableProductIds = Product::publiclyVisible()
            ->whereIn('id', $items->pluck('product_id'))
            ->pluck('id');

        return view('storefront.wishlist.index', compact('items', 'availableProductIds'));
    }

    public function toggle(Request $request)
    {
        abort_unless($request->user()->isBuyer(), 403, 'Only Buyer accounts can use a wishlist.');
        $request->validate(['product_id' => ['required', 'exists:products,id']]);

        $wishlist = Wishlist::firstOrCreate(['user_id' => auth()->id()]);
        $exists = $wishlist->items()->where('product_id', $request->input('product_id'))->exists();

        if ($exists) {
            $wishlist->items()->where('product_id', $request->input('product_id'))->delete();
            $added = false;
        } else {
            if (! Product::publiclyVisible()->whereKey($request->input('product_id'))->exists()) {
                return response()->json(['success' => false, 'message' => 'This product is currently unavailable.'], 422);
            }
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

        return back()->with('success', $added ? 'Added to your wishlist.' : 'Removed from your wishlist.');
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

        $product = Product::publiclyVisible()->with('variants')->find($item->product_id);
        if (! $product) {
            return back()->with('error', 'This product is currently unavailable.');
        }
        if ($product->variants->isNotEmpty()) {
            return redirect()->route('products.show', $product->slug)
                ->with('error', 'Please select a product option.');
        }

        try {
            app(\App\Services\CartService::class)->add(
                auth()->id(),
                $product->id,
                null,
                $request->input('quantity', 1)
            );
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Product added to cart.');
    }
}
