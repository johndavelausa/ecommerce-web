<x-app-layout>
@push('styles')
<style>
    /* ── Admin Dashboard — Brand Palette ───────────────────────── */
    .adm-main { background: #F2F7F3; }
    .adm-page-title { font-size: 1.375rem; font-weight: 800; color: #0F3D22; margin: 0 0 2px; }
    .adm-page-sub   { font-size: 0.8125rem; color: #9E9E9E; margin: 0 0 20px; font-style: italic; }
    .adm-section-label {
        font-size: 0.625rem; font-weight: 800; color: #1B7A37;
        text-transform: uppercase; letter-spacing: 0.1em;
        margin: 20px 0 10px; padding-left: 12px;
        border-left: 3px solid #F9C74F;
        font-style: normal;
    }
    /* ── Hero cards (minimal border-radius) ── */
    .adm-hero {
        background: linear-gradient(135deg, #0F3D22 0%, #1B7A37 100%);
        border-radius: 4px;
        padding: 18px 26px;
        text-decoration: none;
        display: block;
        transition: all 0.15s;
        box-shadow: 0 2px 10px rgba(15,61,34,0.15);
    }
    a.adm-hero:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(15,61,34,0.3); }
    .adm-hero-label { font-size: 0.6875rem; font-weight: 700; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 5px; }
    .adm-hero-value { font-size: 1.625rem; font-weight: 900; color: #F9C74F; line-height: 1.1; }
    .adm-hero-hint  { font-size: 0.6875rem; color: rgba(255,255,255,0.4); margin-top: 5px; font-style: italic; }
    /* ── Animated border metric cards (loading animation) ── */
    @keyframes borderLoad {
        0%   { border-color: #D4E8DA; box-shadow: inset 0 0 0 0 rgba(45,159,78,0); }
        25%  { border-color: #2D9F4E; box-shadow: inset 0 0 0 0 rgba(45,159,78,0.1); }
        50%  { border-color: #F9C74F; box-shadow: inset 0 0 0 2px rgba(249,199,79,0.25); }
        75%  { border-color: #2D9F4E; box-shadow: inset 0 0 0 0 rgba(45,159,78,0.1); }
        100% { border-color: #D4E8DA; box-shadow: inset 0 0 0 0 rgba(45,159,78,0); }
    }
    .adm-metric {
        background: #fff;
        border-radius: 50px;
        padding: 14px 22px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 14px;
        border: 1.5px solid #D4E8DA;
        transition: all 0.15s ease;
        animation: borderLoad 2.5s ease-in-out infinite;
    }
    a.adm-metric:hover { animation: none; box-shadow: 0 4px 14px rgba(15,61,34,0.12); border-color: #2D9F4E; transform: translateY(-1px); }
    .adm-metric-dot {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; font-weight: 900;
    }
    .adm-metric-dot.green  { background: #E8F5E9; color: #1B7A37; }
    .adm-metric-dot.gold   { background: #FFF9E3; color: #F57C00; }
    .adm-metric-dot.blue   { background: #E3F2FD; color: #1565C0; }
    .adm-metric-body { flex: 1; min-width: 0; }
    .adm-metric-label { font-size: 0.6875rem; font-weight: 700; color: #9E9E9E; text-transform: uppercase; letter-spacing: 0.05em; }
    .adm-metric-value { font-size: 1.375rem; font-weight: 800; color: #0F3D22; line-height: 1.1; }
    .adm-metric-value.green { color: #1B7A37; }
    .adm-metric-value.amber { color: #F57C00; }
    .adm-metric-value.red   { color: #C0392B; }
    .adm-metric-hint { font-size: 0.6875rem; color: #BDBDBD; margin-top: 2px; font-style: italic; }
    /* ── Group cards (rounded) ── */
    .adm-group-card {
        background: #fff;
        border-radius: 24px;
        padding: 16px 20px;
        box-shadow: 0 1px 4px rgba(15,61,34,0.07);
        border: 1.5px solid #D4E8DA;
    }
    .adm-group-title { font-size: 0.6875rem; font-weight: 800; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
    .adm-group-dot { width: 8px; height: 8px; border-radius: 50%; background: #F9C74F; flex-shrink: 0; }
    .adm-group-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F0F7F2; }
    .adm-group-row:last-child { border-bottom: none; padding-bottom: 0; }
    .adm-group-key { font-size: 0.8125rem; color: #757575; font-style: italic; }
    .adm-group-val { font-size: 0.9375rem; font-weight: 800; color: #0F3D22; }
    .adm-group-val.green { color: #1B7A37; }
    .adm-group-val.amber { color: #F57C00; }
    .adm-group-val.red   { color: #C0392B; }
    /* ── Pill status chips ── */
    .adm-status-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; }
    .adm-chip {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 18px; border-radius: 50px;
        background: #fff; border: 1.5px solid #D4E8DA;
        text-decoration: none; font-size: 0.8125rem; transition: all 0.15s;
    }
    .adm-chip:hover { border-color: #2D9F4E; background: #F5FBF7; }
    .adm-chip-label { color: #424242; font-style: italic; font-size: 0.8125rem; }
    .adm-chip-val   { font-weight: 900; font-size: 1.0625rem; }
    .chip-processing { color: #F57C00; }
    .chip-shipped    { color: #1565C0; }
    .chip-delivered  { color: #1B7A37; }
    .chip-cancelled  { color: #C0392B; }
    /* ── Table card ── */
    .adm-table-card { background: #fff; border-radius: 20px; border: 1.5px solid #D4E8DA; overflow: hidden; box-shadow: 0 1px 4px rgba(15,61,34,0.06); }
    .adm-table-card-header { background: linear-gradient(135deg, #0F3D22 0%, #1a5c35 100%); padding: 13px 20px; border-bottom: 2px solid #F9C74F; }
    .adm-table-card-header .title { font-size: 0.9375rem; font-weight: 700; color: #fff; margin: 0 0 2px; }
    .adm-table-card-header .sub   { font-size: 0.6875rem; color: rgba(255,255,255,0.5); margin: 0; font-style: italic; }
    .adm-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .adm-table th { padding: 9px 16px; text-align: left; font-size: 0.6875rem; font-weight: 700; color: #1B7A37; text-transform: uppercase; letter-spacing: 0.05em; background: #F5FBF7; border-bottom: 1px solid #D4E8DA; }
    .adm-table th.right { text-align: right; }
    .adm-table td { padding: 9px 16px; color: #424242; border-bottom: 1px solid #F0F7F2; }
    .adm-table td.right { text-align: right; font-weight: 700; color: #0F3D22; }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tr:hover td { background: #F5FBF7; }
    .adm-table .empty-row td { color: #9E9E9E; font-style: italic; padding: 18px 16px; }
    .adm-month-change.up   { color: #F9C74F; font-weight: 700; }
    .adm-month-change.down { color: #FF7675; font-weight: 700; }
</style>
@endpush

    <div class="flex min-h-screen">
        @include('layouts.admin-sidebar')
        <main class="flex-1 p-6 adm-main" style="min-width:0;overflow-x:hidden;">
            <div style="width:100%;">

                <p class="adm-page-title">Dashboard</p>
                <p class="adm-page-sub"><em>Platform overview &mdash; {{ now()->format('F j, Y') }}</em></p>

                {{-- ── Hero pill row ── --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:6px;">
                    <a href="{{ route('admin.reports') }}" class="adm-hero">
                        <div class="adm-hero-label">Total Sales</div>
                        <div class="adm-hero-value">₱{{ number_format($totalSales ?? 0, 2) }}</div>
                        <div class="adm-hero-hint"><em>Delivered orders &middot; tap for breakdown →</em></div>
                    </a>
                    <div class="adm-hero" style="background:linear-gradient(135deg,#1B7A37 0%,#2D9F4E 100%);">
                        <div class="adm-hero-label">Platform GMV</div>
                        <div class="adm-hero-value">₱{{ number_format($platformGmv ?? 0, 2) }}</div>
                        <div class="adm-hero-hint"><em>Gross merchandise value excl. cancelled</em></div>
                    </div>
                    <div class="adm-hero" style="background:linear-gradient(135deg,#0A2B17 0%,#0F3D22 100%);">
                        <div class="adm-hero-label">Revenue This Month</div>
                        <div class="adm-hero-value">₱{{ number_format($revenueThisMonth ?? 0, 2) }}</div>
                        <div class="adm-hero-hint">
                            <span class="adm-month-change {{ ($revenueMonthChangePercent ?? 0) >= 0 ? 'up' : 'down' }}">
                                {{ ($revenueMonthChangePercent ?? 0) >= 0 ? '+' : '' }}{{ number_format($revenueMonthChangePercent ?? 0, 1) }}% vs last month
                            </span>
                            <em style="display:block;margin-top:2px;">Last month: ₱{{ number_format($revenueLastMonth ?? 0, 2) }}</em>
                        </div>
                    </div>
                </div>

                {{-- ── Core KPIs ── --}}
                <div class="adm-section-label">Core KPIs</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                    <a href="{{ route('admin.orders') }}" class="adm-metric">
                        <div class="adm-metric-dot blue">📦</div>
                        <div class="adm-metric-body">
                            <div class="adm-metric-label">Total Orders</div>
                            <div class="adm-metric-value">{{ number_format($totalOrders ?? 0) }}</div>
                            <div class="adm-metric-hint"><em>All statuses &middot; view list →</em></div>
                        </div>
                    </a>
                    <div class="adm-metric">
                        <div class="adm-metric-dot gold">💰</div>
                        <div class="adm-metric-body">
                            <div class="adm-metric-label">Total Revenue</div>
                            <div class="adm-metric-value green">₱{{ number_format($totalRevenue ?? 0, 2) }}</div>
                            <div class="adm-metric-hint"><em>Cumulative platform revenue</em></div>
                        </div>
                    </div>
                    <div class="adm-metric">
                        <div class="adm-metric-dot green">📈</div>
                        <div class="adm-metric-body">
                            <div class="adm-metric-label">Total Profit</div>
                            <div class="adm-metric-value green">₱{{ number_format($totalProfit ?? 0, 2) }}</div>
                            <div class="adm-metric-hint"><em>After platform costs</em></div>
                        </div>
                    </div>
                </div>

                {{-- ── Platform ── --}}
                <div class="adm-section-label">Platform</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="adm-group-card">
                        <div class="adm-group-title"><span class="adm-group-dot"></span>Users</div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Total Sellers</span>
                            <span class="adm-group-val">{{ $totalSellers ?? 0 }} <span style="font-size:0.75rem;color:#C0392B;font-weight:600;">({{ $rejectedSellers ?? 0 }} rejected)</span></span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Active / Inactive</span>
                            <span class="adm-group-val"><span class="green">{{ $activeSellers ?? 0 }}</span> / <span class="amber">{{ $inactiveSellers ?? 0 }}</span></span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Registered Customers</span>
                            <span class="adm-group-val">{{ $totalCustomers ?? 0 }}</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Seller Churn Rate</span>
                            <span class="adm-group-val">{{ number_format($churnRate ?? 0, 1) }}%</span>
                        </div>
                    </div>
                    <div class="adm-group-card">
                        <div class="adm-group-title"><span class="adm-group-dot"></span>Orders Snapshot</div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">New Orders Today</span>
                            <span class="adm-group-val green">{{ $newOrdersToday ?? 0 }}</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Avg Order Value</span>
                            <span class="adm-group-val">₱{{ number_format($averageOrderValue ?? 0, 2) }}</span>
                        </div>
                        <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}" style="text-decoration:none;" class="adm-group-row">
                            <span class="adm-group-key">Bad Orders</span>
                            <span class="adm-group-val red">{{ $badOrdersCount ?? 0 }} <span style="font-size:0.75rem;color:#9E9E9E;">({{ number_format($badOrdersPercent ?? 0, 1) }}%)</span></span>
                        </a>
                        <a href="{{ route('admin.messages') }}" style="text-decoration:none;" class="adm-group-row">
                            <span class="adm-group-key">Unread Messages</span>
                            <span class="adm-group-val {{ ($unreadMessages ?? 0) > 0 ? 'amber' : '' }}">{{ $unreadMessages ?? 0 }}</span>
                        </a>
                    </div>
                </div>

                {{-- ── SLA & Fees ── --}}
                <div class="adm-section-label">SLA &amp; Fees</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="adm-group-card">
                        <div class="adm-group-title"><span class="adm-group-dot"></span>SLA Metrics</div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Acceptance Rate</span>
                            <span class="adm-group-val">{{ number_format($sellerAcceptanceRate ?? 0, 1) }}%</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">On-time Ship Rate</span>
                            <span class="adm-group-val {{ ($onTimeShipRate ?? 0) < 80 ? 'amber' : 'green' }}">{{ number_format($onTimeShipRate ?? 0, 1) }}%</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Cancellation Rate</span>
                            <span class="adm-group-val {{ ($sellerCancellationRate ?? 0) > 20 ? 'red' : '' }}">{{ number_format($sellerCancellationRate ?? 0, 1) }}%</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Return Rate</span>
                            <span class="adm-group-val {{ ($returnRate ?? 0) > 15 ? 'red' : '' }}">{{ number_format($returnRate ?? 0, 1) }}%</span>
                        </div>
                    </div>
                    <div class="adm-group-card">
                        <div class="adm-group-title"><span class="adm-group-dot"></span>Platform Fees Collected</div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Registration Fees</span>
                            <span class="adm-group-val green">₱{{ number_format($totalRegistrationFees ?? 0, 2) }}</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Subscription Fees</span>
                            <span class="adm-group-val green">₱{{ number_format($totalSubscriptionFees ?? 0, 2) }}</span>
                        </div>
                        <div class="adm-group-row">
                            <span class="adm-group-key">Last Month Revenue</span>
                            <span class="adm-group-val">₱{{ number_format($revenueLastMonth ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- ── Order Status ── --}}
                <div class="adm-section-label">Order Status Breakdown</div>
                <p style="font-size:0.75rem;color:#9E9E9E;font-style:italic;margin:-4px 0 10px;"><em>Click a status to filter orders in the order list.</em></p>
                <div class="adm-status-row">
                    <a href="{{ route('admin.orders', ['status' => 'processing']) }}" class="adm-chip">
                        <span class="adm-chip-label">Processing</span><span class="adm-chip-val chip-processing">{{ $orderStatusBreakdown['processing'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.orders', ['status' => 'shipped']) }}" class="adm-chip">
                        <span class="adm-chip-label">Shipped</span><span class="adm-chip-val chip-shipped">{{ $orderStatusBreakdown['shipped'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.orders', ['status' => 'delivered']) }}" class="adm-chip">
                        <span class="adm-chip-label">Delivered</span><span class="adm-chip-val chip-delivered">{{ $orderStatusBreakdown['delivered'] ?? 0 }}</span>
                    </a>
                    <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}" class="adm-chip">
                        <span class="adm-chip-label">Cancelled</span><span class="adm-chip-val chip-cancelled">{{ $orderStatusBreakdown['cancelled'] ?? 0 }}</span>
                    </a>
                </div>

                {{-- ── Tables ── --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;padding-bottom:32px;">
                    <div class="adm-table-card">
                        <div class="adm-table-card-header"><p class="title">Monthly Revenue</p><p class="sub"><em>Last 12 months</em></p></div>
                        <table class="adm-table">
                            <thead><tr><th>Month</th><th class="right">Revenue</th></tr></thead>
                            <tbody>
                                @forelse(($monthlyRevenue ?? []) as $row)
                                    <tr><td>{{ $row->ym }}</td><td class="right">₱{{ number_format((float)$row->total, 2) }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="adm-table-card">
                        <div class="adm-table-card-header"><p class="title">New Seller Registrations</p><p class="sub"><em>{{ now()->year }} — per month</em></p></div>
                        <table class="adm-table">
                            <thead><tr><th>Month</th><th class="right">New Sellers</th></tr></thead>
                            <tbody>
                                @forelse(($sellerRegistrations ?? []) as $row)
                                    <tr><td>{{ $row->ym }}</td><td class="right">{{ (int)$row->total }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No registrations yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="adm-table-card">
                        <div class="adm-table-card-header"><p class="title">Top 5 Best-Selling Products</p><p class="sub"><em>By quantity sold from delivered orders</em></p></div>
                        <table class="adm-table">
                            <thead><tr><th>Product</th><th>Seller</th><th class="right">Qty</th></tr></thead>
                            <tbody>
                                @forelse(($topProducts ?? []) as $row)
                                    <tr><td>{{ $row->product_name }}</td><td style="color:#9E9E9E;font-style:italic;">{{ $row->store_name ?? '—' }}</td><td class="right">{{ (int)$row->qty_sold }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="3">No data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="adm-table-card">
                        <div class="adm-table-card-header"><p class="title">Top 5 Most Active Sellers</p><p class="sub"><em>By number of completed orders</em></p></div>
                        <table class="adm-table">
                            <thead><tr><th>Store</th><th class="right">Orders</th></tr></thead>
                            <tbody>
                                @forelse(($topSellers ?? []) as $row)
                                    <tr><td>{{ $row->store_name ?? '—' }}</td><td class="right">{{ (int)$row->completed_orders }}</td></tr>
                                @empty
                                    <tr class="empty-row"><td colspan="2">No data yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
</x-app-layout>

