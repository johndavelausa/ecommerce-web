<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseCleanupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Stop foreign key checks so we can wipe linked tables without errors
        Schema::disableForeignKeyConstraints();

        $this->command->warn("Starting Total Reset (Admin Only mode)...");

        // 2. Identify the Admins to keep
        // This looks at the Spatie 'model_has_roles' table for 'admin' users
        $adminUserIds = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'admin')
            ->pluck('model_id')
            ->toArray();

        if (empty($adminUserIds)) {
            $this->command->error("No Admins found! Cleanup aborted to prevent locking you out.");
            return;
        }

        // 3. Delete all Users who are NOT Admins
        // This will also trigger Cascading Deletes for 'sellers' in most setups, 
        // but we truncate the tables below to be 100% sure.
        DB::table('users')->whereNotIn('id', $adminUserIds)->delete();
        $this->command->info("Deleted all non-admin users.");

        // 4. Wipe EVERYTHING else (Sellers, Products, Orders, etc.)
        $tablesToWipe = [
            // --- The Core "Removal" ---
            'products',
            'sellers',
            'product_histories',
            'product_reports',
            'seller_notes',
            'seller_activity_logs',
            'seller_payouts',
            
            // --- Transactions & Activity ---
            'orders',
            'order_items',
            'order_status_history',
            'order_status_histories',
            'order_tracking_events',
            'order_disputes',
            'checkout_snapshots',
            'payments',
            'wishlists',
            'reviews',
            'messages',
            'conversations',
            'notifications',
            'admin_actions',
            'addresses',

            // --- System Data ---
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
                $this->command->info("Table Wiped: {$table}");
            }
        }

        // 5. Turn constraints back on
        Schema::enableForeignKeyConstraints();
        
        $this->command->info("--- CLEANUP COMPLETE ---");
        $this->command->info("Remaining: " . DB::table('users')->count() . " Admins.");
        $this->command->info("All Products, Sellers, and Customers have been removed.");
    }
}