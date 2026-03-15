<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Add one unit of a product to the cart (session). Used from product detail page.
     */
    public function add(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $product = Product::query()
            ->with('seller')
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved')->where('is_open', true))
            ->findOrFail($id);

        $cart = $request->session()->get('cart', []);
        $key = (string) $product->id;
        if (! isset($cart[$key]) && count($cart) >= 50) {
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'cart-full',
                    'message' => __('Cart is full (max 50 items).'),
                    'count' => array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart)),
                ], 422);
            }

            return back()->with('error', __('Cart is full (max 50 items).'));
        }
        $currentQty = $cart[$key]['quantity'] ?? 0;
        $newQty = min($currentQty + 1, $product->stock);
        $cart[$key] = [
            'product_id' => $product->id,
            'seller_id'  => $product->seller_id,
            'name'       => $product->name,
            'price'      => (float) ($product->sale_price ?? $product->price),
            'image_path' => $product->image_path,
            'quantity'   => $newQty,
        ];
        $request->session()->put('cart', $cart);

        $count = array_sum(array_map(fn ($row) => (int) ($row['quantity'] ?? 0), $cart));

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'added-to-cart',
                'count' => $count,
            ]);
        }

        return back()->with('status', 'added-to-cart');
    }
}
