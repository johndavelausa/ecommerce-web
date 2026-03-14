<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalProfit = (float) Payment::query()
            ->where('status', 'approved')
            ->sum('amount');

        $totalRevenue = (float) Order::query()
            ->whereIn('status', ['shipped', 'delivered'])
            ->sum('total_amount');

        $monthlyRevenue = Order::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->whereIn('status', ['shipped', 'delivered'])
            ->whereNotNull('created_at')
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $sellerRegistrations = Seller::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->whereNotNull('created_at')
            ->whereYear('created_at', now()->year)
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $totalApprovedSellers = (int) Seller::query()->where('status', 'approved')->count();
        $totalCustomers = (int) User::query()->whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count();

        $unreadMessages = (int) Message::query()
            ->where('is_read', false)
            ->where('sender_type', '!=', 'admin')
            ->count();

        $totalRegistrationFees = (float) Payment::query()
            ->where('status', 'approved')
            ->where('type', 'registration')
            ->sum('amount');

        $totalSubscriptionFees = (float) Payment::query()
            ->where('status', 'approved')
            ->where('type', 'subscription')
            ->sum('amount');

        $topProducts = OrderItem::query()
            ->selectRaw('products.id as product_id, products.name as product_name, sellers.store_name as store_name, SUM(order_items.quantity) as qty_sold')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('sellers', 'sellers.id', '=', 'orders.seller_id')
            ->where('orders.status', 'delivered')
            ->groupBy('products.id', 'products.name', 'sellers.store_name')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get();

        $topSellers = Order::query()
            ->selectRaw('sellers.id as seller_id, sellers.store_name as store_name, COUNT(orders.id) as completed_orders')
            ->join('sellers', 'sellers.id', '=', 'orders.seller_id')
            ->where('orders.status', 'delivered')
            ->groupBy('sellers.id', 'sellers.store_name')
            ->orderByDesc('completed_orders')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'totalProfit' => $totalProfit,
            'totalRevenue' => $totalRevenue,
            'monthlyRevenue' => $monthlyRevenue,
            'totalApprovedSellers' => $totalApprovedSellers,
            'totalCustomers' => $totalCustomers,
            'unreadMessages' => $unreadMessages,
            'totalRegistrationFees' => $totalRegistrationFees,
            'totalSubscriptionFees' => $totalSubscriptionFees,
            'topProducts' => $topProducts,
            'topSellers' => $topSellers,
            'sellerRegistrations' => $sellerRegistrations,
        ]);
    }
}
