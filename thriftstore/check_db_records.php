<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking database records...\n\n";

$count = DB::table('order_status_history')->count();
echo "Total records in order_status_history table: {$count}\n\n";

if ($count > 0) {
    echo "Sample records:\n";
    $records = DB::table('order_status_history')->orderBy('id', 'desc')->limit(10)->get();
    foreach ($records as $record) {
        echo "  ID: {$record->id}, Order: {$record->order_id}, From: " . ($record->from_status ?? 'null') . " -> To: {$record->to_status}\n";
    }
}

echo "\nDone.\n";
