<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = $request->input('sales_period', 'monthly'); // daily, weekly, monthly, yearly
        $refundDisputeFilter = $request->input('refund_dispute_filter', 'all'); // all, pending, completed, no_refund

        $totalProfit = (float) Payment::query()->where('status', 'approved')->sum('amount');
        $totalRevenue = (float) Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->whereDoesntHave('disputes', function($q) {
                $q->whereIn('status', [
                    OrderDispute::STATUS_REFUND_COMPLETED,
                    OrderDispute::STATUS_RETURN_RECEIVED,
                    OrderDispute::STATUS_REFUND_PENDING
                ]);
            })
            ->sum('total_amount');

        $profitByMonth = Payment::query()
            ->where('status', 'approved')
            ->selectRaw("DATE_FORMAT(COALESCE(approved_at, created_at), '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();

        $revenueByMonth = Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED)
                  ->orWhere('refund_status', '');
            })
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();
 
        $salesQuery = Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->whereDoesntHave('disputes', function($q) {
                $q->whereIn('status', [
                    OrderDispute::STATUS_REFUND_COMPLETED,
                    OrderDispute::STATUS_RETURN_RECEIVED,
                    OrderDispute::STATUS_REFUND_PENDING
                ]);
            });
        $cancelledQuery = Order::query()->where('status', 'cancelled');

        $this->applyPeriodFilter($salesQuery, $period);
        $this->applyPeriodFilter($cancelledQuery, $period);
        $this->applyRefundDisputeFilter($salesQuery, $refundDisputeFilter);
        $this->applyRefundDisputeFilter($cancelledQuery, $refundDisputeFilter);

        $refundBase = Order::query();
        $this->applyPeriodFilter($refundBase, $period);

        $refundPendingCountQuery = clone $refundBase;
        $this->applyRefundDisputeFilter($refundPendingCountQuery, 'pending');
        $refundPendingCount = (int) $refundPendingCountQuery->count();

        $refundCompletedCountQuery = clone $refundBase;
        $this->applyRefundDisputeFilter($refundCompletedCountQuery, 'completed');
        $refundCompletedCount = (int) $refundCompletedCountQuery->count();

        $refundNoRefundCountQuery = clone $refundBase;
        $this->applyRefundDisputeFilter($refundNoRefundCountQuery, 'no_refund');
        $refundNoRefundCount = (int) $refundNoRefundCountQuery->count();

        $totalSalesFiltered = (float) $salesQuery->sum('total_amount');

        $completedCount = (int) $salesQuery->count();
        $cancelledCount = (int) $cancelledQuery->count();
        $denominator = $completedCount + $cancelledCount;
        $cancellationRate = $denominator > 0 ? round(($cancelledCount / $denominator) * 100, 1) : 0.0;

        $gcashTotal = (float) Payment::query()->where('status', 'approved')->sum('amount');
        $cashTotal = $totalRevenue;

        $cancelledOrders = Order::query()->where('status', 'cancelled')->with('customer');
        $this->applyRefundDisputeFilter($cancelledOrders, $refundDisputeFilter);
        $cancelledOrders = $cancelledOrders->orderByDesc('cancelled_at')->paginate(20);

        $peakDayQuery = Order::query();
        $peakHourQuery = Order::query();
        $this->applyPeriodFilter($peakDayQuery, $period);
        $this->applyPeriodFilter($peakHourQuery, $period);
        $this->applyRefundDisputeFilter($peakDayQuery, $refundDisputeFilter);
        $this->applyRefundDisputeFilter($peakHourQuery, $refundDisputeFilter);

        $peakDays = $peakDayQuery
            ->selectRaw("DAYOFWEEK(created_at) as dow, DATE_FORMAT(created_at, '%W') as day_name, COUNT(*) as total")
            ->groupBy('dow', 'day_name')
            ->orderByDesc('total')
            ->get();

        $peakHours = $peakHourQuery
            ->selectRaw("HOUR(created_at) as hour, COUNT(*) as total")
            ->groupBy('hour')
            ->orderByDesc('total')
            ->get();

        $newSignUps = User::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();

        $totalPendingFees = (float) Payment::query()->where('status', 'pending')->sum('amount');
        $productCount = (int) Product::query()->where('is_active', 1)->count();

        return view('admin.reports', [
            'totalProfit' => $totalProfit,
            'totalRevenue' => $totalRevenue,
            'profitByMonth' => $profitByMonth,
            'revenueByMonth' => $revenueByMonth,
            'salesPeriod' => $period,
            'totalSalesFiltered' => $totalSalesFiltered,
            'gcashTotal' => $gcashTotal,
            'cashTotal' => $cashTotal,
            'cancelledOrders' => $cancelledOrders,
            'newSignUps' => $newSignUps,
            'totalPendingFees' => $totalPendingFees,
            'productCount' => $productCount,
            'completedCount' => $completedCount,
            'cancelledCount' => $cancelledCount,
            'cancellationRate' => $cancellationRate,
            'peakDays' => $peakDays,
            'peakHours' => $peakHours,
            'refundDisputeFilter' => $refundDisputeFilter,
            'refundPendingCount' => $refundPendingCount,
            'refundCompletedCount' => $refundCompletedCount,
            'refundNoRefundCount' => $refundNoRefundCount,
            'salesBySeller' => $this->getSalesBySeller($period, $refundDisputeFilter),
        ]);
    }

    public function exportPayments(Request $request): Response
    {
        $fileName = 'payment-history-' . now()->format('Y-m-d_H-i-s') . '.pdf';
 
        $payments = Payment::query()
            ->with(['seller.user'])
            ->orderByDesc('created_at')
            ->get();
 
        $pdf = Pdf::loadView('admin.reports.payments-pdf', [
            'payments' => $payments,
            'exportedAt' => now(),
        ]);
 
        return $pdf->download($fileName);
    }

    public function exportAll(Request $request): Response
    {
        $period = $request->input('sales_period', 'monthly');
        $refundDisputeFilter = $request->input('refund_dispute_filter', 'all');
        $fileName = 'reports-' . $period . '-' . now()->format('Y-m-d_H-i-s') . '.pdf';
 
        // Summary data
        $totalProfit = (float) Payment::query()->where('status', 'approved')->sum('amount');
        $totalRevenue = (float) Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->sum('total_amount');
        
        $totalPendingFees = (float) Payment::query()->where('status', 'pending')->sum('amount');
        $productCount = (int) Product::query()->where('is_active', 1)->count();
 
        $salesQuery = Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED]);
        $cancelledQuery = Order::query()->where('status', 'cancelled');
 
        $this->applyPeriodFilter($salesQuery, $period);
        $this->applyPeriodFilter($cancelledQuery, $period);
        $this->applyRefundDisputeFilter($salesQuery, $refundDisputeFilter);
        $this->applyRefundDisputeFilter($cancelledQuery, $refundDisputeFilter);
 
        $totalSalesFiltered = (float) $salesQuery->sum('total_amount');
        $completedCount = (int) $salesQuery->count();
        $cancelledCount = (int) $cancelledQuery->count();
        $denominator = $completedCount + $cancelledCount;
        $cancellationRate = $denominator > 0 ? round(($cancelledCount / $denominator) * 100, 1) : 0.0;
 
        $profitByMonth = Payment::query()
            ->where('status', 'approved')
            ->selectRaw("DATE_FORMAT(COALESCE(approved_at, created_at), '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();
 
        $revenueByMonth = Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();
 
        $newSignUps = User::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();
 
        $pdf = Pdf::loadView('admin.reports.summary-pdf', [
            'period' => $period,
            'refundDisputeFilter' => $refundDisputeFilter,
            'totalProfit' => $totalProfit,
            'totalRevenue' => $totalRevenue,
            'totalSalesFiltered' => $totalSalesFiltered,
            'totalPendingFees' => $totalPendingFees,
            'productCount' => $productCount,
            'completedCount' => $completedCount,
            'cancelledCount' => $cancelledCount,
            'cancellationRate' => $cancellationRate,
            'profitByMonth' => $profitByMonth,
            'revenueByMonth' => $revenueByMonth,
            'newSignUps' => $newSignUps,
            'salesBySeller' => $this->getSalesBySeller($period, $refundDisputeFilter),
            'exportedAt' => now(),
        ]);
 
        return $pdf->download($fileName);
    }

    private function applyPeriodFilter(Builder $query, string $period): void
    {
        match ($period) {
            'daily' => $query->whereRaw('DATE(created_at) = CURDATE()'),
            'weekly' => $query->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'),
            'monthly' => $query->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'),
            'yearly' => $query->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)'),
            default => $query->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'),
        };
    }

    private function applyRefundDisputeFilter(Builder $query, string $filter): void
    {
        if ($filter === 'pending') {
            $query->where(function (Builder $inner) {
                $inner->where('refund_status', Order::REFUND_STATUS_PENDING)
                    ->orWhereHas('disputes', function (Builder $dq) {
                        $dq->whereIn('status', OrderDispute::ACTIVE_STATUSES);
                    });
            });

            return;
        }

        if ($filter === 'completed') {
            $query->where(function (Builder $inner) {
                $inner->where('refund_status', Order::REFUND_STATUS_COMPLETED)
                    ->orWhereHas('disputes', function (Builder $dq) {
                        $dq->whereIn('status', OrderDispute::TERMINAL_STATUSES);
                    });
            });

            return;
        }

        if ($filter === 'no_refund') {
            $query->where(function (Builder $inner) {
                $inner->whereNull('refund_status')
                    ->orWhere('refund_status', Order::REFUND_STATUS_NOT_REQUIRED);
            })->whereDoesntHave('disputes');
        }
    }

    private function getSalesBySeller(string $period, string $filter): \Illuminate\Database\Eloquent\Collection
    {
        $q = Order::query()
            ->whereIn('status', [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RECEIVED, Order::STATUS_COMPLETED])
            ->where(function($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', Order::REFUND_STATUS_COMPLETED);
            })
            ->whereDoesntHave('disputes', function($q) {
                $q->whereIn('status', [
                    OrderDispute::STATUS_REFUND_COMPLETED,
                    OrderDispute::STATUS_RETURN_RECEIVED,
                    OrderDispute::STATUS_REFUND_PENDING
                ]);
            });

        $this->applyPeriodFilter($q, $period);
        $this->applyRefundDisputeFilter($q, $filter);

        return $q->with('seller')
            ->selectRaw('seller_id, SUM(total_amount) as total_sales, COUNT(*) as order_count')
            ->groupBy('seller_id')
            ->orderByDesc('total_sales')
            ->limit(50)
            ->get();
    }
}
