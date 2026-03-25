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
            ->sum('total_amount');

        $completedOrdersCount = Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', $successStatuses)
            ->count();

        $cancelledOrdersCount = Order::query()
            ->where('seller_id', $sellerId)
            ->where('status', Order::STATUS_CANCELLED)
            ->count();

        $periodQuery = Order::query()->where('seller_id', $sellerId);
        $this->applyPeriodFilter($periodQuery, $period);
        
        $periodSales = (float) (clone $periodQuery)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->sum('total_amount');

        // Charts data
        $monthlyRevenue = Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->limit(12)
            ->get();

        // Top products
        $topProducts = OrderItem::query()
            ->selectRaw('products.name as name, SUM(order_items.quantity) as qty')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $sellerId)
            ->whereIn('orders.status', $successStatuses)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->limit(10)
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
            'exportedAt' => now(),
        ]);

        return $pdf->download($fileName);
    }

    private function applyPeriodFilter($query, $period)
    {
        switch ($period) {
            case 'daily':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'weekly':
                $query->where('created_at', '>=', Carbon::now()->subWeek());
                break;
            case 'monthly':
                $query->where('created_at', '>=', Carbon::now()->subMonth());
                break;
            case 'yearly':
                $query->where('created_at', '>=', Carbon::now()->subYear());
                break;
        }
    }
}
