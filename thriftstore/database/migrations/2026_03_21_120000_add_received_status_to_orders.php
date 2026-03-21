<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add received_at timestamp to orders table
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'received_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('received_at')->nullable()->after('delivered_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'received_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('received_at');
            });
        }
    }
};
