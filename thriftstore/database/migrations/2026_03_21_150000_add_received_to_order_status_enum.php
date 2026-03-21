<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'received' to the order status enum.
     */
    public function up(): void
    {
        // MySQL doesn't allow direct ENUM modification, so we need to use raw SQL
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('awaiting_payment', 'paid', 'to_pack', 'ready_to_ship', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'received', 'completed', 'cancelled') DEFAULT 'awaiting_payment'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('awaiting_payment', 'paid', 'to_pack', 'ready_to_ship', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled') DEFAULT 'awaiting_payment'");
    }
};
