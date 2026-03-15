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

class ReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = $request->input('sales_period', 'monthly'); // daily, weekly, monthly, yearly
        $refundDisputeFilter = $request->input('refund_dispute_filter', 'all'); // all, pending, completed, no_refund

        $totalProfit = (float) Payment::query()->where('status', 'approved')->sum('amount');
        $totalRevenue = (float) Order::query()->whereIn('status', ['shipped', 'delivered'])->sum('total_amount');

        $profitByMonth = Payment::query()
            ->where('status', 'approved')
            ->selectRaw("DATE_FORMAT(COALESCE(approved_at, created_at), '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();

        $revenueByMonth = Order::query()
            ->whereIn('status', ['shipped', 'delivered'])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->limit(12)
            ->get();

        $salesQuery = Order::query()->whereIn('status', ['shipped', 'delivered']);
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
        ]);
    }

    public function exportPayments(Request $request): StreamedResponse
    {
        $fileName = 'payment-history-' . now()->format('Y-m-d_H-i-s') . '.csv';

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Seller Store',
                'Seller Email',
                'Type',
                'Amount',
                'Status',
                'Reference Number',
                'GCash Number',
                'Approved At',
                'Paid At',
                'Created At',
                'Rejection Reason',
            ]);

            Payment::query()
                ->with(['seller.user'])
                ->orderBy('created_at')
                ->chunkById(500, function ($payments) use ($handle) {
                    foreach ($payments as $p) {
                        fputcsv($handle, [
                            $p->id,
                            $p->seller?->store_name ?? '',
                            $p->seller?->user?->email ?? '',
                            $p->type,
                            (string) $p->amount,
                            $p->status,
                            $p->reference_number,
                            $p->gcash_number,
                            optional($p->approved_at)->toDateTimeString(),
                            optional($p->paid_at)->toDateTimeString(),
                            optional($p->created_at)->toDateTimeString(),
                            $p->rejection_reason,
                        ]);
                    }
                });

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportAll(Request $request): StreamedResponse
    {
        $period = $request->input('sales_period', 'monthly');
        $refundDisputeFilter = $request->input('refund_dispute_filter', 'all');
        $fileName = 'reports-' . $period . '-' . now()->format('Y-m-d_H-i-s') . '.csv';

        $callback = function () use ($period, $refundDisputeFilter) {
            $handle = fopen('php://output', 'w');

            // Helper to write a blank line between sections
            $blank = function () use ($handle) {
                fputcsv($handle, ['']);
            };

            // Rebuild key queries for the selected period
            $totalProfit = (float) Payment::query()->where('status', 'approved')->sum('amount');
            $totalRevenue = (float) Order::query()->whereIn('status', ['shipped', 'delivered'])->sum('total_amount');
            $totalPendingFees = (float) Payment::query()->where('status', 'pending')->sum('amount');
            $productCount = (int) Product::query()->where('is_active', 1)->count();

            $salesQuery = Order::query()->whereIn('status', ['shipped', 'delivered']);
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

            $profitByMonth = Payment::query()
                ->where('status', 'approved')
                ->selectRaw("DATE_FORMAT(COALESCE(approved_at, created_at), '%Y-%m') as ym, SUM(amount) as total")
                ->groupBy('ym')
                ->orderByDesc('ym')
                ->limit(12)
                ->get();

            $revenueByMonth = Order::query()
                ->whereIn('status', ['shipped', 'delivered'])
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
                ->groupBy('ym')
                ->orderByDesc('ym')
                ->limit(12)
                ->get();

            $peakDayQuery = Order::query();
            $peakHourQuery = Order::query();
            $this->applyPeriodFilter($peakDayQuery, $period);
            $this->applyPeriodFilter($peakHourQuery, $period);
            $this->applyRefundDisputeFilter($peakDayQuery, $refundDisputeFilter);
            $this->applyRefundDisputeFilter($peakHourQuery, $refundDisputeFilter);

            $peakDays = $peakDayQuery
                ->selectRaw("DATE_FORMAT(created_at, '%W') as day_name, COUNT(*) as total")
                ->groupBy('day_name')
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

            // Summary section
            fputcsv($handle, ['Section', 'Metric', 'Value']);
            fputcsv($handle, ['Summary', 'Total Profit (fees)', $totalProfit]);
            fputcsv($handle, ['Summary', 'Total Revenue (orders)', $totalRevenue]);
            fputcsv($handle, ['Summary', 'Total Sales (selected period)', $totalSalesFiltered]);
            fputcsv($handle, ['Summary', 'GCash total (fees)', $gcashTotal]);
            fputcsv($handle, ['Summary', 'Cash total (COD orders)', $cashTotal]);
            fputcsv($handle, ['Summary', 'Pending fees', $totalPendingFees]);
            fputcsv($handle, ['Summary', 'Active products', $productCount]);
            fputcsv($handle, ['Summary', 'Completed orders (period)', $completedCount]);
            fputcsv($handle, ['Summary', 'Cancelled orders (period)', $cancelledCount]);
            fputcsv($handle, ['Summary', 'Cancellation rate % (period)', $cancellationRate]);
            fputcsv($handle, ['Summary', 'Refund/dispute filter', $refundDisputeFilter]);
            fputcsv($handle, ['Summary', 'Refund/dispute pending orders (period)', $refundPendingCount]);
            fputcsv($handle, ['Summary', 'Refund/dispute completed orders (period)', $refundCompletedCount]);
            fputcsv($handle, ['Summary', 'No refund/dispute orders (period)', $refundNoRefundCount]);

            $blank();

            // Profit by month
            fputcsv($handle, ['ProfitByMonth', 'Month', 'Amount']);
            foreach ($profitByMonth as $row) {
                fputcsv($handle, ['ProfitByMonth', $row->ym, $row->total]);
            }

            $blank();

            // Revenue by month
            fputcsv($handle, ['RevenueByMonth', 'Month', 'Amount']);
            foreach ($revenueByMonth as $row) {
                fputcsv($handle, ['RevenueByMonth', $row->ym, $row->total]);
            }

            $blank();

            // Peak days
            fputcsv($handle, ['PeakDays', 'DayOfWeek', 'Orders']);
            foreach ($peakDays as $row) {
                fputcsv($handle, ['PeakDays', $row->day_name, $row->total]);
            }

            $blank();

            // Peak hours
            fputcsv($handle, ['PeakHours', 'Hour', 'Orders']);
            foreach ($peakHours as $row) {
                fputcsv($handle, ['PeakHours', $row->hour, $row->total]);
            }

            $blank();

            // New sign ups
            fputcsv($handle, ['NewSignUps', 'Month', 'Count']);
            foreach ($newSignUps as $row) {
                fputcsv($handle, ['NewSignUps', $row->ym, $row->cnt]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
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
}
