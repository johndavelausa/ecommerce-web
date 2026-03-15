-- Incremental schema patch for existing databases
-- Apply this if your DB already exists and you do NOT want to re-import shop_db.sql.

USE `shop_db1`;

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `courier_name` VARCHAR(50) NULL DEFAULT NULL AFTER `seller_id`,
  ADD COLUMN IF NOT EXISTS `shipped_at` TIMESTAMP NULL DEFAULT NULL AFTER `estimated_delivery_date`,
  ADD COLUMN IF NOT EXISTS `delivered_at` TIMESTAMP NULL DEFAULT NULL AFTER `shipped_at`,
  ADD COLUMN IF NOT EXISTS `completed_at` TIMESTAMP NULL DEFAULT NULL AFTER `delivered_at`,
  ADD COLUMN IF NOT EXISTS `cancelled_by_type` ENUM('system','admin','seller','customer') NULL DEFAULT NULL AFTER `cancelled_at`,
  ADD COLUMN IF NOT EXISTS `cancellation_reason_code` VARCHAR(50) NULL DEFAULT NULL AFTER `cancelled_by_type`,
  ADD COLUMN IF NOT EXISTS `cancellation_reason_note` TEXT NULL AFTER `cancellation_reason_code`,
  ADD COLUMN IF NOT EXISTS `refund_status` ENUM('not_required','pending','completed') NULL DEFAULT NULL AFTER `cancellation_reason_note`,
  ADD COLUMN IF NOT EXISTS `refund_reason_code` VARCHAR(50) NULL DEFAULT NULL AFTER `refund_status`,
  ADD COLUMN IF NOT EXISTS `refunded_at` TIMESTAMP NULL DEFAULT NULL AFTER `refund_reason_code`;

ALTER TABLE `orders`
  MODIFY COLUMN `status` ENUM('awaiting_payment','paid','to_pack','ready_to_ship','processing','shipped','out_for_delivery','delivered','completed','cancelled') NOT NULL DEFAULT 'awaiting_payment';

ALTER TABLE `orders`
  ADD INDEX IF NOT EXISTS `orders_courier_name_index` (`courier_name`),
  ADD INDEX IF NOT EXISTS `orders_tracking_number_index` (`tracking_number`),
  ADD INDEX IF NOT EXISTS `orders_completed_at_index` (`completed_at`),
  ADD INDEX IF NOT EXISTS `orders_cancellation_reason_code_index` (`cancellation_reason_code`),
  ADD INDEX IF NOT EXISTS `orders_refund_status_index` (`refund_status`);

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
);

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
);

ALTER TABLE `order_disputes`
  ADD COLUMN IF NOT EXISTS `seller_response_note` TEXT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `seller_responded_at` TIMESTAMP NULL DEFAULT NULL AFTER `seller_response_note`;

ALTER TABLE `order_disputes`
  ADD INDEX IF NOT EXISTS `order_disputes_seller_responded_at_index` (`seller_responded_at`);

ALTER TABLE `order_disputes`
  MODIFY COLUMN `status` ENUM('open','seller_review','under_admin_review','return_requested','return_in_transit','return_received','refund_pending','refund_completed','resolved_approved','resolved_rejected','closed') NOT NULL DEFAULT 'open';

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
);

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
);

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
);
