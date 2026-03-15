<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->id }} Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; }
        .footer { margin-top: 24px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <h1>Order Receipt #{{ $order->id }}</h1>
    <div class="meta">
        <strong>Tracking number:</strong> {{ $order->tracking_number ?? '—' }}<br>
        <strong>Seller:</strong> {{ $order->seller->store_name ?? '—' }}<br>
        <strong>Date of order:</strong> {{ $order->created_at->format('F j, Y \a\t g:i A') }}<br>
        <strong>Date received:</strong> {{ $order->updated_at->format('F j, Y \a\t g:i A') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? 'Product #' . $item->product_id }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">₱{{ number_format($item->price_at_purchase, 2) }}</td>
                    <td class="text-right">₱{{ number_format($item->quantity * (float) $item->price_at_purchase, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="text-right">Delivery fee</td>
                <td class="text-right">(included in total)</td>
            </tr>
            <tr class="total-row">
                <td colspan="3" class="text-right">Total amount</td>
                <td class="text-right">₱{{ number_format($order->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        This is a receipt for your records.
    </div>
</body>
</html>
