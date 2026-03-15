<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Reports') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-sm text-gray-600">Total sales period:</span>
                    <a href="{{ route('admin.reports', ['sales_period' => 'daily', 'refund_dispute_filter' => $refundDisputeFilter ?? 'all']) }}" class="px-3 py-1 rounded text-sm {{ ($salesPeriod ?? '') === 'daily' ? 'bg-indigo-600 text-white' : 'bg-gray-200' }}">Daily</a>
                    <a href="{{ route('admin.reports', ['sales_period' => 'weekly', 'refund_dispute_filter' => $refundDisputeFilter ?? 'all']) }}" class="px-3 py-1 rounded text-sm {{ ($salesPeriod ?? '') === 'weekly' ? 'bg-indigo-600 text-white' : 'bg-gray-200' }}">Weekly</a>
                    <a href="{{ route('admin.reports', ['sales_period' => 'monthly', 'refund_dispute_filter' => $refundDisputeFilter ?? 'all']) }}" class="px-3 py-1 rounded text-sm {{ ($salesPeriod ?? '') === 'monthly' ? 'bg-indigo-600 text-white' : 'bg-gray-200' }}">Monthly</a>
                    <a href="{{ route('admin.reports', ['sales_period' => 'yearly', 'refund_dispute_filter' => $refundDisputeFilter ?? 'all']) }}" class="px-3 py-1 rounded text-sm {{ ($salesPeriod ?? '') === 'yearly' ? 'bg-indigo-600 text-white' : 'bg-gray-200' }}">Yearly</a>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.reports.export-all', ['sales_period' => $salesPeriod ?? 'monthly', 'refund_dispute_filter' => $refundDisputeFilter ?? 'all']) }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-md border border-indigo-600 text-xs font-medium text-indigo-600 hover:bg-indigo-50">
                        Export all reports (CSV)
                    </a>
                    <a href="{{ route('admin.reports.payments.export') }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        Export payment history (CSV)
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm font-medium text-gray-700">Refund/dispute filter</div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('admin.reports', ['sales_period' => $salesPeriod ?? 'monthly', 'refund_dispute_filter' => 'all']) }}"
                       class="px-3 py-1 rounded text-sm {{ ($refundDisputeFilter ?? 'all') === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-700' }}">All</a>
                    <a href="{{ route('admin.reports', ['sales_period' => $salesPeriod ?? 'monthly', 'refund_dispute_filter' => 'pending']) }}"
                       class="px-3 py-1 rounded text-sm {{ ($refundDisputeFilter ?? 'all') === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-100 text-amber-800' }}">Pending</a>
                    <a href="{{ route('admin.reports', ['sales_period' => $salesPeriod ?? 'monthly', 'refund_dispute_filter' => 'completed']) }}"
                       class="px-3 py-1 rounded text-sm {{ ($refundDisputeFilter ?? 'all') === 'completed' ? 'bg-green-600 text-white' : 'bg-green-100 text-green-800' }}">Completed</a>
                    <a href="{{ route('admin.reports', ['sales_period' => $salesPeriod ?? 'monthly', 'refund_dispute_filter' => 'no_refund']) }}"
                       class="px-3 py-1 rounded text-sm {{ ($refundDisputeFilter ?? 'all') === 'no_refund' ? 'bg-slate-600 text-white' : 'bg-slate-100 text-slate-800' }}">No refund/dispute</a>
                </div>
                <div class="mt-2 text-xs text-gray-500">
                    @php($filterLabel = match($refundDisputeFilter ?? 'all') {
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'no_refund' => 'No refund/dispute',
                        default => 'All',
                    })
                    Current filter: <span class="font-semibold text-gray-700">{{ $filterLabel }}</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-400">
                    <div class="text-sm text-gray-500">Refund/dispute pending</div>
                    <div class="text-xl font-semibold">{{ $refundPendingCount ?? 0 }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
                    <div class="text-sm text-gray-500">Refund/dispute completed</div>
                    <div class="text-xl font-semibold">{{ $refundCompletedCount ?? 0 }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 border-l-4 border-slate-500">
                    <div class="text-sm text-gray-500">No refund/dispute</div>
                    <div class="text-xl font-semibold">{{ $refundNoRefundCount ?? 0 }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Total Profit (fees)</div>
                    <div class="text-xl font-semibold">₱{{ number_format($totalProfit ?? 0, 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Total Revenue (orders)</div>
                    <div class="text-xl font-semibold">₱{{ number_format($totalRevenue ?? 0, 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Total Sales (selected period)</div>
                    <div class="text-xl font-semibold">₱{{ number_format($totalSalesFiltered ?? 0, 2) }}</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="font-medium text-sm text-gray-700">Cancellation Rate (selected period)</div>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <span class="text-gray-500">Completed orders:</span>
                        <div class="font-semibold text-gray-900">{{ $completedCount ?? 0 }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500">Cancelled orders:</span>
                        <div class="font-semibold text-gray-900">{{ $cancelledCount ?? 0 }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500">Total (completed + cancelled):</span>
                        <div class="font-semibold text-gray-900">{{ ($completedCount ?? 0) + ($cancelledCount ?? 0) }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500">Cancellation rate:</span>
                        <div class="font-semibold {{ ($cancellationRate ?? 0) > 20 ? 'text-red-600' : 'text-gray-900' }}">
                            {{ number_format($cancellationRate ?? 0, 1) }}%
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-3 border-b font-medium">Profit by month (approved fees)</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Month</th><th class="px-4 py-2 text-left">Amount</th></tr></thead>
                            <tbody class="divide-y">
                                @forelse($profitByMonth ?? [] as $row)
                                    <tr><td class="px-4 py-2">{{ $row->ym }}</td><td class="px-4 py-2">₱{{ number_format((float)$row->total, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="px-4 py-3 text-gray-500">No data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-3 border-b font-medium">Revenue by month (orders)</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Month</th><th class="px-4 py-2 text-left">Amount</th></tr></thead>
                            <tbody class="divide-y">
                                @forelse($revenueByMonth ?? [] as $row)
                                    <tr><td class="px-4 py-2">{{ $row->ym }}</td><td class="px-4 py-2">₱{{ number_format((float)$row->total, 2) }}</td></tr>
                                @empty
                                    <tr><td colspan="2" class="px-4 py-3 text-gray-500">No data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-3 border-b font-medium">Payment method breakdown</div>
                <div class="p-4 grid grid-cols-2 gap-4">
                    <div><span class="text-gray-500">GCash (seller fees):</span> ₱{{ number_format($gcashTotal ?? 0, 2) }}</div>
                    <div><span class="text-gray-500">Cash (COD orders):</span> ₱{{ number_format($cashTotal ?? 0, 2) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-3 border-b font-medium">Peak days for orders ({{ $salesPeriod ?? 'monthly' }})</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Day of week</th>
                                    <th class="px-4 py-2 text-right">Orders</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($peakDays ?? [] as $row)
                                    <tr>
                                        <td class="px-4 py-2">{{ $row->day_name }}</td>
                                        <td class="px-4 py-2 text-right">{{ $row->total }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-4 py-3 text-gray-500">No orders in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-3 border-b font-medium">Peak hours for orders ({{ $salesPeriod ?? 'monthly' }})</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left">Hour of day</th>
                                    <th class="px-4 py-2 text-right">Orders</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse($peakHours ?? [] as $row)
                                    <tr>
                                        <td class="px-4 py-2">
                                            {{ sprintf('%02d:00 - %02d:59', $row->hour, $row->hour) }}
                                        </td>
                                        <td class="px-4 py-2 text-right">{{ $row->total }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="px-4 py-3 text-gray-500">No orders in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-3 border-b font-medium">Cancellations (recent)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Customer</th><th class="px-4 py-2 text-left">Amount</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($cancelledOrders ?? [] as $o)
                                <tr><td class="px-4 py-2">{{ $o->cancelled_at?->format('Y-m-d H:i') }}</td><td class="px-4 py-2">{{ $o->customer->name ?? '—' }}</td><td class="px-4 py-2">₱{{ number_format($o->total_amount, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-3 text-gray-500">No cancellations</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if(isset($cancelledOrders) && $cancelledOrders->hasPages())
                        <div class="px-4 py-2 border-t">
                            {{ $cancelledOrders->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-3 border-b font-medium">New sign-ups by month</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50"><tr><th class="px-4 py-2 text-left">Month</th><th class="px-4 py-2 text-left">Count</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($newSignUps ?? [] as $row)
                                <tr><td class="px-4 py-2">{{ $row->ym }}</td><td class="px-4 py-2">{{ $row->cnt }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="px-4 py-3 text-gray-500">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="font-medium">Financial summary</div>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div><span class="text-gray-500">Total fees collected:</span> ₱{{ number_format($totalProfit ?? 0, 2) }}</div>
                    <div><span class="text-gray-500">Pending fees:</span> ₱{{ number_format($totalPendingFees ?? 0, 2) }}</div>
                    <div><span class="text-gray-500">Active product listings:</span> {{ $productCount ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
