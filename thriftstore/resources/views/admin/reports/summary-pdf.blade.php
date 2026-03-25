<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Business Summary Report</title>
    <style>
        body { font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 11px; line-height: 1.4; margin: 0; padding: 25px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1B7A37; padding-bottom: 15px; }
        .header h1 { color: #1B7A37; font-size: 26px; margin: 0; text-transform: uppercase; letter-spacing: 3px; font-weight: 800; }
        .header p { color: #888; margin: 5px 0 0; font-style: italic; font-size: 12px; }
        
        .section-title { font-size: 14px; font-weight: 800; color: #1B7A37; margin: 25px 0 10px; border-left: 4px solid #F9C74F; padding-left: 10px; text-transform: uppercase; letter-spacing: 1px; }
        
        .kpi-grid { display: block; overflow: auto; margin-bottom: 20px; }
        .kpi-row { margin-bottom: 10px; width: 100%; }
        .kpi-card { float: left; width: 22.5%; padding: 10px; border: 1px solid #D4E8DA; border-radius: 8px; background-color: #fcfdfc; margin-right: 2%; text-align: center; height: 60px; }
        .kpi-label { font-size: 8px; color: #777; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 14px; font-weight: 900; color: #0F3D22; }
        
        .clear { clear: both; }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { background-color: #f2f7f3; color: #1B7A37; text-align: left; padding: 8px; border: 1px solid #D4E8DA; text-transform: uppercase; font-size: 9px; }
        td { padding: 8px; border: 1px solid #D4E8DA; vertical-align: top; font-size: 10px; }
        
        .even { background-color: #fcfdfc; }
        .bold { font-weight: bold; }
        .amount { font-weight: bold; color: #1B7A37; }
        .currency { font-family: 'DejaVu Sans', sans-serif; font-weight: normal; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #aaa; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Platform Report</h1>
        <p>Period: {{ ucfirst($period) }} | Analytics Summary</p>
    </div>

    <div style="font-size: 9px; color: #777; margin-bottom: 20px; text-align: right;">
        Exported: {{ $exportedAt->format('F j, Y g:1 A') }}
    </div>

    <div class="section-title">Core Performance Metrics</div>
    <div class="kpi-grid">
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Total Profit (Fees)</div>
                <div class="kpi-value"><span class="currency"></span>{{ number_format($totalProfit, 2) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Period Sales</div>
                <div class="kpi-value"><span class="currency"></span>{{ number_format($totalSalesFiltered, 2) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Completed Orders</div>
                <div class="kpi-value">{{ $completedCount }}</div>
            </div>
            <div class="kpi-card" style="border-color: #FF7675;">
                <div class="kpi-label">Cancellation Rate</div>
                <div class="kpi-value" style="color: #C0392B;">{{ $cancellationRate }}%</div>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <div style="width: 100%; margin-top: 10px;">
        <div style="float: left; width: 48%;">
            <div class="section-title">Revenue by Month</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenueByMonth as $index => $row)
                        <tr class="{{ $index % 2 != 0 ? 'even' : '' }}">
                            <td>{{ $row->ym }}</td>
                            <td class="amount"><span class="currency"></span>{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div style="float: right; width: 48%;">
            <div class="section-title">Profit by Month</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($profitByMonth as $index => $row)
                        <tr class="{{ $index % 2 != 0 ? 'even' : '' }}">
                            <td>{{ $row->ym }}</td>
                            <td class="amount"><span class="currency"></span>{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="section-title">Metric Overview</div>
    <table style="margin-top: 0;">
        <tbody>
            <tr>
                <td class="bold">Active Products</td>
                <td>{{ number_format($productCount) }}</td>
                <td class="bold">New Sign-ups (Period)</td>
                <td>{{ $newSignUps->sum('cnt') }}</td>
            </tr>
            <tr>
                <td class="bold">Pending Fees</td>
                <td class="amount" style="color: #616161;"><span class="currency"></span>{{ number_format($totalPendingFees, 2) }}</td>
                <td class="bold">Refund Filter applied</td>
                <td>{{ ucfirst($refundDisputeFilter) }}</td>
            </tr>
            <tr>
                <td class="bold">Cancelled Orders</td>
                <td style="color: #C0392B;">{{ $cancelledCount }}</td>
                <td class="bold">Total Cumulative Revenue</td>
                <td class="amount"><span class="currency"></span>{{ number_format($totalRevenue, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        &copy; {{ date('Y') }} Platform Management System | Automated Analytics System
    </div>
</body>
</html>
