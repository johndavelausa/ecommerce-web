<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enums can be tricky to modify with SQLite or different DB drivers
        // Better to change the column type to string or add more items
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('target_role')->default('seller')->change();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('target_role', ['seller', 'platform'])->default('seller')->change();
        });
    }
};
