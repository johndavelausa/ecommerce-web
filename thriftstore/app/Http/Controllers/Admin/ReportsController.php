<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = $request->input('sales_period', 'monthly'); // daily, weekly, monthly, yearly

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

        $applyPeriod = function ($q) use ($period) {
            return match ($period) {
                'daily' => $q->whereRaw('DATE(created_at) = CURDATE()'),
                'weekly' => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'),
                'monthly' => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'),
                'yearly' => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)'),
                default => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'),
            };
        };

        $applyPeriod($salesQuery);
        $applyPeriod($cancelledQuery);

        $totalSalesFiltered = (float) $salesQuery->sum('total_amount');

        $completedCount = (int) $salesQuery->count();
        $cancelledCount = (int) $cancelledQuery->count();
        $denominator = $completedCount + $cancelledCount;
        $cancellationRate = $denominator > 0 ? round(($cancelledCount / $denominator) * 100, 1) : 0.0;

        $gcashTotal = (float) Payment::query()->where('status', 'approved')->sum('amount');
        $cashTotal = $totalRevenue;

        $cancelledOrders = Order::query()->where('status', 'cancelled')->with('customer')->orderByDesc('cancelled_at')->limit(50)->get();

        $peakDayQuery = Order::query();
        $peakHourQuery = Order::query();
        $applyPeriod($peakDayQuery);
        $applyPeriod($peakHourQuery);

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
        $fileName = 'reports-' . $period . '-' . now()->format('Y-m-d_H-i-s') . '.csv';

        $callback = function () use ($period) {
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

            $applyPeriod = function ($q) use ($period) {
                return match ($period) {
                    'daily' => $q->whereRaw('DATE(created_at) = CURDATE()'),
                    'weekly' => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'),
                    'monthly' => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'),
                    'yearly' => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)'),
                    default => $q->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)'),
                };
            };

            $applyPeriod($salesQuery);
            $applyPeriod($cancelledQuery);

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
            $applyPeriod($peakDayQuery);
            $applyPeriod($peakHourQuery);

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
}
