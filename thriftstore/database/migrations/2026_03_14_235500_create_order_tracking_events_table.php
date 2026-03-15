<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tracking_number', 120)->nullable();
            $table->string('courier_name', 50)->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('event_status', 80);
            $table->string('event_code', 80)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
            $table->index(['tracking_number', 'occurred_at']);
            $table->index(['provider', 'event_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking_events');
    }
};
