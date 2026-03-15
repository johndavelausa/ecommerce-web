<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function store(Request $request, int $productId): RedirectResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        if (! $user || ! $user->hasRole('customer')) {
            abort(403);
        }

        $order = Order::query()
            ->where('id', $request->order_id)
            ->where('customer_id', $user->id)
            ->where('status', 'delivered')
            ->firstOrFail();

        if (! $order->items()->where('product_id', $productId)->exists()) {
            throw ValidationException::withMessages(['order_id' => ['This order does not contain this product.']]);
        }

        $product = Product::where('id', $productId)->where('is_active', true)->firstOrFail();

        if (Review::where('customer_id', $user->id)->where('product_id', $productId)->where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['body' => ['You have already reviewed this item from this order.']]);
        }

        Review::create([
            'customer_id' => $user->id,
            'product_id' => $productId,
            'order_id' => $order->id,
            'rating' => $request->rating,
            'body' => $request->body,
        ]);

        return redirect()->route('product.show', $productId)->with('status', 'review-submitted');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $review = Review::where('id', $id)->where('customer_id', $request->user()->id)->firstOrFail();
        $review->update(['rating' => $request->rating, 'body' => $request->body]);

        return redirect()->route('product.show', $review->product_id)->with('status', 'review-updated');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $review = Review::where('id', $id)->where('customer_id', $request->user()->id)->firstOrFail();
        $productId = $review->product_id;
        $review->delete();

        return redirect()->route('product.show', $productId)->with('status', 'review-deleted');
    }
}
