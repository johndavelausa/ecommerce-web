<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;

echo "Checking order status history...\n\n";

$totalOrders = Order::count();
echo "Total orders: {$totalOrders}\n";

$ordersWithHistory = Order::has('statusHistory')->count();
echo "Orders with status history: {$ordersWithHistory}\n\n";

$order = Order::with('statusHistory')->first();
if ($order) {
    echo "Sample Order ID: {$order->id}\n";
    echo "Current Status: {$order->status}\n";
    echo "Status History Records: {$order->statusHistory->count()}\n\n";
    
    if ($order->statusHistory->count() > 0) {
        echo "Status History:\n";
        foreach ($order->statusHistory as $history) {
            echo "  - From: " . ($history->from_status ?? 'null') . " -> To: {$history->to_status} at {$history->created_at}\n";
        }
    } else {
        echo "NO STATUS HISTORY RECORDS FOUND!\n";
    }
    
    echo "\n";
    echo "Full Tracking Timeline:\n";
    $timeline = $order->full_tracking_timeline;
    if (empty($timeline)) {
        echo "  TIMELINE IS EMPTY!\n";
    } else {
        foreach ($timeline as $item) {
            echo "  - {$item['title']} at {$item['occurred_at']}\n";
        }
    }
}

echo "\nDone.\n";
