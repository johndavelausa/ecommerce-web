<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Populate missing status history for existing orders.
     */
    public function up(): void
    {
        // Get all orders that don't have status history
        $orders = DB::table('orders')
            ->select('id', 'status', 'created_at')
            ->get();

        foreach ($orders as $order) {
            // Check if this order already has status history
            $hasHistory = DB::table('order_status_history')
                ->where('order_id', $order->id)
                ->exists();

            if (!$hasHistory) {
                // Create initial status history record
                DB::table('order_status_history')->insert([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => $order->status,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => $order->created_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete the populated records
    }
};
