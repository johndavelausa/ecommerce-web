<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Simplify dispute flow - remove admin involvement, seller handles everything (TikTok Shop style)
     */
    public function up(): void
    {
        if (Schema::hasTable('order_disputes')) {
            // Add new seller-managed fields
            Schema::table('order_disputes', function (Blueprint $table) {
                if (!Schema::hasColumn('order_disputes', 'seller_resolution_action')) {
                    $table->string('seller_resolution_action', 50)->nullable()->after('seller_responded_at');
                }
                if (!Schema::hasColumn('order_disputes', 'return_tracking_number')) {
                    $table->string('return_tracking_number', 100)->nullable()->after('seller_resolution_action');
                }
            });

            // Update any existing admin-review statuses to seller_review FIRST
            DB::table('order_disputes')
                ->where('status', 'under_admin_review')
                ->update(['status' => 'seller_review']);
                
            DB::table('order_disputes')
                ->whereIn('status', ['resolved_approved', 'resolved_rejected'])
                ->update(['status' => 'closed']);

            // Remove admin-related fields
            Schema::table('order_disputes', function (Blueprint $table) {
                if (Schema::hasColumn('order_disputes', 'admin_resolution_note')) {
                    $table->dropColumn('admin_resolution_note');
                }
                if (Schema::hasColumn('order_disputes', 'resolved_by_admin_id')) {
                    $table->dropForeign(['resolved_by_admin_id']);
                    $table->dropColumn('resolved_by_admin_id');
                }
            });

            // Update status enum to remove admin statuses
            DB::statement("ALTER TABLE order_disputes MODIFY COLUMN status ENUM('open', 'seller_review', 'return_requested', 'return_in_transit', 'return_received', 'refund_pending', 'refund_completed', 'closed') DEFAULT 'open'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_disputes')) {
            Schema::table('order_disputes', function (Blueprint $table) {
                $table->text('admin_resolution_note')->nullable();
                $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dropColumn(['seller_resolution_action', 'return_tracking_number']);
            });

            DB::statement("ALTER TABLE order_disputes MODIFY COLUMN status ENUM('open', 'seller_review', 'under_admin_review', 'return_requested', 'return_in_transit', 'return_received', 'refund_pending', 'refund_completed', 'resolved_approved', 'resolved_rejected', 'closed') DEFAULT 'open'");
        }
    }
};
