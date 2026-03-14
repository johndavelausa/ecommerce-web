<?php

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $from;
    public string $to;
    public string $status = 'delivered'; // delivered, shipped, processing, cancelled, all

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
            'status' => ['required', 'in:delivered,shipped,processing,cancelled,all'],
        ]);
    }

    public function getRowsProperty()
    {
        $user = Auth::guard('seller')->user();
        $seller = $user?->seller;
        if (! $seller) {
            return collect();
        }

        $this->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'status' => ['required', 'in:delivered,shipped,processing,cancelled,all'],
        ]);

        $q = Order::query()
            ->where('seller_id', $seller->id)
            ->whereBetween('created_at', [
                $this->from . ' 00:00:00',
                $this->to . ' 23:59:59',
            ]);

        if ($this->status !== 'all') {
            $q->where('status', $this->status);
        }

        // Aggregate per day
        $rows = $q->selectRaw('DATE(created_at) as day, COUNT(*) as orders_count, SUM(total_amount) as total_amount')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows;
    }

    public function getBestDaysProperty()
    {
        $user = Auth::guard('seller')->user();
        $seller = $user?->seller;
        if (! $seller) {
            return collect();
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

        return $q->selectRaw("DAYOFWEEK(created_at) as dow, DATE_FORMAT(created_at, '%W') as day_name, COUNT(*) as orders_count, SUM(total_amount) as total_amount")
            ->groupBy('dow', 'day_name')
            ->orderByDesc('orders_count')
            ->get();
    }

    public function getCancelStatsProperty()
    {
        $user = Auth::guard('seller')->user();
        $seller = $user?->seller;
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
                        <option value="delivered">Delivered (earnings)</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="all">All statuses</option>
                    </select>
                    @error('status') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        @php
            $rows = $this->rows;
            $totalOrders = $rows->sum('orders_count');
            $totalAmount = $rows->sum('total_amount');
            $avgOrderValue = $totalOrders > 0 ? $totalAmount / $totalOrders : 0;
            $cancelStats = $this->cancelStats;
        @endphp

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between text-sm">
                <div class="text-gray-700">
                    Showing <span class="font-semibold">{{ $rows->count() }}</span> days,
                    <span class="font-semibold">{{ $totalOrders }}</span> orders total.
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
                            <td colspan="3" class="px-4 py-8 text-center text-gray-500">
                                No orders found for this range.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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

