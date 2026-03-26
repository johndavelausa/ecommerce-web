<?php

namespace App\Livewire\Seller;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderDispute;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Reports extends Component
{
    public $salesPeriod = 'monthly'; // daily, weekly, monthly, yearly
    public $refundDisputeFilter = 'all'; // all, pending, completed, no_refund

    public function render()
    {
        $seller = Auth::guard('seller')->user()->seller;
        $sellerId = $seller->id;

        // Successful statuses: delivered, received, completed
        $successStatuses = [Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED];

        // ── Summary Metrics ──
        $totalRevenue = (float) Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->sum('total_amount');

        $completedOrdersCount = Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', $successStatuses)
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->count();

        $cancelledOrdersCount = Order::query()
            ->where('seller_id', $sellerId)
            ->where(function($q) {
                $q->where('status', Order::STATUS_CANCELLED)
                  ->orWhere('refund_status', Order::REFUND_STATUS_COMPLETED);
            })
            ->count();

        // ── Period-Filtered Metrics ──
        $periodQuery = Order::query()->where('seller_id', $sellerId);
        $this->applyPeriodFilter($periodQuery);
        
        $periodSales = (float) (clone $periodQuery)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->sum('total_amount');

        $periodOrdersCount = (int) (clone $periodQuery)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->count();

        // ── Charts Data (Last 6 Months) ──
        $monthlyRevenue = Order::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', array_merge([Order::STATUS_SHIPPED], $successStatuses))
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->limit(6)
            ->get()
            ->reverse();

        // ── Best Selling Products ──
        $topProducts = OrderItem::query()
            ->selectRaw('products.name as name, SUM(order_items.quantity) as qty')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $sellerId)
            ->whereIn('orders.status', $successStatuses)
            ->where(function($q) {
                $q->whereNull('orders.refund_status')
                  ->orWhere('orders.refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // ── Dispute Summary (Seller Level) ──
        $disputeCount = OrderDispute::query()
            ->whereHas('order', fn($q) => $q->where('seller_id', $sellerId))
            ->count();

        return view('livewire.seller.reports', [
            'totalRevenue' => $totalRevenue,
            'completedOrdersCount' => $completedOrdersCount,
            'cancelledOrdersCount' => $cancelledOrdersCount,
            'periodSales' => $periodSales,
            'periodOrdersCount' => $periodOrdersCount,
            'monthlyRevenue' => $monthlyRevenue,
            'topProducts' => $topProducts,
            'disputeCount' => $disputeCount,
        ]);
    }

    private function applyPeriodFilter($query)
    {
        switch ($this->salesPeriod) {
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

    public function setPeriod($period)
    {
        $this->salesPeriod = $period;
        $this->dispatch('refresh-charts');
    }
}
