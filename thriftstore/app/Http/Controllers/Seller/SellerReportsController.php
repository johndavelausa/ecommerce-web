<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderDispute;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class SellerReportsController extends Controller
{
    public function export(Request $request): Response
    {
        $seller = Auth::guard('seller')->user()->seller;
        if (!$seller) abort(403);
        $sellerId = $seller->id;

        $period = $request->input('period', 'monthly');
        $fileName = 'seller-report-' . $period . '-' . now()->format('Y-m-d') . '.pdf';

        $successStatuses = [Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED];

        // Core Metrics
        $totalRevenue = (float) Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->sum('total_amount');

        $completedOrdersCount = Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', $successStatuses)
            ->count();

        $cancelledOrdersCount = Order::query()
            ->where('seller_id', $sellerId)
            ->where(function($q) {
                $q->where('status', Order::STATUS_CANCELLED)
                  ->orWhere('refund_status', Order::REFUND_STATUS_COMPLETED);
            })
            ->count();

        $periodQuery = Order::query()->where('seller_id', $sellerId);
        $this->applyPeriodFilter($periodQuery, $period);
        
        $periodSales = (float) (clone $periodQuery)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->sum('total_amount');

        // Charts data
        $monthlyRevenue = Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->limit(12)
            ->get();

        // Top products by quantity
        $topProducts = OrderItem::query()
            ->selectRaw('products.name as name, SUM(order_items.quantity) as qty, SUM(order_items.quantity * order_items.price_at_purchase) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $sellerId)
            ->whereIn('orders.status', $successStatuses)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        // Detailed product performance for the period
        $productPerformance = OrderItem::query()
            ->selectRaw('products.name as name, SUM(order_items.quantity) as qty, SUM(order_items.quantity * order_items.price_at_purchase) as revenue')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $sellerId)
            ->whereIn('orders.status', $successStatuses);
        $this->applyPeriodFilter($productPerformance, $period);
        $productPerformance = $productPerformance->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get();

        // Recent feedback
        $recentFeedback = \App\Models\Review::query()
            ->with(['customer', 'product'])
            ->whereHas('product', function($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            })
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $pdf = Pdf::loadView('seller.reports.summary-pdf', [
            'seller' => $seller,
            'period' => $period,
            'totalRevenue' => $totalRevenue,
            'completedOrdersCount' => $completedOrdersCount,
            'cancelledOrdersCount' => $cancelledOrdersCount,
            'periodSales' => $periodSales,
            'monthlyRevenue' => $monthlyRevenue,
            'topProducts' => $topProducts,
            'productPerformance' => $productPerformance,
            'recentFeedback' => $recentFeedback,
            'exportedAt' => now(),
        ]);

        return $pdf->download($fileName);
    }

    private function applyPeriodFilter($query, $period)
    {
        $column = 'orders.created_at';
        // If it's a simple Order query without joins, we might just use 'created_at' but 'orders.created_at' is safer if joined.
        // However, if it's NOT joined, 'orders.created_at' will fail. 
        // Let's use a smarter approach or just detect if it's an Order query.
        
        // Actually, in this controller, we mostly join orders.
        // Let's just use whereDate and similar but be careful.
        
        switch ($period) {
            case 'daily':
                $query->whereDate($query->getModel()->getTable() . '.created_at', Carbon::today());
                break;
            case 'weekly':
                $query->where($query->getModel()->getTable() . '.created_at', '>=', Carbon::now()->subWeek());
                break;
            case 'monthly':
                $query->where($query->getModel()->getTable() . '.created_at', '>=', Carbon::now()->subMonth());
                break;
            case 'yearly':
                $query->where($query->getModel()->getTable() . '.created_at', '>=', Carbon::now()->subYear());
                break;
        }
    }
}
