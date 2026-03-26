<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    /**
     * Public store profile page (B2 v1.4, G1 v1.4, G2 v1.4). Store header and stats.
     */
    public function show(Request $request, $store_name): View
    {
        $seller = Seller::query()
            ->with('user')
            ->where('store_name', $store_name)
            ->where('status', 'approved')
            ->firstOrFail();

        $storeRating = (float) Review::query()
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->where('products.seller_id', $seller->id)
            ->avg('reviews.rating');
        $reviewCount = (int) Review::query()
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->where('products.seller_id', $seller->id)
            ->count();

        $activeProductsCount = (int) Product::query()
            ->where('seller_id', $seller->id)
            ->where('is_active', true)
            ->count();

        $completedOrdersCount = (int) Order::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
                Order::STATUS_RECEIVED,
                Order::STATUS_COMPLETED
            ])
            ->count();

        return view('store.show', [
            'seller' => $seller,
            'catalogUrl' => route('catalog', ['seller' => $seller->id]),
            'storeRating' => round($storeRating, 1),
            'reviewCount' => $reviewCount,
            'memberSince' => $seller->created_at,
            'activeProductsCount' => $activeProductsCount,
            'completedOrdersCount' => $completedOrdersCount,
        ]);
    }
}
