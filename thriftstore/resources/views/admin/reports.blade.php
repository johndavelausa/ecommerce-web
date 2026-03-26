<x-app-layout>
@push('styles')
<style>
    .rep-main { background: #F2F7F3; }
    .rep-title { font-size: 1.375rem; font-weight: 800; color: #0F3D22; margin-bottom: 20px; }
    .rep-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; }
    .rep-period-btn { padding: 6px 14px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; text-decoration: none; transition: all 0.15s; }
    .rep-period-btn.active { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #F9C74F; border-color: #2D9F4E; }
    .rep-period-btn:hover { border-color: #2D9F4E; }
    .rep-filter-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; padding: 18px 20px; }
    .rep-filter-btn { padding: 6px 14px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #D4E8DA; background: #fff; color: #424242; text-decoration: none; transition: all 0.15s; }
    .rep-filter-btn.active { background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%); color: #fff; border-color: #2D9F4E; }
    .rep-filter-btn:hover { border-color: #2D9F4E; }
    .rep-metric { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; padding: 16px 18px; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .rep-metric-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; font-style: italic; margin-bottom: 6px; }
    .rep-metric-value { font-size: 1.5rem; font-weight: 900; color: #0F3D22; line-height: 1; }
    .rep-metric.amber { border-top: 3px solid #F57C00; }
    .rep-metric.green { border-top: 3px solid #1B7A37; }
    .rep-metric.slate { border-top: 3px solid #616161; }
    .rep-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .rep-table-header { background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%); padding: 12px 18px; border-bottom: 2px solid #F9C74F; font-weight: 700; color: #fff; font-size: 0.9375rem; }
    .rep-table-sub { font-size: 0.6875rem; color: rgba(255,255,255,0.5); font-style: italic; margin-top: 2px; }
    .rep-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .rep-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .rep-table th.right { text-align: right; }
    .rep-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .rep-table td.right { text-align: right; font-weight: 700; color: #0F3D22; }
    .rep-table tr:last-child td { border-bottom: none; }
    .rep-table tr:hover td { background: #F5FBF7; }
    .rep-table .empty-row td { color: #9E9E9E; font-style: italic; padding: 18px 16px; }
    .rep-export-btn { padding: 7px 16px; border-radius: 50px; font-size: 0.8125rem; font-weight: 600; border: 1.5px solid #2D9F4E; background: #fff; color: #2D9F4E; text-decoration: none; transition: all 0.15s; }
    .rep-export-btn:hover { background: #E8F5E9; }
    .rep-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .rep-summary-item { padding: 12px 0; border-bottom: 1px solid #F0F7F2; }
    .rep-summary-item:last-child { border-bottom: none; }
    .rep-summary-key { font-size: 0.8125rem; color: #757575; font-style: italic; }
    .rep-summary-val { font-size: 1rem; font-weight: 800; color: #0F3D22; margin-top: 4px; }
    .rep-summary-val.red { color: #C0392B; }
    .rep-summary-val.green { color: #1B7A37; }
</style>
@endpush

    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 rep-main" style="min-width:0;overflow-x:hidden;">
            <div style="width:100%;padding-bottom:32px;">
                <p class="rep-title">Reports</p>

                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:20px;">
                    <span class="rep-label">Sales Period:</span>
                    <a href="{{ route('admin.reports', ['sales_period' => 'daily']) }}" class="rep-period-btn {{ ($salesPeriod ?? '') === 'daily' ? 'active' : '' }}">Daily</a>
                    <a href="{{ route('admin.reports', ['sales_period' => 'weekly']) }}" class="rep-period-btn {{ ($salesPeriod ?? '') === 'weekly' ? 'active' : '' }}">Weekly</a>
                    <a href="{{ route('admin.reports', ['sales_period' => 'monthly']) }}" class="rep-period-btn {{ ($salesPeriod ?? '') === 'monthly' ? 'active' : '' }}">Monthly</a>
                    <a href="{{ route('admin.reports', ['sales_period' => 'yearly']) }}" class="rep-period-btn {{ ($salesPeriod ?? '') === 'yearly' ? 'active' : '' }}">Yearly</a>
                    <div style="margin-left:auto;display:flex;gap:8px;">
                        <a href="{{ route('admin.reports.export-all', ['sales_period' => $salesPeriod ?? 'monthly']) }}" class="rep-export-btn">📄 Export Report (PDF)</a>
                        <a href="{{ route('admin.reports.payments.export') }}" class="rep-export-btn">📄 Export Payments (PDF)</a>
                    </div>
                </div>



                {{-- Financial metrics --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px;">
                    <div class="rep-metric">
                        <div class="rep-metric-label">Total Profit (Fees)</div>
                        <div class="rep-metric-value">{{ number_format($totalProfit ?? 0, 2) }}</div>
                    </div>
                    <div class="rep-metric">
                        <div class="rep-metric-label">Total Revenue (Orders)</div>
                        <div class="rep-metric-value">{{ number_format($totalRevenue ?? 0, 2) }}</div>
                    </div>
                    <div class="rep-metric">
                        <div class="rep-metric-label">Total Sales (Period)</div>
                        <div class="rep-metric-value">{{ number_format($totalSalesFiltered ?? 0, 2) }}</div>
                    </div>
                </div>

                {{-- Cancellation rate --}}
                <div class="rep-filter-card" style="margin-bottom:20px;">
                    <div class="rep-label">Cancellation Rate (Selected Period)</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-top:14px;">
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Completed Orders</div>
                            <div class="rep-summary-val">{{ $completedCount ?? 0 }}</div>
                        </div>
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Cancelled Orders</div>
                            <div class="rep-summary-val red">{{ $cancelledCount ?? 0 }}</div>
                        </div>
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Total (Completed + Cancelled)</div>
                            <div class="rep-summary-val">{{ ($completedCount ?? 0) + ($cancelledCount ?? 0) }}</div>
                        </div>
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Cancellation Rate</div>
                            <div class="rep-summary-val {{ ($cancellationRate ?? 0) > 20 ? 'red' : '' }}">{{ number_format($cancellationRate ?? 0, 1) }}%</div>
                        </div>
                    </div>
                </div>

                {{-- Tables --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:20px;">
                    <div class="rep-table-card">
                        <div class="rep-table-header">Profit by Month<div class="rep-table-sub"><em>Approved fees</em></div></div>
                        <table class="rep-table">
                            <thead><tr><th>Month</th><th class="right">Amount</th></tr></thead>
                            <tbody>
                                @forelse($profitByMonth ?? [] as $row)
                                    <tr><td>{{ $row->ym }}</td><td class="right">{{ number_format((float)$row->total, 2) }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="rep-table-card">
                        <div class="rep-table-header">Revenue by Month<div class="rep-table-sub"><em>Orders</em></div></div>
                        <table class="rep-table">
                            <thead><tr><th>Month</th><th class="right">Amount</th></tr></thead>
                            <tbody>
                                @forelse($revenueByMonth ?? [] as $row)
                                    <tr><td>{{ $row->ym }}</td><td class="right">{{ number_format((float)$row->total, 2) }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No data</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Payment breakdown --}}
                <div class="rep-filter-card" style="margin-bottom:20px;">
                    <div class="rep-label">Payment Method Breakdown</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:14px;">
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">GCash (Seller Fees)</div>
                            <div class="rep-summary-val">{{ number_format($gcashTotal ?? 0, 2) }}</div>
                        </div>
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Cash (COD Orders)</div>
                            <div class="rep-summary-val">{{ number_format($cashTotal ?? 0, 2) }}</div>
                        </div>
                    </div>
                </div>

                {{-- Peak days/hours --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:20px;">
                    <div class="rep-table-card">
                        <div class="rep-table-header">Peak Days for Orders<div class="rep-table-sub"><em>{{ $salesPeriod ?? 'monthly' }}</em></div></div>
                        <table class="rep-table">
                            <thead><tr><th>Day of Week</th><th class="right">Orders</th></tr></thead>
                            <tbody>
                                @forelse($peakDays ?? [] as $row)
                                    <tr><td>{{ $row->day_name }}</td><td class="right">{{ $row->total }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No orders in this period</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="rep-table-card">
                        <div class="rep-table-header">Peak Hours for Orders<div class="rep-table-sub"><em>{{ $salesPeriod ?? 'monthly' }}</em></div></div>
                        <table class="rep-table">
                            <thead><tr><th>Hour of Day</th><th class="right">Orders</th></tr></thead>
                            <tbody>
                                @forelse($peakHours ?? [] as $row)
                                    <tr><td>{{ sprintf('%02d:00 - %02d:59', $row->hour, $row->hour) }}</td><td class="right">{{ $row->total }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No orders in this period</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Cancellations --}}
                <div class="rep-table-card" style="margin-bottom:20px;">
                    <div class="rep-table-header">Recent Cancellations</div>
                    <table class="rep-table">
                        <thead><tr><th>Date</th><th>Customer</th><th class="right">Amount</th></tr></thead>
                        <tbody>
                            @forelse($cancelledOrders ?? [] as $o)
                                <tr><td>{{ $o->cancelled_at?->format('Y-m-d H:i') }}</td><td>{{ $o->customer->name ?? '—' }}</td><td class="right">{{ number_format($o->total_amount, 2) }}</td></tr>
                            @empty
                                <tr class="empty-row"><td colspan="3">No cancellations</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if(isset($cancelledOrders) && $cancelledOrders->hasPages())
                        <div style="padding:12px 16px;border-top:1px solid #D4E8DA;">
                            {{ $cancelledOrders->links() }}
                        </div>
                    @endif
                </div>

                {{-- Sales by Seller --}}
                <div class="rep-table-card" style="margin-bottom:20px;">
                    <div class="rep-table-header">Sales by Seller<div class="rep-table-sub"><em>Revenue generated per store ({{ $salesPeriod ?? 'monthly' }})</em></div></div>
                    <table class="rep-table">
                        <thead>
                            <tr>
                                <th>Seller / Store</th>
                                <th class="right">Orders</th>
                                <th class="right">Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($salesBySeller ?? [] as $row)
                                <tr>
                                    <td>{{ $row->seller->store_name ?? '—' }}</td>
                                    <td class="right">{{ $row->order_count }}</td>
                                    <td class="right">{{ number_format($row->total_sales, 2) }}</td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="3">No sales in this period</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Sign-ups --}}

                <div class="rep-table-card" style="margin-bottom:20px;">
                    <div class="rep-table-header">New Sign-ups by Month</div>
                    <table class="rep-table">
                        <thead><tr><th>Month</th><th class="right">Count</th></tr></thead>
                        <tbody>
                            @forelse($newSignUps ?? [] as $row)
                                <tr><td>{{ $row->ym }}</td><td class="right">{{ $row->cnt }}</td></tr>
                            @empty
                                <tr class="empty-row"><td colspan="2">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Financial summary --}}
                <div class="rep-filter-card">
                    <div class="rep-label">Financial Summary</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:14px;">
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Total Fees Collected</div>
                            <div class="rep-summary-val">{{ number_format($totalProfit ?? 0, 2) }}</div>
                        </div>
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Pending Fees</div>
                            <div class="rep-summary-val">{{ number_format($totalPendingFees ?? 0, 2) }}</div>
                        </div>
                        <div class="rep-summary-item">
                            <div class="rep-summary-key">Active Product Listings</div>
                            <div class="rep-summary-val">{{ $productCount ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-app-layout>
