<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add location and description fields to order_status_history for detailed tracking.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_status_history')) {
            Schema::table('order_status_history', function (Blueprint $table) {
                if (!Schema::hasColumn('order_status_history', 'location')) {
                    $table->string('location')->nullable()->after('to_status');
                }
                if (!Schema::hasColumn('order_status_history', 'description')) {
                    $table->text('description')->nullable()->after('location');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_status_history')) {
            Schema::table('order_status_history', function (Blueprint $table) {
                $table->dropColumn(['location', 'description']);
            });
        }
    }
};
