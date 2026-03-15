<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * A1 — Product Condition Field (v1.3): required condition for thrift/ukay items.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('condition', ['new', 'like_new', 'good', 'fair', 'poor'])
                ->default('good')
                ->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
};
