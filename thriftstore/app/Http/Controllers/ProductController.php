<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Show product detail. Increment view count once per session (B1 v1.4).
     * E2 v1.4: Pass reviews, star breakdown, canReview, eligibleOrderId for ratings section.
     */
    public function show(Request $request, int $id): View
    {
        $product = Product::query()
            ->with(['seller.user', 'reviews' => fn ($q) => $q->with('customer')->orderByDesc('created_at')])
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount('reviews')
            ->where('is_active', true)
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved')->where('is_open', true))
            ->findOrFail($id);

        $sessionKey = 'product_views';
        $viewed = $request->session()->get($sessionKey, []);
        if (! in_array($id, $viewed, true)) {
            $product->increment('views');
            $request->session()->push($sessionKey, $id);
        }

        $inWishlist = false;
        $canReview = false;
        $eligibleOrderId = null;
        $starBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($product->reviews as $r) {
            if (isset($starBreakdown[$r->rating])) {
                $starBreakdown[$r->rating]++;
            }
        }
        $totalReviews = $product->reviews_count;
        $maxBar = $totalReviews > 0 ? max($starBreakdown) : 1;

        if ($request->user() && $request->user()->hasRole('customer')) {
            $inWishlist = Wishlist::where('customer_id', $request->user()->id)
                ->where('product_id', $product->id)->exists();

            $deliveredOrderWithProduct = Order::query()
                ->where('customer_id', $request->user()->id)
                ->where('status', 'delivered')
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->whereDoesntHave('reviews', fn ($q) => $q->where('product_id', $product->id))
                ->first();
            if ($deliveredOrderWithProduct) {
                $canReview = true;
                $eligibleOrderId = $deliveredOrderWithProduct->id;
            }
        }

        return view('product.show', [
            'product' => $product,
            'inWishlist' => $inWishlist,
            'starBreakdown' => $starBreakdown,
            'maxBar' => $maxBar,
            'canReview' => $canReview,
            'eligibleOrderId' => $eligibleOrderId,
        ]);
    }
}
