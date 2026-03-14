<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Packing Slip #{{ $order->id }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12px; color: #111827; margin: 0; padding: 16px; }
        .sheet { max-width: 700px; margin: 0 auto; border: 1px solid #e5e7eb; padding: 16px 20px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 0 0 4px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .meta, .addresses { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
        .meta div, .addresses div { flex: 1; }
        .label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .08em; }
        .value { font-size: 13px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 6px 4px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .08em; }
        tfoot td { border-top: 1px solid #d1d5db; font-weight: 600; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .small { font-size: 11px; color: #6b7280; }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .print-btn { display: inline-block; margin-bottom: 12px; padding: 6px 10px; border: 1px solid #4f46e5; color: #4f46e5; border-radius: 4px; font-size: 11px; text-decoration: none; }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
            .sheet { border: none; margin: 0; }
        }
    </style>
</head>
<body>
    <a href="#" class="print-btn" onclick="window.print(); return false;">Print packing slip</a>

    <div class="sheet">
        <h1>Packing Slip</h1>

        <div class="meta">
            <div>
                <div class="label">Order #</div>
                <div class="value">{{ $order->id }}</div>
            </div>
            <div>
                <div class="label">Tracking #</div>
                <div class="value">{{ $order->tracking_number ?? '—' }}</div>
            </div>
            <div>
                <div class="label">Date</div>
                <div class="value">{{ optional($order->created_at)->format('Y-m-d H:i') }}</div>
            </div>
        </div>

        <div class="addresses">
            <div>
                <h2>Ship To</h2>
                <div class="value">{{ $order->customer->name ?? 'Customer' }}</div>
                @if($order->customer && $order->customer->contact_number)
                    <div class="small">{{ $order->customer->contact_number }}</div>
                @endif
                <div class="small mt-2" style="white-space: pre-wrap;">{{ $order->shipping_address }}</div>
            </div>
            <div>
                <h2>From Seller</h2>
                <div class="value">{{ $order->seller->store_name ?? 'Seller' }}</div>
                @if($order->seller && $order->seller->user)
                    <div class="small">{{ $order->seller->user->name }}</div>
                    <div class="small">{{ $order->seller->user->contact_number }}</div>
                @endif
            </div>
        </div>

        <h2>Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Line total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product->name ?? 'Product' }}</td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">₱{{ number_format((float) $item->price_at_purchase, 2) }}</td>
                        <td class="text-right">₱{{ number_format((float) $item->price_at_purchase * $item->quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right">Total</td>
                    <td class="text-right">₱{{ number_format((float) $order->total_amount, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-4 small text-center">
            Thank you for your order!
        </div>
    </div>
</body>
</html>

