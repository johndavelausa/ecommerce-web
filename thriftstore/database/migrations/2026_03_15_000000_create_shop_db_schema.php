<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('pending_email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('contact_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('avatar')->nullable();
            $table->rememberToken();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->string('suspicious_reason')->nullable();
            $table->timestamp('suspicious_flagged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('recipient_name')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->index('user_id');
            $table->index('last_activity');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
            $table->index('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
            $table->index('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index(['queue', 'reserved_at', 'available_at']);
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('target_role', ['seller', 'platform'])->default('seller');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('created_by');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('store_name')->unique();
            $table->text('store_description')->nullable();
            $table->string('gcash_number', 50);
            $table->boolean('is_open')->default(true);
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->date('subscription_due_date')->nullable();
            $table->enum('subscription_status', ['active', 'grace_period', 'lapsed'])->default('active');
            $table->enum('delivery_option', ['free', 'flat_rate', 'per_product'])->default('free');
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->text('business_hours')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('seller_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->text('note');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('seller_id');
        });

        Schema::create('seller_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('action', 100);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['seller_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->enum('type', ['registration', 'subscription']);
            $table->decimal('amount', 10, 2);
            $table->string('gcash_number', 50);
            $table->string('reference_number', 100)->unique();
            $table->string('screenshot_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index('seller_id');
            $table->index(['type', 'status']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('category', 50)->nullable();
            $table->string('tags')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->enum('condition', ['new', 'like_new', 'good', 'fair', 'poor'])->default('good');
            $table->string('size_variant', 100)->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->timestamps();
            $table->index('seller_id');
            $table->index('is_active');
            $table->index('name');
        });

        Schema::create('product_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('action', ['added', 'updated', 'deleted', 'stock_change']);
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('product_id');
        });

        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 100);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('courier_name', 50)->nullable();
            $table->string('tracking_number', 50)->nullable();
            $table->date('estimated_delivery_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['awaiting_payment', 'paid', 'to_pack', 'ready_to_ship', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled'])->default('awaiting_payment');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->text('shipping_address');
            $table->text('customer_note')->nullable();
            $table->unsignedTinyInteger('store_rating')->nullable();
            $table->text('store_review')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('cancelled_by_type', ['system', 'admin', 'seller', 'customer'])->nullable();
            $table->string('cancellation_reason_code', 50)->nullable();
            $table->text('cancellation_reason_note')->nullable();
            $table->enum('refund_status', ['not_required', 'pending', 'completed'])->nullable();
            $table->string('refund_reason_code', 50)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index('customer_id');
            $table->index('seller_id');
            $table->index('status');
            $table->index('courier_name');
            $table->index('tracking_number');
            $table->index('completed_at');
            $table->index('cancellation_reason_code');
            $table->index('refund_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('price_at_purchase', 10, 2);
            $table->timestamps();
            $table->index('order_id');
            $table->index('product_id');
        });

        Schema::create('checkout_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->char('snapshot_token', 36)->unique();
            $table->char('cart_hash', 64);
            $table->char('snapshot_version', 64);
            $table->longText('snapshot_payload');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'consumed_at']);
            $table->index('expires_at');
        });

        Schema::create('order_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tracking_number', 120)->nullable();
            $table->string('courier_name', 50)->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('event_status', 80);
            $table->string('event_code', 80)->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'occurred_at']);
            $table->index(['tracking_number', 'occurred_at']);
            $table->index(['provider', 'event_status']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->enum('actor_type', ['system', 'admin', 'seller', 'customer'])->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index('order_id');
            $table->index('to_status');
            $table->index(['actor_type', 'actor_id']);
        });

        Schema::create('order_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('reason_code', 50);
            $table->text('description');
            $table->string('evidence_path')->nullable();
            $table->enum('status', ['open', 'seller_review', 'under_admin_review', 'return_requested', 'return_in_transit', 'return_received', 'refund_pending', 'refund_completed', 'resolved_approved', 'resolved_rejected', 'closed'])->default('open');
            $table->text('seller_response_note')->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->text('admin_resolution_note')->nullable();
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('seller_id');
            $table->index('status');
            $table->index('seller_responded_at');
        });

        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('platform_fee_rate', 6, 4)->default(0.1000);
            $table->decimal('platform_fee_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2)->default(0.00);
            $table->enum('status', ['pending', 'released', 'on_hold'])->default('pending');
            $table->string('hold_reason', 100)->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status']);
            $table->index('released_at');
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['seller-admin', 'seller-customer', 'guest']);
            $table->timestamps();
            $table->index('seller_id');
            $table->index('customer_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->enum('sender_type', ['admin', 'seller', 'customer', 'guest']);
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index('conversation_id');
            $table->index('is_read');
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'product_id', 'order_id']);
            $table->index('product_id');
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['customer_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value');
            $table->timestamps();
        });

        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('admin_actions');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('order_disputes');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_tracking_events');
        Schema::dropIfExists('checkout_snapshots');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('product_reports');
        Schema::dropIfExists('product_histories');
        Schema::dropIfExists('products');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('seller_activity_logs');
        Schema::dropIfExists('seller_notes');
        Schema::dropIfExists('sellers');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('users');

        Schema::enableForeignKeyConstraints();
    }
};
