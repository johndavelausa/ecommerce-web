<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseCleanupSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Tables to WIPE COMPLETELY (Transactions, Logs, Messages)
        $tablesToWipe = [
            'order_status_history',
            'order_status_histories',
            'order_tracking_events',
            'order_disputes',
            'order_items',
            'orders',
            'seller_payouts',
            'checkout_snapshots',
            'payments',
            'wishlists',
            'reviews',
            'messages',
            'conversations',
            'notifications',
            'product_reports',
            'product_histories',
            'seller_activity_logs',
            'admin_actions',
            'failed_jobs',
            'job_batches',
            'jobs',
            'cache',
            'cache_locks',
            'sessions',
        ];

        foreach ($tablesToWipe as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->command->info("Wiped: {$table}");
            }
        }

        // 2. Reset Product STOCKS to 0 (Keeps the products, just zeros the inventory)
        if (Schema::hasTable('products')) {
            DB::table('products')->update(['stock' => 0]);
            $this->command->info("All product stocks have been reset to 0.");
        }

        Schema::enableForeignKeyConstraints();
        
        $this->command->info("Cleanup complete! Users, Sellers, and Products (empty stock) are ready.");
    }
}