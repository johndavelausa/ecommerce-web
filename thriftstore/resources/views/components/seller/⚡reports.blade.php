<?php

use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $from;
    public string $to;
    public string $status = 'completed'; // completed, delivered, shipped, out_for_delivery, processing, cancelled, all
    public string $refundDisputeFilter = 'all'; // all, pending, completed, no_refund

    public function mount(): void
    {
        $this->to = now()->toDateString();
        $this->from = now()->subMonth()->toDateString();
    }

    public function updated($field): void
    {
        $this->validateOnly($field, [
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'status' => ['required', 'in:completed,delivered,shipped,out_for_delivery,processing,cancelled,all'],
            'refundDisputeFilter' => ['required', 'in:all,pending,completed,no_refund'],
        ]);
        if (in_array($field, ['from', 'to', 'status', 'refundDisputeFilter'], true)) {
            $this->resetPage();
        }
    }

    protected function baseOrdersQuery()
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) {
            return null;
        }

        $q = Order::query()
            ->where('seller_id', $seller->id)
            ->whereBetween('created_at', [
                $this->from . ' 00:00:00',
                $this->to . ' 23:59:59',
            ]);

        if ($this->status !== 'all') {
            $q->where('status', $this->status);
        }

        return $q;
    }

    protected function applyRefundDisputeFilter($q, ?string $filter = null): void
    {
        $filter = $filter ?? $this->refundDisputeFilter;

        if ($filter === 'pending') {
            $q->where(function ($inner) {
                $inner->where('refund_status', Order::REFUND_STATUS_PENDING)
                    ->orWhereHas('disputes', function ($dq) {
                        $dq->whereIn('status', OrderDispute::ACTIVE_STATUSES);
                    });
            });

            return;
        }

        if ($filter === 'completed') {
            $q->where(function ($inner) {
                $inner->where('refund_status', Order::REFUND_STATUS_COMPLETED)
                    ->orWhereHas('disputes', function ($dq) {
                        $dq->whereIn('status', OrderDispute::TERMINAL_STATUSES);
                    });
            });

            return;
        }

        if ($filter === 'no_refund') {
            $q->where(function ($inner) {
                $inner->whereNull('refund_status')
                    ->orWhere('refund_status', Order::REFUND_STATUS_NOT_REQUIRED);
            })->whereDoesntHave('disputes');
        }
    }

    public function getRowsProperty()
    {
        $base = $this->baseOrdersQuery();
        if (! $base) {
            return collect();
        }

        $this->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'status' => ['required', 'in:completed,delivered,shipped,out_for_delivery,processing,cancelled,all'],
            'refundDisputeFilter' => ['required', 'in:all,pending,completed,no_refund'],
        ]);
        $q = (clone $base);
        $this->applyRefundDisputeFilter($q);

        // Aggregate per day, paginated (B2 - v1.3)
        return $q->selectRaw('DATE(created_at) as day, COUNT(*) as orders_count, SUM(total_amount) as total_amount')
            ->groupBy('day')
            ->orderByDesc('day')
            ->paginate(20);
    }

    public function getReportTotalsProperty()
    {
        $base = $this->baseOrdersQuery();
        if (! $base) {
            return (object) ['orders_count' => 0, 'total_amount' => 0];
        }
        $this->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'status' => ['required', 'in:completed,delivered,shipped,out_for_delivery,processing,cancelled,all'],
            'refundDisputeFilter' => ['required', 'in:all,pending,completed,no_refund'],
        ]);
        $q = (clone $base);
        $this->applyRefundDisputeFilter($q);
        $row = $q->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total_amount), 0) as total_amount')->first();
        return (object) ['orders_count' => (int) $row->orders_count, 'total_amount' => (float) $row->total_amount];
    }

    public function getRefundDisputeKpisProperty()
    {
        $base = $this->baseOrdersQuery();
        if (! $base) {
            return (object) ['pending' => 0, 'completed' => 0, 'no_refund' => 0];
        }

        $pendingQuery = (clone $base);
        $this->applyRefundDisputeFilter($pendingQuery, 'pending');

        $completedQuery = (clone $base);
        $this->applyRefundDisputeFilter($completedQuery, 'completed');

        $noRefundQuery = (clone $base);
        $this->applyRefundDisputeFilter($noRefundQuery, 'no_refund');

        return (object) [
            'pending' => (int) $pendingQuery->count(),
            'completed' => (int) $completedQuery->count(),
            'no_refund' => (int) $noRefundQuery->count(),
        ];
    }

    public function getBestDaysProperty()
    {
        $base = $this->baseOrdersQuery();
        if (! $base) {
            return collect();
        }

        $q = (clone $base);
        $this->applyRefundDisputeFilter($q);

        return $q->selectRaw("DAYOFWEEK(created_at) as dow, DATE_FORMAT(created_at, '%W') as day_name, COUNT(*) as orders_count, SUM(total_amount) as total_amount")
            ->groupBy('dow', 'day_name')
            ->orderByDesc('orders_count')
            ->get();
    }

    /** B5 v1.4 — Revenue this month vs last month */
    public function getRevenueComparisonProperty()
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) {
            return (object) ['this_month' => 0.0, 'last_month' => 0.0, 'change_percent' => 0.0];
        }
        $thisMonth = (float) Order::query()->where('seller_id', $seller->id)->whereIn('status', ['shipped', 'out_for_delivery', 'delivered', 'completed'])
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total_amount');
        $lastMonth = (float) Order::query()->where('seller_id', $seller->id)->whereIn('status', ['shipped', 'out_for_delivery', 'delivered', 'completed'])
            ->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->sum('total_amount');
        $change = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : ($thisMonth > 0 ? 100.0 : 0.0);
        return (object) ['this_month' => $thisMonth, 'last_month' => $lastMonth, 'change_percent' => $change];
    }

    /** B5 v1.4 — Customer retention: % of customers with 2+ orders */
    public function getRetentionRateProperty()
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) {
            return (object) ['rate' => 0.0, 'repeat_customers' => 0, 'total_customers' => 0];
        }
        $total = (int) Order::query()->where('seller_id', $seller->id)->selectRaw('COUNT(DISTINCT customer_id) as c')->value('c');
        $repeat = $total === 0 ? 0 : (int) Order::query()->where('seller_id', $seller->id)->groupBy('customer_id')->havingRaw('COUNT(*) >= 2')->get()->count();
        $rate = $total > 0 ? round(($repeat / $total) * 100, 1) : 0.0;
        return (object) ['rate' => $rate, 'repeat_customers' => $repeat, 'total_customers' => $total];
    }

    public function getCancelStatsProperty()
    {
        $seller = Auth::guard('seller')->user()?->seller;
        if (! $seller) {
            return collect();
        }

        $q = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.seller_id', $seller->id)
            ->whereBetween('orders.created_at', [
                $this->from . ' 00:00:00',
                $this->to . ' 23:59:59',
            ]);

        // focus on completed vs cancelled behaviour; ignore processing-only
        if ($this->status !== 'all') {
            $q->where('orders.status', $this->status);
        }

        if ($this->refundDisputeFilter === 'pending') {
            $q->where(function ($inner) {
                $inner->where('orders.refund_status', Order::REFUND_STATUS_PENDING)
                    ->orWhereExists(function ($exists) {
                        $exists->selectRaw('1')
                            ->from('order_disputes')
                            ->whereColumn('order_disputes.order_id', 'orders.id')
                            ->whereIn('order_disputes.status', OrderDispute::ACTIVE_STATUSES);
                    });
            });
        } elseif ($this->refundDisputeFilter === 'completed') {
            $q->where(function ($inner) {
                $inner->where('orders.refund_status', Order::REFUND_STATUS_COMPLETED)
                    ->orWhereExists(function ($exists) {
                        $exists->selectRaw('1')
                            ->from('order_disputes')
                            ->whereColumn('order_disputes.order_id', 'orders.id')
                            ->whereIn('order_disputes.status', OrderDispute::TERMINAL_STATUSES);
                    });
            });
        } elseif ($this->refundDisputeFilter === 'no_refund') {
            $q->where(function ($inner) {
                $inner->whereNull('orders.refund_status')
                    ->orWhere('orders.refund_status', Order::REFUND_STATUS_NOT_REQUIRED);
            })->whereNotExists(function ($exists) {
                $exists->selectRaw('1')
                    ->from('order_disputes')
                    ->whereColumn('order_disputes.order_id', 'orders.id');
            });
        }

        $stats = $q->selectRaw("
                products.id   as product_id,
                products.name as product_name,
                SUM(order_items.quantity) as total_qty,
                SUM(CASE WHEN orders.status = 'cancelled' THEN order_items.quantity ELSE 0 END) as cancelled_qty
            ")
            ->groupBy('products.id', 'products.name')
            ->havingRaw('total_qty > 0')
            ->orderByDesc('cancelled_qty')
            ->limit(20)
            ->get();

        return $stats;
    }
};
?>

