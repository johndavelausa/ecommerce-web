<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * A2 — Delivery Fee Rules (v1.3): seller can choose free, flat rate, or per-product fee.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->enum('delivery_option', ['free', 'flat_rate', 'per_product'])->default('free')->after('subscription_status');
            $table->decimal('delivery_fee', 10, 2)->nullable()->after('delivery_option');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->nullable()->after('condition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['delivery_option', 'delivery_fee']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('delivery_fee');
        });
    }
};
