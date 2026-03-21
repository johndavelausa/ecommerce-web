<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Testing status history functionality...\n\n";

// Check if table exists
$tableExists = Schema::hasTable('order_status_history');
echo "Table 'order_status_history' exists: " . ($tableExists ? 'YES' : 'NO') . "\n";

if ($tableExists) {
    $count = DB::table('order_status_history')->count();
    echo "Total records in order_status_history: {$count}\n\n";
}

// Get first order
$order = Order::first();
if (!$order) {
    echo "No orders found in database.\n";
    exit;
}

echo "Testing with Order ID: {$order->id}\n";
echo "Current status: {$order->status}\n";
echo "Status history count BEFORE: " . $order->statusHistory()->count() . "\n\n";

// Try to update status
echo "Attempting to change status to 'to_pack'...\n";
$oldStatus = $order->status;
$order->status = 'to_pack';
$order->save();

echo "Status changed from '{$oldStatus}' to '{$order->status}'\n";

// Refresh and check
$order = $order->fresh();
echo "Status history count AFTER: " . $order->statusHistory()->count() . "\n";

if ($order->statusHistory()->count() > 0) {
    echo "\nStatus history records:\n";
    foreach ($order->statusHistory as $history) {
        echo "  - From: " . ($history->from_status ?? 'null') . " -> To: {$history->to_status} at {$history->created_at}\n";
    }
} else {
    echo "\nERROR: No status history was created!\n";
    echo "This means the Order model's event listeners are not firing.\n";
}

echo "\nDone.\n";
