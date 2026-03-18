<x-app-layout>

    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 bg-gray-50">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('admin.reports') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 block hover:bg-gray-50 transition">
                    <div class="text-sm text-gray-500">Total Sales</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($totalSales ?? 0, 2) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Delivered orders · View breakdown →</div>
                </a>
                <a href="{{ route('admin.orders') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 block hover:bg-gray-50 transition">
                    <div class="text-sm text-gray-500">Total Orders</div>
                    <div class="text-2xl font-semibold">{{ number_format($totalOrders ?? 0) }}</div>
                    <div class="text-xs text-gray-400 mt-1">All statuses · View list →</div>
                </a>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Profit</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($totalProfit ?? 0, 2) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Revenue</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($totalRevenue ?? 0, 2) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Sellers</div>
                    <div class="text-2xl font-semibold">{{ $totalSellers ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">Rejected: <span class="font-medium text-red-600">{{ $rejectedSellers ?? 0 }}</span></div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Registered Customers</div>
                    <div class="text-2xl font-semibold">{{ $totalCustomers ?? 0 }}</div>
                </div>
                <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 block hover:bg-gray-50 transition">
                    <div class="text-sm text-gray-500">Bad Orders</div>
                    <div class="text-2xl font-semibold text-red-700">{{ $badOrdersCount ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">({{ number_format($badOrdersPercent ?? 0, 1) }}% of total)</div>
                </a>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Seller Churn Rate</div>
                    <div class="text-2xl font-semibold">{{ number_format($churnRate ?? 0, 1) }}%</div>
                    <div class="text-xs text-gray-500 mt-1">this month</div>
                </div>
            </div>

            {{-- A1 v1.4 — Dashboard New Metrics --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Active vs Inactive Sellers</div>
                    <div class="flex items-baseline gap-3 mt-1">
                        <span class="text-2xl font-semibold text-green-700">{{ $activeSellers ?? 0 }}</span>
                        <span class="text-gray-400">active</span>
                        <span class="text-2xl font-semibold text-amber-600">{{ $inactiveSellers ?? 0 }}</span>
                        <span class="text-gray-400">inactive</span>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">By subscription status</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Platform GMV</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($platformGmv ?? 0, 2) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Total order value (excl. cancelled)</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Avg Order Value</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($averageOrderValue ?? 0, 2) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Delivered orders only</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">New Orders Today</div>
                    <div class="text-2xl font-semibold">{{ $newOrdersToday ?? 0 }}</div>
                    <div class="text-xs text-gray-400 mt-1">Orders created today</div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Seller Acceptance Rate</div>
                    <div class="text-2xl font-semibold">{{ number_format($sellerAcceptanceRate ?? 0, 1) }}%</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $acceptedOrders ?? 0 }} accepted / {{ $slaScope ?? 0 }} seller-workflow orders</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">On-time Ship Rate</div>
                    <div class="text-2xl font-semibold {{ ($onTimeShipRate ?? 0) < 80 ? 'text-amber-700' : '' }}">{{ number_format($onTimeShipRate ?? 0, 1) }}%</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $onTimeShipments ?? 0 }} on-time / {{ $shipmentScope ?? 0 }} shipped orders (48h SLA)</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Cancellation Rate</div>
                    <div class="text-2xl font-semibold {{ ($sellerCancellationRate ?? 0) > 20 ? 'text-red-700' : '' }}">{{ number_format($sellerCancellationRate ?? 0, 1) }}%</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $cancelledOrdersSla ?? 0 }} cancelled / {{ $slaScope ?? 0 }} seller-workflow orders</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Return Rate</div>
                    <div class="text-2xl font-semibold {{ ($returnRate ?? 0) > 15 ? 'text-red-700' : '' }}">{{ number_format($returnRate ?? 0, 1) }}%</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $returnedOrders ?? 0 }} returned / {{ $returnScope ?? 0 }} delivered-completed orders</div>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 sm:col-span-2 lg:col-span-1">
                    <div class="text-sm text-gray-500">Revenue This Month vs Last Month</div>
                    <div class="text-xl font-semibold mt-1">₱{{ number_format($revenueThisMonth ?? 0, 2) }}</div>
                    <div class="text-sm mt-1 {{ ($revenueMonthChangePercent ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        @if(($revenueMonthChangePercent ?? 0) >= 0)
                            +{{ number_format($revenueMonthChangePercent ?? 0, 1) }}% vs last month
                        @else
                            {{ number_format($revenueMonthChangePercent ?? 0, 1) }}% vs last month
                        @endif
                    </div>
                    <div class="text-xs text-gray-400 mt-1">Last month: ₱{{ number_format($revenueLastMonth ?? 0, 2) }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 mt-6">
                <div class="text-sm font-medium text-gray-700 mb-3">Order Status Breakdown</div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <a href="{{ route('admin.orders', ['status' => 'processing']) }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50 transition">
                        <span class="text-sm text-gray-600">Processing</span>
                        <span class="text-sm font-semibold text-amber-700">{{ $orderStatusBreakdown['processing'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.orders', ['status' => 'shipped']) }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50 transition">
                        <span class="text-sm text-gray-600">Shipped</span>
                        <span class="text-sm font-semibold text-blue-700">{{ $orderStatusBreakdown['shipped'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.orders', ['status' => 'delivered']) }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50 transition">
                        <span class="text-sm text-gray-600">Delivered</span>
                        <span class="text-sm font-semibold text-green-700">{{ $orderStatusBreakdown['delivered'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50 transition">
                        <span class="text-sm text-gray-600">Cancelled</span>
                        <span class="text-sm font-semibold text-red-700">{{ $orderStatusBreakdown['cancelled'] ?? 0 }}</span>
                    </a>
                </div>
                <div class="text-xs text-gray-400 mt-2">Click a status to view orders in the order list.</div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Collected Registration Fees (approved)</div>
                    <div class="text-xl font-semibold">₱{{ number_format($totalRegistrationFees ?? 0, 2) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Collected Subscription Fees (approved)</div>
                    <div class="text-xl font-semibold">₱{{ number_format($totalSubscriptionFees ?? 0, 2) }}</div>
                </div>
                <a href="{{ route('admin.messages') }}" class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4 block hover:bg-gray-50">
                    <div class="text-sm text-gray-500">Unread Messages</div>
                    <div class="text-xl font-semibold">{{ $unreadMessages ?? 0 }}</div>
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-4 border-b">
                    <div class="font-semibold">Monthly Revenue (last 12 months)</div>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Month</th>
                                <th class="py-2">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse(($monthlyRevenue ?? []) as $row)
                                <tr>
                                    <td class="py-2 pr-4">{{ $row->ym }}</td>
                                    <td class="py-2">₱{{ number_format((float) $row->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-3 text-gray-500" colspan="2">No revenue data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-4 border-b">
                    <div class="font-semibold">New Seller Registrations ({{ now()->year }})</div>
                    <div class="text-xs text-gray-500">Count of sellers created per month this year.</div>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Month</th>
                                <th class="py-2 text-right">New sellers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse(($sellerRegistrations ?? []) as $row)
                                <tr>
                                    <td class="py-2 pr-4">{{ $row->ym }}</td>
                                    <td class="py-2 text-right font-semibold text-gray-900">{{ (int) $row->total }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-3 text-gray-500" colspan="2">No seller registrations recorded yet this year.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-4 border-b">
                    <div class="font-semibold">Top 5 Best-Selling Products</div>
                    <div class="text-xs text-gray-500">Based on total quantity sold from delivered orders.</div>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Product</th>
                                <th class="py-2 pr-4">Seller</th>
                                <th class="py-2 text-right">Qty sold</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse(($topProducts ?? []) as $row)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-900">{{ $row->product_name }}</td>
                                    <td class="py-2 pr-4 text-gray-700">{{ $row->store_name ?? '—' }}</td>
                                    <td class="py-2 text-right font-semibold text-gray-900">{{ (int) $row->qty_sold }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-3 text-gray-500" colspan="3">No sales data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-4 border-b">
                    <div class="font-semibold">Top 5 Most Active Sellers</div>
                    <div class="text-xs text-gray-500">Based on number of completed (delivered) orders.</div>
                </div>
                <div class="p-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">Seller / Store</th>
                                <th class="py-2 text-right">Completed orders</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse(($topSellers ?? []) as $row)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-900">{{ $row->store_name ?? '—' }}</td>
                                    <td class="py-2 text-right font-semibold text-gray-900">{{ (int) $row->completed_orders }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-3 text-gray-500" colspan="2">No completed orders yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </main>
    </div>
</x-app-layout>

