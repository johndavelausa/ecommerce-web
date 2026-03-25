<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $seller->store_name }} - Sales Report</title>
    <style>
        body { font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 11px; line-height: 1.4; margin: 0; padding: 25px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1B7A37; padding-bottom: 15px; }
        .header h1 { color: #1B7A37; font-size: 26px; margin: 0; text-transform: uppercase; letter-spacing: 3px; font-weight: 800; }
        .header p { color: #888; margin: 5px 0 0; font-style: italic; font-size: 12px; }
        
        .section-title { font-size: 14px; font-weight: 800; color: #1B7A37; margin: 25px 0 10px; border-left: 4px solid #F9C74F; padding-left: 10px; text-transform: uppercase; letter-spacing: 1px; }
        
        .kpi-grid { display: block; overflow: auto; margin-bottom: 20px; }
        .kpi-row { margin-bottom: 10px; width: 100%; }
        .kpi-card { float: left; width: 30.5%; padding: 12px; border: 1px solid #D4E8DA; border-radius: 10px; background-color: #fcfdfc; margin-right: 2%; text-align: center; height: 65px; }
        .kpi-label { font-size: 8px; color: #777; font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 16px; font-weight: 900; color: #0F3D22; }
        
        .clear { clear: both; }

        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { background-color: #f2f7f3; color: #1B7A37; text-align: left; padding: 8px; border: 1px solid #D4E8DA; text-transform: uppercase; font-size: 9px; }
        td { padding: 8px; border: 1px solid #D4E8DA; vertical-align: top; font-size: 10px; }
        
        .even { background-color: #fcfdfc; }
        .bold { font-weight: bold; }
        .amount { font-family: 'DejaVu Sans', sans-serif; font-weight: bold; color: #1B7A37; }
        .currency { font-family: 'DejaVu Sans', sans-serif; font-weight: normal; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #aaa; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $seller->store_name }} Report</h1>
        <p>Period: {{ ucfirst($period) }} | Performance Summary</p>
    </div>

    <div style="font-size: 9px; color: #777; margin-bottom: 20px; text-align: right;">
        Exported: {{ $exportedAt->format('F j, Y g:i A') }}
    </div>

    <div class="section-title">Sales Performance</div>
    <div class="kpi-grid">
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-label">Lifetime Revenue</div>
                <div class="kpi-value"><span class="currency"></span>{{ number_format($totalRevenue, 2) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Revenue ({{ ucfirst($period) }})</div>
                <div class="kpi-value"><span class="currency"></span>{{ number_format($periodSales, 2) }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Completed Orders</div>
                <div class="kpi-value">{{ $completedOrdersCount }}</div>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <div style="width: 100%; margin-top: 10px;">
        <div style="float: left; width: 48%;">
            <div class="section-title">Recent Monthly Totals</div>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($monthlyRevenue as $index => $row)
                        <tr class="{{ $index % 2 != 0 ? 'even' : '' }}">
                            <td>{{ $row->ym }}</td>
                            <td class="amount"><span class="currency"></span>{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div style="float: right; width: 48%;">
            <div class="section-title">Best Sellers</div>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="text-align: right;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topProducts as $index => $p)
                        <tr class="{{ $index % 2 != 0 ? 'even' : '' }}">
                            <td>{{ $p->name }}</td>
                            <td style="text-align: right; font-weight: bold; color: #1B7A37;">{{ $p->qty }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="section-title">Summary Observations</div>
    <table style="margin-top: 0;">
        <tbody>
            <tr>
                <td class="bold">Total Unsuccessful Orders</td>
                <td style="color: #C0392B;">{{ $cancelledOrdersCount }}</td>
                <td class="bold">Average Revenue / Order</td>
                <td class="amount">
                    <span class="currency"></span>{{ $completedOrdersCount > 0 ? number_format($totalRevenue / $completedOrdersCount, 2) : '0.00' }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        &copy; {{ date('Y') }} {{ $seller->store_name }} | Generated for Platform Performance Review
    </div>
</body>
</html>
