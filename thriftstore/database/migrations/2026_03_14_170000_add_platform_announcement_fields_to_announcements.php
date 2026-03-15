<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('body');
            $table->timestamp('expires_at')->nullable()->after('is_active');
        });

        // Allow target_role 'platform' for homepage announcements (A4 v1.4)
        DB::statement("ALTER TABLE announcements MODIFY COLUMN target_role ENUM('seller', 'platform') NOT NULL DEFAULT 'seller'");
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'expires_at']);
        });
        DB::statement("ALTER TABLE announcements MODIFY COLUMN target_role ENUM('seller') NOT NULL DEFAULT 'seller'");
    }
};
