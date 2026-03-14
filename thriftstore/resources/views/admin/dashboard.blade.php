<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Profit</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($totalProfit ?? 0, 2) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total Revenue</div>
                    <div class="text-2xl font-semibold">₱{{ number_format($totalRevenue ?? 0, 2) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Approved Sellers</div>
                    <div class="text-2xl font-semibold">{{ $totalApprovedSellers ?? 0 }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Registered Customers</div>
                    <div class="text-2xl font-semibold">{{ $totalCustomers ?? 0 }}</div>
                </div>
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
    </div>
</x-app-layout>

