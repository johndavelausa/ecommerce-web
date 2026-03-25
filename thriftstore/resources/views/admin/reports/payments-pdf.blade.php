<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment History Report</title>
    <style>
        body { font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 11px; line-height: 1.4; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1B7A37; padding-bottom: 10px; }
        .header h1 { color: #1B7A37; font-size: 24px; margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .header p { color: #666; margin: 5px 0 0; font-style: italic; }
        
        .meta { margin-bottom: 20px; display: flex; justify-content: space-between; font-size: 10px; color: #777; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f2f7f3; color: #1B7A37; text-align: left; padding: 8px; border-bottom: 1px solid #D4E8DA; text-transform: uppercase; font-size: 9px; }
        td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .status { padding: 3px 8px; border-radius: 10px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .status-approved { background-color: #E8F5E9; color: #2E7D32; }
        .status-pending { background-color: #FFF3E0; color: #E65100; }
        .status-rejected { background-color: #FFEBEE; color: #C62828; }
        
        .amount { font-family: 'DejaVu Sans', sans-serif; font-weight: bold; color: #1B7A37; }
        .currency { font-family: 'DejaVu Sans', sans-serif; font-weight: normal; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #aaa; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payment History</h1>
        <p>Official Platform Financial Record</p>
    </div>

    <div class="meta">
        <span>Generated on: {{ $exportedAt->format('F j, Y g:i A') }}</span>
        <span style="float: right;">Total Records: {{ count($payments) }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Seller / Store</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Reference</th>
                <th>Date Paid</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $p)
                <tr>
                    <td style="color: #999;">#{{ $p->id }}</td>
                    <td>
                        <div style="font-weight: bold; color: #333;">{{ $p->seller?->store_name ?? 'N/A' }}</div>
                        <div style="font-size: 9px; color: #777;">{{ $p->seller?->user?->email ?? '' }}</div>
                    </td>
                    <td>{{ ucfirst($p->type) }}</td>
                    <td class="amount"><span class="currency">&#8369;</span>{{ number_format($p->amount, 2) }}</td>
                    <td>
                        <span class="status status-{{ $p->status }}">
                            {{ ucfirst($p->status) }}
                        </span>
                    </td>
                    <td style="font-family: monospace; font-size: 9px;">{{ $p->reference_number ?? '—' }}</td>
                    <td>{{ $p->paid_at ? $p->paid_at->format('M d, Y') : ($p->created_at ? $p->created_at->format('M d, Y') : '—') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        &copy; {{ date('Y') }} Platform Management System | Confidential Document
    </div>
</body>
</html>
