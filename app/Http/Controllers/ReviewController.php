<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create($orderNumber, $productId)
    {
        $order = auth()->user()->orders()->where('order_number', $orderNumber)
            ->where('status', 'completed')
            ->firstOrFail();

        $orderItem = $order->items()->where('product_id', $productId)->firstOrFail();

        $alreadyReviewed = Review::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->exists();

        return view('storefront.reviews.create', compact('order', 'productId', 'alreadyReviewed'));
    }

    public function store(Request $request, $orderNumber, $productId)
    {
        $order = auth()->user()->orders()->where('order_number', $orderNumber)
            ->where('status', 'completed')
            ->firstOrFail();

        // Only allow reviewing products actually purchased & delivered.
        $order->items()->where('product_id', $productId)->firstOrFail();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $order, $productId) {
            Review::updateOrCreate(
                ['order_id' => $order->id, 'product_id' => $productId, 'user_id' => auth()->id()],
                ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'is_visible' => true]
            );

            $product = Product::find($productId);
            if ($product) {
                $product->load('reviews');
                $avg = round($product->reviews()->avg('rating') ?? 0, 2);
                $count = $product->reviews()->count();
                $product->update(['rating_avg' => $avg, 'rating_count' => $count]);
            }
        });

        return redirect()->route('orders.show', $order->order_number)
            ->with('success', 'Thank you for your review!');
    }
}