<div class="space-y-6">
    @php($rev = $this->revenueComparison; $ret = $this->retentionRate; $refundKpis = $this->refundDisputeKpis)
    <div class="grid gap-4 md:grid-cols-2">
        <div class="bg-white rounded-lg shadow p-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Revenue this month vs last month</h4>
            <div class="mt-2 text-2xl font-semibold text-gray-900">₱{{ number_format($rev->this_month, 2) }}</div>
            <div class="mt-1 text-sm {{ $rev->change_percent >= 0 ? 'text-green-600' : 'text-red-600' }}">
                @if($rev->change_percent >= 0)<span aria-hidden="true">↑</span> +@else<span aria-hidden="true">↓</span> @endif{{ $rev->change_percent }}% vs last month
            </div>
            <div class="text-xs text-gray-500 mt-0.5">Last month: ₱{{ number_format($rev->last_month, 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Customer retention rate</h4>
            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($ret->rate, 1) }}%</div>
            <div class="text-xs text-gray-500 mt-1">{{ $ret->repeat_customers }} customers with 2+ orders / {{ $ret->total_customers }} unique</div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <button type="button" wire:click="$set('refundDisputeFilter', 'pending')"
                class="text-left bg-white rounded-lg shadow p-5 border {{ $refundDisputeFilter === 'pending' ? 'border-amber-500 ring-1 ring-amber-400' : 'border-transparent' }}">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Refund/dispute pending</h4>
            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $refundKpis->pending }}</div>
            <div class="mt-1 text-xs text-gray-500">Orders with pending refund status or active dispute.</div>
        </button>
        <button type="button" wire:click="$set('refundDisputeFilter', 'completed')"
                class="text-left bg-white rounded-lg shadow p-5 border {{ $refundDisputeFilter === 'completed' ? 'border-green-500 ring-1 ring-green-400' : 'border-transparent' }}">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Refund/dispute completed</h4>
            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $refundKpis->completed }}</div>
            <div class="mt-1 text-xs text-gray-500">Orders with completed refund status or resolved disputes.</div>
        </button>
        <button type="button" wire:click="$set('refundDisputeFilter', 'no_refund')"
                class="text-left bg-white rounded-lg shadow p-5 border {{ $refundDisputeFilter === 'no_refund' ? 'border-slate-500 ring-1 ring-slate-400' : 'border-transparent' }}">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">No refund/dispute</h4>
            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $refundKpis->no_refund }}</div>
            <div class="mt-1 text-xs text-gray-500">Orders with no dispute records and no refund flow.</div>
        </button>
    </div>

    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Sales & earnings report</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Filter by date range and status to see your own orders and earnings only.
                </p>
            </div>
            <div class="flex flex-wrap gap-3 text-sm">
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">From</label>
                    <input type="date" wire:model.live="from"
                           class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('from') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">To</label>
                    <input type="date" wire:model.live="to"
                           class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('to') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Status</label>
                    <select wire:model.live="status"
                            class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="completed">Completed (earnings)</option>
                        <option value="delivered">Delivered</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="out_for_delivery">Out for delivery</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="all">All statuses</option>
                    </select>
                    @error('status') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 uppercase tracking-wide">Refund / dispute</label>
                    <select wire:model.live="refundDisputeFilter"
                            class="mt-1 block w-44 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="no_refund">No refund / dispute</option>
                    </select>
                    @error('refundDisputeFilter') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        @php
            $rows = $this->rows;
            $totals = $this->reportTotals;
            $totalOrders = $totals->orders_count;
            $totalAmount = $totals->total_amount;
            $avgOrderValue = $totalOrders > 0 ? $totalAmount / $totalOrders : 0;
            $cancelStats = $this->cancelStats;
        @endphp

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between text-sm">
                <div class="text-gray-700">
                    <span class="font-semibold">{{ $totalOrders }}</span> orders total in range
                    @if($rows->hasPages())
                        · Showing page {{ $rows->currentPage() }} ({{ $rows->count() }} days)
                    @else
                        · {{ $rows->count() }} days
                    @endif
                </div>
                <div class="text-gray-900 font-semibold">
                    Total: ₱{{ number_format($totalAmount ?? 0, 2) }}
                    <span class="text-xs text-gray-500 ml-2">
                        (Average order value: ₱{{ number_format($avgOrderValue, 2) }})
                    </span>
                </div>
            </div>

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total amount</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg order value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">
                                {{ $row->day }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                {{ $row->orders_count }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900">
                                ₱{{ number_format($row->total_amount ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900">
                                @php($rowAvg = $row->orders_count > 0 ? $row->total_amount / $row->orders_count : 0)
                                ₱{{ number_format($rowAvg, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                No orders found for this range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if($rows->hasPages())
                <div class="px-4 py-2 border-t">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>

        @php($bestDays = $this->bestDays)

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between text-sm">
                <div class="text-gray-700">
                    Best days of week for this range
                </div>
                @if($bestDays->count())
                    <div class="text-xs text-gray-500">
                        Top day: <span class="font-semibold">{{ $bestDays->first()->day_name }}</span>
                        ({{ $bestDays->first()->orders_count }} orders)
                    </div>
                @endif
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Day of week</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($bestDays as $row)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $row->day_name }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $row->orders_count }}</td>
                            <td class="px-4 py-3 text-right text-gray-900">
                                ₱{{ number_format($row->total_amount ?? 0, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                Not enough data yet to compute best days.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between text-sm">
                <div class="text-gray-700">
                    Products with highest cancel / return rate
                </div>
                @if($cancelStats->count())
                    @php($top = $cancelStats->first())
                    @php($rate = $top->total_qty > 0 ? round(($top->cancelled_qty / $top->total_qty) * 100, 1) : 0)
                    <div class="text-xs text-gray-500">
                        Worst product: <span class="font-semibold">{{ $top->product_name }}</span>
                        ({{ $top->cancelled_qty }}/{{ $top->total_qty }} &approx; {{ $rate }}% cancelled)
                    </div>
                @endif
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cancelled qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cancel rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($cancelStats as $row)
                        @php($rate = $row->total_qty > 0 ? round(($row->cancelled_qty / $row->total_qty) * 100, 1) : 0)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $row->product_name }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $row->total_qty }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                {{ $row->cancelled_qty }}
                            </td>
                            <td class="px-4 py-3 text-right {{ $rate > 20 ? 'text-red-600 font-semibold' : 'text-gray-900' }}">
                                {{ number_format($rate, 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                Not enough cancelled orders to compute product cancel rates.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

