<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;

echo "Testing Order -> StatusHistory relationship...\n\n";

// Direct query
$directCount = DB::table('order_status_history')->where('order_id', 1)->count();
echo "Direct DB query for order_id=1: {$directCount} records\n";

// Using OrderStatusHistory model
$modelCount = OrderStatusHistory::where('order_id', 1)->count();
echo "Using OrderStatusHistory model: {$modelCount} records\n";

// Using relationship
$order = Order::find(1);
echo "\nOrder ID: {$order->id}\n";
echo "Order status: {$order->status}\n";

// Try to load relationship
$order->load('statusHistory');
echo "Relationship loaded, count: " . $order->statusHistory->count() . "\n";

// Try direct relationship query
$relCount = $order->statusHistory()->count();
echo "Direct relationship query: {$relCount} records\n";

if ($relCount > 0) {
    echo "\nStatus history via relationship:\n";
    foreach ($order->statusHistory as $history) {
        echo "  - {$history->from_status} -> {$history->to_status}\n";
    }
}

echo "\nDone.\n";
