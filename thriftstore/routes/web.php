<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Auth\SellerAuthenticatedSessionController;
use App\Http\Controllers\Webhooks\CourierTrackingWebhookController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AddressController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::view('/', 'customer.home')->name('catalog');

// NEXT-18 — Courier webhook ingestion endpoint (token-protected, no CSRF).
Route::post('/webhooks/courier/tracking', CourierTrackingWebhookController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.courier.tracking');

// B1 v1.4 — Product detail (view count incremented once per session)
Route::get('/product/{id}', [App\Http\Controllers\ProductController::class, 'show'])->name('product.show');
Route::post('/cart/add/{id}', [App\Http\Controllers\CartController::class, 'add'])->middleware(['auth:web', 'role:customer'])->name('cart.add');
Route::post('/product/{id}/report', [App\Http\Controllers\ProductReportController::class, 'store'])->middleware(['auth:web', 'role:customer'])->name('product.report.store');
Route::post('/product/{id}/review', [App\Http\Controllers\ProductReviewController::class, 'store'])->middleware(['auth:web', 'role:customer'])->name('product.review.store');

// B2 v1.4 — Public store profile (verified badge, business hours)
Route::get('/store/{store_name}', [App\Http\Controllers\StoreController::class, 'show'])->name('store.show');

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::view('/faq', 'faq')->name('faq');

// A4 v1.4 — Editable legal pages (content from System Settings)
Route::get('/privacy', [LegalPageController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('/cookie-settings', [LegalPageController::class, 'cookieSettings'])->name('legal.cookie-settings');

// Admin login (guest; uses admin guard / admin_session cookie)
Route::get('/admin/login', [AdminAuthenticatedSessionController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthenticatedSessionController::class, 'store'])->name('admin.login.store');

// Seller login and register (guest; uses seller guard / seller_session cookie)
Route::get('/seller/login', [SellerAuthenticatedSessionController::class, 'create'])->name('seller.login');
Route::post('/seller/login', [SellerAuthenticatedSessionController::class, 'store'])->name('seller.login.store');
Route::get('/seller/register', [App\Http\Controllers\Auth\SellerRegisteredUserController::class, 'create'])->name('seller.register');
Route::post('/seller/register', [App\Http\Controllers\Auth\SellerRegisteredUserController::class, 'store'])->name('seller.register.store');
Route::get('/seller/register/check-email', [App\Http\Controllers\Auth\SellerRegisteredUserController::class, 'checkEmail'])->name('seller.register.check-email');
Route::get('/seller/register/check-store-name', [App\Http\Controllers\Auth\SellerRegisteredUserController::class, 'checkStoreName'])->name('seller.register.check-store-name');



// Admin routes (auth:admin — only admin guard)
Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::view('/sellers', 'admin.sellers')->name('admin.sellers');
    Route::view('/customers', 'admin.customers')->name('admin.customers');
    Route::view('/messages', 'admin.messages')->name('admin.messages');
    Route::get('/reports', ReportsController::class)->name('admin.reports');
    Route::get('/reports/export', [ReportsController::class, 'exportAll'])->name('admin.reports.export-all');
    Route::get('/reports/payments/export', [ReportsController::class, 'exportPayments'])->name('admin.reports.payments.export');
    Route::view('/orders', 'admin.orders')->name('admin.orders');
    Route::view('/product-reports', 'admin.product-reports')->name('admin.product-reports');
    Route::view('/deletion-requests', 'admin.deletion-requests')->name('admin.deletion-requests');
    Route::view('/payments', 'admin.payments')->name('admin.payments');
    Route::view('/settings', 'admin.settings')->name('admin.settings');
    Route::post('/notifications/read-all', function () {
        $user = auth('admin')->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
        return back();
    })->name('admin.notifications.read-all');
    Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('admin.logout');
});

// Seller routes (auth:seller). Approved routes also require verified email (B1 - v1.3).
Route::middleware(['auth:seller'])->prefix('seller')->group(function () {
    Route::view('/status', 'seller.status')->name('seller.status');
    Route::view('/dashboard', 'seller.dashboard')->middleware(['seller.approved'])->name('seller.dashboard');
    Route::view('/store', 'seller.store')->middleware(['seller.approved'])->name('seller.store');
    Route::view('/products', 'seller.products')->middleware(['seller.approved'])->name('seller.products');
    Route::view('/orders', 'seller.orders')->middleware(['seller.approved'])->name('seller.orders');
    Route::view('/reviews', 'seller.reviews')->middleware(['seller.approved'])->name('seller.reviews');
    Route::get('/orders/{order}/print', function (\App\Models\Order $order) {
        $sellerUser = auth('seller')->user();
        $seller = $sellerUser?->seller;
        if (! $seller || $order->seller_id !== $seller->id) {
            abort(403);
        }
        $order->load(['customer', 'items.product', 'seller']);
        return view('seller.packing-slip', ['order' => $order]);
    })->middleware(['seller.approved'])->name('seller.orders.print');
    Route::view('/payments', 'seller.payments')->middleware(['seller.approved'])->name('seller.payments');
    Route::view('/reports', 'seller.reports')->middleware(['seller.approved'])->name('seller.reports');
    Route::get('/reports/export', [App\Http\Controllers\Seller\SellerReportsController::class, 'export'])->middleware(['seller.approved'])->name('seller.reports.export');
    Route::view('/messages', 'seller.messages')->name('seller.messages');
    Route::post('/notifications/read-all', function () {
        $user = auth('seller')->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
        return back();
    })->middleware(['seller.approved'])->name('seller.notifications.read-all');
    Route::post('/subscription', [App\Http\Controllers\Seller\SubscriptionController::class, 'store'])->name('seller.subscription.store');
    Route::post('/logout', [SellerAuthenticatedSessionController::class, 'destroy'])->name('seller.logout');
});

// Customer routes (auth:web — default guard for customers). Checkout requires verified email (B1 - v1.3).
Route::middleware(['auth:web', 'role:customer'])->group(function () {
    Route::view('/products', 'customer.catalog')->name('customer.dashboard');
    Route::redirect('/dashboard', '/products');
    Route::view('/cart', 'customer.cart')->name('customer.cart');
    Route::view('/checkout', 'customer.checkout')->name('customer.checkout');
    Route::view('/orders', 'customer.orders')->name('customer.orders');
    Route::get('/orders/{order}/receipt', [App\Http\Controllers\OrderReceiptController::class, 'download'])->name('customer.orders.receipt');
    Route::view('/reviews', 'customer.reviews')->name('customer.reviews');
    Route::view('/wishlist', 'customer.wishlist')->name('customer.wishlist');
    Route::post('/wishlist/toggle/{id}', function (\Illuminate\Http\Request $request, int $id) {
        $product = \App\Models\Product::where('is_active', true)->findOrFail($id);
        $w = \App\Models\Wishlist::where('customer_id', $request->user()->id)->where('product_id', $product->id)->first();
        $wished = false;
        if ($w) {
            $w->delete();
            $status = 'wishlist-removed';
        } else {
            \App\Models\Wishlist::firstOrCreate(['customer_id' => $request->user()->id, 'product_id' => $product->id]);
            $wished = true;
            $status = 'wishlist-added';
        }

        $count = \App\Models\Wishlist::where('customer_id', $request->user()->id)->count();

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => $status,
                'wished' => $wished,
                'count' => $count,
            ]);
        }

        return redirect()->back()->with('status', $status);
    })->name('wishlist.toggle');
    Route::view('/messages', 'customer.messages')->name('customer.messages');
    Route::post('/notifications/read-all', function () {
        $user = auth('web')->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
        return back();
    })->name('customer.notifications.read-all');
});

// Profile (any authenticated user via web or seller guard)
Route::middleware('auth:web,seller')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/deletion-request', [ProfileController::class, 'requestDeletion'])->name('profile.deletion-request');
    Route::post('/profile/addresses', [AddressController::class, 'store'])->name('profile.addresses.store');
    Route::patch('/profile/addresses/{address}', [AddressController::class, 'update'])->name('profile.addresses.update');
    Route::delete('/profile/addresses/{address}', [AddressController::class, 'destroy'])->name('profile.addresses.destroy');
    Route::post('/profile/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('profile.addresses.set-default');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// Support & Legal Pages (fix RouteNotFoundException)
Route::view('/support/faq', 'support.faq')->name('support.faq');
Route::view('/support/contact', 'support.contact')->name('support.contact');
Route::view('/support/privacy', 'support.privacy')->name('support.privacy');
Route::view('/support/terms', 'support.terms')->name('support.terms');
Route::view('/support/cookies', 'support.cookies')->name('support.cookies');

// --- Dynamic Search Suggestions ---
use App\Http\Controllers\SearchController;
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

require __DIR__.'/auth.php';
