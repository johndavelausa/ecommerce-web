<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Seller;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $totalSales = (float) Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->sum('total_amount');

        // Total Orders: count of all orders platform-wide (feature v1.2 - Admin #2)
        $totalOrders = (int) Order::query()->count();

        // Bad Orders: cancelled + refunded orders (feature v1.2 - Admin #5)
        $ordersCancelled = (int) Order::query()->where('status', 'cancelled')->count();
        $ordersRefunded = (int) Order::query()->where('refund_status', Order::REFUND_STATUS_COMPLETED)->count();
        $badOrdersCount = $ordersCancelled + $ordersRefunded;
        $badOrdersPercent = $totalOrders > 0 ? round(($badOrdersCount / $totalOrders) * 100, 1) : 0.0;

        // Order Status Breakdown: counts by status (feature v1.2 - Admin #3)
        $orderStatusBreakdown = Order::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $totalProfit = (float) Payment::query()
            ->where('status', 'approved')
            ->sum('amount');

        $totalRevenue = (float) Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->sum('total_amount');

        $monthlyRevenue = Order::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
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
        $totalSellers = (int) Seller::query()->count();
        $rejectedSellers = (int) Seller::query()->where('status', 'rejected')->count();

        // A1 - v1.4: Active vs Inactive Sellers (subscription status)
        $activeSellers = (int) Seller::query()->where('subscription_status', 'active')->count();
        $inactiveSellers = (int) Seller::query()
            ->whereIn('subscription_status', ['lapsed', 'grace_period'])
            ->count();

        // A1 - v1.4: Platform GMV (all orders except cancelled)
        $platformGmv = (float) Order::query()
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        // A1 - v1.4: Average Order Value (successful only)
        $deliveredCount = (int) Order::query()
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->count();
        $deliveredSum = (float) Order::query()
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->sum('total_amount');
        $averageOrderValue = $deliveredCount > 0 ? round($deliveredSum / $deliveredCount, 2) : 0.0;

        // A1 - v1.4: New Orders Today
        $newOrdersToday = (int) Order::query()
            ->whereDate('created_at', Carbon::today())
            ->count();

        $slaScope = (int) Order::query()
            ->whereIn('status', [
                Order::STATUS_PAID,
                Order::STATUS_TO_PACK,
                Order::STATUS_READY_TO_SHIP,
                Order::STATUS_PROCESSING,
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
                Order::STATUS_CANCELLED,
            ])
            ->count();

        $acceptedOrders = (int) Order::query()
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->count();

        $shipmentScope = (int) Order::query()
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->count();

        $onTimeShipments = (int) Order::query()
            ->whereIn('status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_OUT_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_COMPLETED,
            ])
            ->whereNotNull('shipped_at')
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, shipped_at) <= 48')
            ->count();

        $cancelledOrders = (int) Order::query()
            ->where('status', Order::STATUS_CANCELLED)
            ->count();

        $returnScope = (int) Order::query()
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_COMPLETED])
            ->count();

        $returnedOrders = (int) OrderDispute::query()
            ->whereIn('status', [
                OrderDispute::STATUS_RETURN_REQUESTED,
                OrderDispute::STATUS_RETURN_IN_TRANSIT,
                OrderDispute::STATUS_RETURN_RECEIVED,
                OrderDispute::STATUS_REFUND_PENDING,
                OrderDispute::STATUS_REFUND_COMPLETED,
            ])
            ->distinct('order_id')
            ->count('order_id');

        $sellerAcceptanceRate = $slaScope > 0 ? round(($acceptedOrders / $slaScope) * 100, 1) : 0.0;
        $onTimeShipRate = $shipmentScope > 0 ? round(($onTimeShipments / $shipmentScope) * 100, 1) : 0.0;
        $sellerCancellationRate = $slaScope > 0 ? round(($cancelledOrders / $slaScope) * 100, 1) : 0.0;
        $returnRate = $returnScope > 0 ? round(($returnedOrders / $returnScope) * 100, 1) : 0.0;

        // A1 - v1.4: Revenue This Month vs Last Month (% change)
        $revenueThisMonth = (float) Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_amount');
        $revenueLastMonth = (float) Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->whereMonth('created_at', Carbon::now()->subMonth()->month)
            ->whereYear('created_at', Carbon::now()->subMonth()->year)
            ->sum('total_amount');
        $revenueMonthChangePercent = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100.0 : 0.0);

        // Churn Rate: % of sellers who did not renew in the last month (feature v1.2 - Admin #6)
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        // Sellers lapse when (due_date + 7 days) has passed; first lapse day = due_date + 8
        $lapsedThisMonth = (int) Seller::query()
            ->where('subscription_status', 'lapsed')
            ->whereNotNull('subscription_due_date')
            ->whereBetween('subscription_due_date', [
                $startOfMonth->copy()->subDays(8),
                $endOfMonth->copy()->subDays(8),
            ])
            ->count();
        $activeOrGraceNow = (int) Seller::query()
            ->whereIn('subscription_status', ['active', 'grace_period'])
            ->count();
        $totalActiveLastMonth = $activeOrGraceNow + $lapsedThisMonth;
        $churnRate = $totalActiveLastMonth > 0
            ? round(($lapsedThisMonth / $totalActiveLastMonth) * 100, 1)
            : 0.0;

        $totalCustomers = (int) User::query()->whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count();
        $activeCustomersToday = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'customer'))
            ->whereDate('last_active_at', Carbon::today())
            ->orderByDesc('last_active_at')
            ->limit(10)
            ->get();

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
            ->whereIn('orders.status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->groupBy('products.id', 'products.name', 'sellers.store_name')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get();

        $topSellers = Order::query()
            ->selectRaw('sellers.id as seller_id, sellers.store_name as store_name, COUNT(orders.id) as completed_orders')
            ->join('sellers', 'sellers.id', '=', 'orders.seller_id')
            ->whereIn('orders.status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->groupBy('sellers.id', 'sellers.store_name')
            ->orderByDesc('completed_orders')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'totalSales' => $totalSales,
            'totalOrders' => $totalOrders,
            'badOrdersCount' => $badOrdersCount,
            'badOrdersPercent' => $badOrdersPercent,
            'orderStatusBreakdown' => $orderStatusBreakdown,
            'totalProfit' => $totalProfit,
            'totalRevenue' => $totalRevenue,
            'monthlyRevenue' => $monthlyRevenue,
            'totalApprovedSellers' => $totalApprovedSellers,
            'totalSellers' => $totalSellers,
            'rejectedSellers' => $rejectedSellers,
            'churnRate' => $churnRate,
            'totalCustomers' => $totalCustomers,
            'unreadMessages' => $unreadMessages,
            'totalRegistrationFees' => $totalRegistrationFees,
            'totalSubscriptionFees' => $totalSubscriptionFees,
            'topProducts' => $topProducts,
            'topSellers' => $topSellers,
            'sellerRegistrations' => $sellerRegistrations,
            'activeSellers' => $activeSellers,
            'inactiveSellers' => $inactiveSellers,
            'platformGmv' => $platformGmv,
            'averageOrderValue' => $averageOrderValue,
            'newOrdersToday' => $newOrdersToday,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
            'revenueMonthChangePercent' => $revenueMonthChangePercent,
            'sellerAcceptanceRate' => $sellerAcceptanceRate,
            'acceptedOrders' => $acceptedOrders,
            'slaScope' => $slaScope,
            'onTimeShipRate' => $onTimeShipRate,
            'onTimeShipments' => $onTimeShipments,
            'shipmentScope' => $shipmentScope,
            'sellerCancellationRate' => $sellerCancellationRate,
            'cancelledOrdersSla' => $cancelledOrders,
            'returnRate' => $returnRate,
            'returnedOrders' => $returnedOrders,
            'returnScope' => $returnScope,
            'activeCustomersToday' => $activeCustomersToday,
        ]);
    }
}
