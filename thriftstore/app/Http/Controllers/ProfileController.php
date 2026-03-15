<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AccountDeletionRequest;
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
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
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

        $deletionRequestPending = $user ? AccountDeletionRequest::hasPending($user->id) : false;

        return view('profile.edit', [
            'user' => $user,
            'purchaseStats' => $purchaseStats,
            'deletionRequestPending' => $deletionRequestPending,
        ]);
    }

    /**
     * Submit an account deletion request (C3 v1.4). Admin processes manually.
     */
    public function requestDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (AccountDeletionRequest::hasPending($user->id)) {
            return Redirect::route('profile.edit')->with('status', 'deletion-request-pending');
        }

        AccountDeletionRequest::create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return Redirect::route('profile.edit')->with('status', 'deletion-request-submitted');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        // B1 - v1.3: customers can change email freely; no re-verification required
        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
