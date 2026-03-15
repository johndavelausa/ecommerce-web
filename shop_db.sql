-- Thrift Store Platform (Laravel) schema
-- Database: shop_db
--
-- Notes:
-- - This script replaces Laravel migrations for this project.
-- - Import/run this file in MySQL, then configure your Laravel `.env` to use DB_DATABASE=shop_db.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

CREATE DATABASE IF NOT EXISTS `shop_db1`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `shop_db1`;

-- ------------------------------------------------------------
-- Core Laravel tables (aligned with Laravel 12 defaults)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `username` VARCHAR(255) NULL,
  `email` VARCHAR(255) NOT NULL,
  `pending_email` VARCHAR(255) NULL DEFAULT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `avatar` VARCHAR(255) NULL,
  `remember_token` VARCHAR(100) NULL,
  `last_active_at` TIMESTAMP NULL DEFAULT NULL,
  `is_suspicious` TINYINT(1) NOT NULL DEFAULT 0,
  `suspicious_reason` VARCHAR(255) NULL DEFAULT NULL,
  `suspicious_flagged_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `addresses` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `label` VARCHAR(50) NOT NULL,
  `recipient_name` VARCHAR(255) NULL,
  `line1` VARCHAR(255) NOT NULL,
  `line2` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `region` VARCHAR(100) NULL,
  `postal_code` VARCHAR(20) NULL,
  `phone` VARCHAR(50) NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_user_id_index` (`user_id`),
  CONSTRAINT `addresses_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`),
  CONSTRAINT `sessions_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_at_available_at_index` (`queue`, `reserved_at`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` CHAR(36) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `notifiable_type` VARCHAR(255) NOT NULL,
  `notifiable_id` BIGINT UNSIGNED NOT NULL,
  `data` TEXT NOT NULL,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements: seller broadcasts + platform homepage banner (A4 v1.4)
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` BIGINT UNSIGNED NULL,
  `target_role` ENUM('seller','platform') NOT NULL DEFAULT 'seller',
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_created_by_index` (`created_by`),
  CONSTRAINT `announcements_created_by_foreign`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Spatie Laravel Permission tables
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`, `model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `model_has_roles` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `model_id`, `model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`, `model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Domain tables
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sellers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `store_name` VARCHAR(255) NOT NULL,
  `store_description` TEXT NULL,
  `gcash_number` VARCHAR(50) NOT NULL,
  `is_open` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `subscription_due_date` DATE NULL DEFAULT NULL,
  `subscription_status` ENUM('active','grace_period','lapsed') NOT NULL DEFAULT 'active',
  `delivery_option` ENUM('free','flat_rate','per_product') NOT NULL DEFAULT 'free',
  `delivery_fee` DECIMAL(10,2) NULL DEFAULT NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `business_hours` TEXT NULL,
  `banner_path` VARCHAR(255) NULL,
  `logo_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sellers_store_name_unique` (`store_name`),
  UNIQUE KEY `sellers_user_id_unique` (`user_id`),
  KEY `sellers_status_index` (`status`),
  CONSTRAINT `sellers_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A2 v1.4 — Admin notes on seller profile
CREATE TABLE IF NOT EXISTS `seller_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL,
  `admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `seller_notes_seller_id_index` (`seller_id`),
  CONSTRAINT `seller_notes_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_notes_admin_id_foreign`
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A2 v1.4 — Seller activity log
CREATE TABLE IF NOT EXISTS `seller_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` JSON NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `seller_activity_logs_seller_id_created_at_index` (`seller_id`, `created_at`),
  CONSTRAINT `seller_activity_logs_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('registration','subscription') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `gcash_number` VARCHAR(50) NOT NULL,
  `reference_number` VARCHAR(100) NOT NULL,
  `screenshot_path` VARCHAR(255) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` VARCHAR(255) NULL DEFAULT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_reference_number_unique` (`reference_number`),
  KEY `payments_seller_id_index` (`seller_id`),
  KEY `payments_type_status_index` (`type`, `status`),
  CONSTRAINT `payments_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(50) NULL DEFAULT NULL,
  `tags` VARCHAR(255) NULL DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `sale_price` DECIMAL(10,2) NULL DEFAULT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `image_path` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `condition` ENUM('new','like_new','good','fair','poor') NOT NULL DEFAULT 'good',
  `size_variant` VARCHAR(100) NULL DEFAULT NULL,
  `delivery_fee` DECIMAL(10,2) NULL DEFAULT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 10,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_seller_id_index` (`seller_id`),
  KEY `products_active_index` (`is_active`),
  KEY `products_name_index` (`name`),
  CONSTRAINT `products_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_histories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `action` ENUM('added','updated','deleted','stock_change') NOT NULL,
  `old_value` LONGTEXT NULL,
  `new_value` LONGTEXT NULL,
  `note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_histories_product_id_index` (`product_id`),
  CONSTRAINT `product_histories_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `reason` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_reports_product_id_created_at_index` (`product_id`, `created_at`),
  CONSTRAINT `product_reports_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_reports_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `account_deletion_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_deletion_requests_status_created_at_index` (`status`, `created_at`),
  CONSTRAINT `account_deletion_requests_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_deletion_requests_admin_id_foreign`
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `courier_name` VARCHAR(50) NULL DEFAULT NULL,
  `tracking_number` VARCHAR(50) NULL DEFAULT NULL,
  `estimated_delivery_date` DATE NULL DEFAULT NULL,
  `shipped_at` TIMESTAMP NULL DEFAULT NULL,
  `delivered_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('awaiting_payment','paid','to_pack','ready_to_ship','processing','shipped','out_for_delivery','delivered','completed','cancelled') NOT NULL DEFAULT 'awaiting_payment',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_address` TEXT NOT NULL,
  `customer_note` TEXT NULL DEFAULT NULL,
  `store_rating` TINYINT UNSIGNED NULL DEFAULT NULL,
  `store_review` TEXT NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `cancelled_by_type` ENUM('system','admin','seller','customer') NULL DEFAULT NULL,
  `cancellation_reason_code` VARCHAR(50) NULL DEFAULT NULL,
  `cancellation_reason_note` TEXT NULL,
  `refund_status` ENUM('not_required','pending','completed') NULL DEFAULT NULL,
  `refund_reason_code` VARCHAR(50) NULL DEFAULT NULL,
  `refunded_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_customer_id_index` (`customer_id`),
  KEY `orders_seller_id_index` (`seller_id`),
  KEY `orders_status_index` (`status`),
  KEY `orders_courier_name_index` (`courier_name`),
  KEY `orders_tracking_number_index` (`tracking_number`),
  KEY `orders_completed_at_index` (`completed_at`),
  KEY `orders_cancellation_reason_code_index` (`cancellation_reason_code`),
  KEY `orders_refund_status_index` (`refund_status`),
  CONSTRAINT `orders_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `orders_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL,
  `price_at_purchase` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_index` (`order_id`),
  KEY `order_items_product_id_index` (`product_id`),
  CONSTRAINT `order_items_order_id_foreign`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checkout snapshot-lock table for immutable pricing/version guard
CREATE TABLE IF NOT EXISTS `checkout_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `snapshot_token` CHAR(36) NOT NULL,
  `cart_hash` CHAR(64) NOT NULL,
  `snapshot_version` CHAR(64) NOT NULL,
  `snapshot_payload` LONGTEXT NOT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `consumed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkout_snapshots_snapshot_token_unique` (`snapshot_token`),
  KEY `checkout_snapshots_customer_consumed_index` (`customer_id`, `consumed_at`),
  KEY `checkout_snapshots_expires_at_index` (`expires_at`),
  CONSTRAINT `checkout_snapshots_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Courier webhook tracking timeline events
CREATE TABLE IF NOT EXISTS `order_tracking_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `tracking_number` VARCHAR(120) NULL DEFAULT NULL,
  `courier_name` VARCHAR(50) NULL DEFAULT NULL,
  `provider` VARCHAR(80) NULL DEFAULT NULL,
  `event_status` VARCHAR(80) NOT NULL,
  `event_code` VARCHAR(80) NULL DEFAULT NULL,
  `location` VARCHAR(255) NULL DEFAULT NULL,
  `description` TEXT NULL,
  `occurred_at` TIMESTAMP NULL DEFAULT NULL,
  `raw_payload` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_tracking_events_order_id_occurred_at_index` (`order_id`, `occurred_at`),
  KEY `order_tracking_events_tracking_number_occurred_at_index` (`tracking_number`, `occurred_at`),
  KEY `order_tracking_events_provider_event_status_index` (`provider`, `event_status`),
  CONSTRAINT `order_tracking_events_order_id_foreign`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order status history (step-by-step state machine audit trail)
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(50) NULL DEFAULT NULL,
  `to_status` VARCHAR(50) NULL DEFAULT NULL,
  `actor_type` ENUM('system','admin','seller','customer') NOT NULL DEFAULT 'system',
  `actor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_status_history_order_id_index` (`order_id`),
  KEY `order_status_history_to_status_index` (`to_status`),
  KEY `order_status_history_actor_type_actor_id_index` (`actor_type`, `actor_id`),
  CONSTRAINT `order_status_history_order_id_foreign`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Disputes / return-issue cases for orders (customer evidence + admin resolution)
CREATE TABLE IF NOT EXISTS `order_disputes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `reason_code` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `evidence_path` VARCHAR(255) NULL DEFAULT NULL,
  `status` ENUM('open','seller_review','under_admin_review','return_requested','return_in_transit','return_received','refund_pending','refund_completed','resolved_approved','resolved_rejected','closed') NOT NULL DEFAULT 'open',
  `seller_response_note` TEXT NULL,
  `seller_responded_at` TIMESTAMP NULL DEFAULT NULL,
  `admin_resolution_note` TEXT NULL,
  `resolved_by_admin_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_disputes_order_id_index` (`order_id`),
  KEY `order_disputes_customer_id_index` (`customer_id`),
  KEY `order_disputes_seller_id_index` (`seller_id`),
  KEY `order_disputes_status_index` (`status`),
  KEY `order_disputes_seller_responded_at_index` (`seller_responded_at`),
  CONSTRAINT `order_disputes_order_id_foreign`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_disputes_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_disputes_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_disputes_resolved_by_admin_id_foreign`
    FOREIGN KEY (`resolved_by_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seller payout ledger for completed orders (release/hold pipeline)
CREATE TABLE IF NOT EXISTS `seller_payouts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `gross_amount` DECIMAL(10,2) NOT NULL,
  `platform_fee_rate` DECIMAL(6,4) NOT NULL DEFAULT 0.1000,
  `platform_fee_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending','released','on_hold') NOT NULL DEFAULT 'pending',
  `hold_reason` VARCHAR(100) NULL DEFAULT NULL,
  `released_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_payouts_order_id_unique` (`order_id`),
  KEY `seller_payouts_seller_status_index` (`seller_id`, `status`),
  KEY `seller_payouts_released_at_index` (`released_at`),
  CONSTRAINT `seller_payouts_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seller_payouts_order_id_foreign`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NULL,
  `customer_id` BIGINT UNSIGNED NULL,
  `type` ENUM('seller-admin','seller-customer','guest') NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_seller_id_index` (`seller_id`),
  KEY `conversations_customer_id_index` (`customer_id`),
  CONSTRAINT `conversations_seller_id_foreign`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `conversations_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NULL,
  `sender_type` ENUM('admin','seller','customer','guest') NOT NULL,
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `messages_conversation_id_index` (`conversation_id`),
  KEY `messages_is_read_index` (`is_read`),
  CONSTRAINT `messages_conversation_id_foreign`
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `seller_reply` TEXT NULL,
  `seller_replied_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviews_customer_product_order_unique` (`customer_id`, `product_id`, `order_id`),
  KEY `reviews_product_id_index` (`product_id`),
  CONSTRAINT `reviews_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `reviews_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `reviews_order_id_foreign`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wishlists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlists_customer_product_unique` (`customer_id`, `product_id`),
  KEY `wishlists_product_id_index` (`product_id`),
  CONSTRAINT `wishlists_customer_id_foreign`
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `wishlists_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A3 v1.4 — Admin order status override logging
CREATE TABLE IF NOT EXISTS `admin_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(100) NULL,
  `target_id` BIGINT UNSIGNED NULL,
  `reason` TEXT NULL,
  `details` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_actions_action_created_at_index` (`action`, `created_at`),
  KEY `admin_actions_target_index` (`target_type`, `target_id`),
  CONSTRAINT `admin_actions_admin_id_foreign`
    FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

