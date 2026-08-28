@extends('layouts.storefront')

@section('title', 'Write a Review')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-6">
    <div class="card p-6">
        <h1 class="text-xl font-bold text-navy-800">Write a Review</h1>
        <p class="mt-1 text-sm text-slate-500">Order {{ $order->order_number }}</p>

        @if($alreadyReviewed)
            <div class="alert-info mt-4">You have already reviewed this product.</div>
            <a href="{{ route('orders.show', $order->order_number) }}" class="btn-ghost mt-4">Back to Order</a>
        @else
        <form method="POST" action="{{ route('review.store', [$order->order_number, $productId]) }}">
            @csrf

            <div class="mt-6">
                <p class="label">Your Rating</p>
                <div class="flex gap-2">
                    @for($i=1;$i<=5;$i++)
                        <button type="button" data-star="{{ $i }}" onclick="setRating({{ $i }})" class="star-btn text-3xl text-slate-300 hover:text-sun-400">
                            <svg class="h-9 w-9 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </button>
                    @endfor
                </div>
                <input type="hidden" name="rating" id="rating-input" value="5">
                @error('rating')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-6">
                <label for="comment" class="label">Your Review</label>
                <textarea name="comment" id="comment" rows="4" class="input" placeholder="Share your experience with this product...">{{ old('comment') }}</textarea>
                @error('comment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn-primary">Submit Review</button>
                <a href="{{ route('orders.show', $order->order_number) }}" class="btn-ghost">Cancel</a>
            </div>
        </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    function setRating(n) {
        document.getElementById('rating-input').value = n;
        document.querySelectorAll('.star-btn').forEach((btn, i) => {
            btn.classList.toggle('text-sun-400', i < n);
            btn.classList.toggle('text-slate-300', i >= n);
        });
    }
</script>
@endpush
