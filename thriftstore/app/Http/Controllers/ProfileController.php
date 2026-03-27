<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     * C3 v1.4: Pass purchase history stats for customers.
     * Supports both web (customer) and seller guards.
     */
    public function edit(Request $request): View
    {
        $user = Auth::guard('web')->user() ?? Auth::guard('seller')->user();
        $purchaseStats = null;

        if ($user && $user->hasRole('customer')) {
            $totalOrders = Order::query()
                ->where('customer_id', $user->id)
                ->count();
            $totalSpent = Order::query()
                ->where('customer_id', $user->id)
                ->whereIn('status', ['delivered', 'shipped'])
                ->sum('total_amount');
            $favoriteCategory = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('orders.customer_id', $user->id)
                ->whereIn('orders.status', ['delivered', 'shipped'])
                ->whereNotNull('products.category')
                ->where('products.category', '!=', '')
                ->select('products.category', DB::raw('SUM(order_items.quantity) as total_qty'))
                ->groupBy('products.category')
                ->orderByDesc('total_qty')
                ->first()?->category;

            $purchaseStats = [
                'total_orders' => $totalOrders,
                'total_spent' => (float) $totalSpent,
                'favorite_category' => $favoriteCategory ?? null,
            ];
        }


        return view('profile.edit', [
            'user' => $user,
            'purchaseStats' => $purchaseStats,
            'deletionRequestPending' => false,
        ]);
    }



    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = Auth::guard('web')->user() ?? Auth::guard('seller')->user();
        
        if (!$user) {
            abort(403);
        }

        $user->fill($request->validated());

        // B1 - v1.3: customers can change email freely; no re-verification required
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }


}
