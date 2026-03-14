<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Auth\AdminAuthenticatedSessionController;
use App\Http\Controllers\Auth\SellerAuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'customer.catalog')->name('catalog');

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::view('/faq', 'faq')->name('faq');

// Admin login (guest; uses admin guard / admin_session cookie)
Route::get('/admin/login', [AdminAuthenticatedSessionController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthenticatedSessionController::class, 'store'])->name('admin.login.store');

// Seller login (guest; uses seller guard / seller_session cookie)
Route::get('/seller/login', [SellerAuthenticatedSessionController::class, 'create'])->name('seller.login');
Route::post('/seller/login', [SellerAuthenticatedSessionController::class, 'store'])->name('seller.login.store');

// Admin routes (auth:admin — only admin guard)
Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::view('/sellers', 'admin.sellers')->name('admin.sellers');
    Route::view('/customers', 'admin.customers')->name('admin.customers');
    Route::view('/messages', 'admin.messages')->name('admin.messages');
    Route::get('/reports', ReportsController::class)->name('admin.reports');
    Route::get('/reports/export', [ReportsController::class, 'exportAll'])->name('admin.reports.export-all');
    Route::get('/reports/payments/export', [ReportsController::class, 'exportPayments'])->name('admin.reports.payments.export');
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

// Seller routes (auth:seller — only seller guard)
Route::middleware(['auth:seller'])->prefix('seller')->group(function () {
    Route::view('/status', 'seller.status')->name('seller.status');
    Route::view('/dashboard', 'seller.dashboard')->middleware('seller.approved')->name('seller.dashboard');
    Route::view('/store', 'seller.store')->middleware('seller.approved')->name('seller.store');
    Route::view('/products', 'seller.products')->middleware('seller.approved')->name('seller.products');
    Route::view('/orders', 'seller.orders')->middleware('seller.approved')->name('seller.orders');
    Route::view('/reviews', 'seller.reviews')->middleware('seller.approved')->name('seller.reviews');
    Route::get('/orders/{order}/print', function (\App\Models\Order $order) {
        $sellerUser = auth('seller')->user();
        $seller = $sellerUser?->seller;
        if (! $seller || $order->seller_id !== $seller->id) {
            abort(403);
        }
        $order->load(['customer', 'items.product', 'seller']);
        return view('seller.packing-slip', ['order' => $order]);
    })->middleware('seller.approved')->name('seller.orders.print');
    Route::view('/payments', 'seller.payments')->middleware('seller.approved')->name('seller.payments');
    Route::view('/reports', 'seller.reports')->middleware('seller.approved')->name('seller.reports');
    Route::view('/messages', 'seller.messages')->middleware('seller.approved')->name('seller.messages');
    Route::post('/notifications/read-all', function () {
        $user = auth('seller')->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
        return back();
    })->name('seller.notifications.read-all');
    Route::get('/message-admin', [App\Http\Controllers\Seller\MessageAdminController::class, 'create'])->middleware('seller.approved')->name('seller.message-admin');
    Route::post('/message-admin', [App\Http\Controllers\Seller\MessageAdminController::class, 'store'])->middleware('seller.approved')->name('seller.message-admin.store');
    Route::post('/logout', [SellerAuthenticatedSessionController::class, 'destroy'])->name('seller.logout');
});

// Customer routes (auth:web — default guard for customers)
Route::middleware(['auth:web', 'role:customer'])->group(function () {
    Route::view('/dashboard', 'customer.dashboard')->name('customer.dashboard');
    Route::view('/cart', 'customer.cart')->name('customer.cart');
    Route::view('/checkout', 'customer.checkout')->name('customer.checkout');
    Route::view('/orders', 'customer.orders')->name('customer.orders');
    Route::view('/reviews', 'customer.reviews')->name('customer.reviews');
    Route::view('/wishlist', 'customer.wishlist')->name('customer.wishlist');
    Route::view('/messages', 'customer.messages')->name('customer.messages');
    Route::post('/notifications/read-all', function () {
        $user = auth('web')->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
        return back();
    })->name('customer.notifications.read-all');
});

// Profile (any authenticated user via web guard; customers use /login)
Route::middleware('auth:web')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/addresses', function (\Illuminate\Http\Request $request) {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $address = $user->addresses()->create($data + ['is_default' => $isDefault]);

        if ($isDefault) {
            $user->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        return back()->with('status', 'address-saved');
    })->name('profile.addresses.store');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
