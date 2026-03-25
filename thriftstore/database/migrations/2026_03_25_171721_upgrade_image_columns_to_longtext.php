<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 💎 Final Bullet-Proof Upgrade for your specific schema! 💎
        
        // Products table only has image_path
        DB::statement('ALTER TABLE products MODIFY image_path LONGTEXT NULL');

        // Users has avatar
        DB::statement('ALTER TABLE users MODIFY avatar LONGTEXT NULL');

        // Sellers has logo and banner
        DB::statement('ALTER TABLE sellers MODIFY logo_path LONGTEXT NULL');
        DB::statement('ALTER TABLE sellers MODIFY banner_path LONGTEXT NULL');

        // Payments has screenshot
        DB::statement('ALTER TABLE payments MODIFY screenshot_path LONGTEXT NULL');

        // Order Disputes has evidence
        DB::statement('ALTER TABLE order_disputes MODIFY evidence_path LONGTEXT NULL');
        
        // System settings for the Platform Logo
        DB::statement('ALTER TABLE system_settings MODIFY value LONGTEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE products MODIFY image_path VARCHAR(255) NULL');
    }
};
