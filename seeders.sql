-- Thrift Store Platform — Seed data (sample users, sellers, products, orders, etc.)
-- Run after shop_db.sql. Uses same database name as shop_db.sql (shop_db1).
-- Change USE database below if your DB name is different (e.g. shop_db).

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Use the same database as shop_db.sql
USE `shop_db1`;

-- Password hash used for all seeded users: "password" (bcrypt)
SET @pwd = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
SET @now = NOW();

-- ------------------------------------------------------------
-- Spatie roles (guard_name = web)
-- ------------------------------------------------------------
INSERT IGNORE INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'web', @now, @now),
(2, 'seller', 'web', @now, @now),
(3, 'customer', 'web', @now, @now);

-- ------------------------------------------------------------
-- Users: 1 admin, 3 sellers, 5 customers
-- ------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `email_verified_at`, `contact_number`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'admin', 'admin@thriftstore.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', @now, NULL, @now, @now),
(2, 'Maria Santos', 'mariasantos', 'maria@thriftstore.local', @pwd, @now, '09171234567', @now, @now),
(3, 'Juan Dela Cruz', 'juandc', 'juan@thriftstore.local', @pwd, @now, '09187654321', @now, @now),
(4, 'Ana Reyes', 'anareyes', 'ana@thriftstore.local', @pwd, @now, '09198887777', @now, @now),
(5, 'Carlos Buyer', 'carlosb', 'carlos@thriftstore.local', @pwd, @now, NULL, @now, @now),
(6, 'Liza Shopper', 'lizashop', 'liza@thriftstore.local', @pwd, @now, NULL, @now, @now),
(7, 'Pedro Customer', 'pedroc', 'pedro@thriftstore.local', @pwd, @now, NULL, @now, @now),
(8, 'Sofia Buyer', 'sofiab', 'sofia@thriftstore.local', @pwd, @now, NULL, @now, @now),
(9, 'Miguel Customer', 'miguelc', 'miguel@thriftstore.local', @pwd, @now, NULL, @now, @now)
ON DUPLICATE KEY UPDATE `updated_at` = @now;

-- Assign roles (model_has_roles: role_id, model_type, model_id)
INSERT IGNORE INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2),
(2, 'App\\Models\\User', 3),
(2, 'App\\Models\\User', 4),
(3, 'App\\Models\\User', 5),
(3, 'App\\Models\\User', 6),
(3, 'App\\Models\\User', 7),
(3, 'App\\Models\\User', 8),
(3, 'App\\Models\\User', 9);

-- ------------------------------------------------------------
-- Sellers (user_id 2, 3, 4)
-- ------------------------------------------------------------
INSERT INTO `sellers` (`id`, `user_id`, `store_name`, `store_description`, `gcash_number`, `is_open`, `status`, `subscription_due_date`, `subscription_status`, `delivery_option`, `delivery_fee`, `is_verified`, `business_hours`, `created_at`, `updated_at`) VALUES
(1, 2, 'Maria\'s Thrift Corner', 'Quality pre-loved clothing and accessories. We focus on sustainable fashion and great finds.', '09171234567', 1, 'approved', DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 'active', 'flat_rate', 50.00, 1, 'Mon-Sat 9AM-6PM\nSun 10AM-4PM', @now, @now),
(2, 3, 'Juan Vintage Finds', 'Vintage and retro items. From jackets to bags, all curated with care.', '09187654321', 1, 'approved', DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 'active', 'free', NULL, 1, 'Tue-Sun 10AM-7PM', @now, @now),
(3, 4, 'Ana Secondhand Style', 'Trendy secondhand fashion for everyone. Like-new condition at thrift prices.', '09198887777', 1, 'approved', DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 'active', 'per_product', NULL, 0, NULL, @now, @now)
ON DUPLICATE KEY UPDATE `updated_at` = @now;

-- ------------------------------------------------------------
-- Addresses (customers 5–9)
-- ------------------------------------------------------------
INSERT IGNORE INTO `addresses` (`id`, `user_id`, `label`, `recipient_name`, `line1`, `line2`, `city`, `region`, `postal_code`, `phone`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 5, 'Home', NULL, '123 Main St', 'Apt 4B', 'Manila', 'NCR', '1000', NULL, 1, @now, @now),
(2, 6, 'Home', NULL, '456 Oak Ave', NULL, 'Quezon City', 'NCR', '1100', NULL, 1, @now, @now),
(3, 7, 'Office', NULL, '789 Business Blvd', NULL, 'Makati', 'NCR', '1200', NULL, 1, @now, @now);

-- ------------------------------------------------------------
-- Payments (registration + subscription per seller)
-- ------------------------------------------------------------
INSERT IGNORE INTO `payments` (`id`, `seller_id`, `type`, `amount`, `gcash_number`, `reference_number`, `screenshot_path`, `status`, `approved_at`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'registration', 500.00, '09171234567', 'REG-1-20260314', 'payments/placeholder.png', 'approved', @now, @now, @now, @now),
(2, 1, 'subscription', 299.00, '09171234567', 'SUB-1-20260314', 'payments/placeholder.png', 'approved', @now, @now, @now, @now),
(3, 2, 'registration', 500.00, '09187654321', 'REG-2-20260314', 'payments/placeholder.png', 'approved', @now, @now, @now, @now),
(4, 2, 'subscription', 299.00, '09187654321', 'SUB-2-20260314', 'payments/placeholder.png', 'approved', @now, @now, @now, @now),
(5, 3, 'registration', 500.00, '09198887777', 'REG-3-20260314', 'payments/placeholder.png', 'approved', @now, @now, @now, @now),
(6, 3, 'subscription', 299.00, '09198887777', 'SUB-3-20260314', 'payments/placeholder.png', 'approved', @now, @now, @now, @now);

-- ------------------------------------------------------------
-- Products (sellers 1–3, placeholder image)
-- ------------------------------------------------------------
INSERT INTO `products` (`id`, `seller_id`, `name`, `description`, `category`, `tags`, `price`, `sale_price`, `stock`, `image_path`, `is_active`, `condition`, `size_variant`, `views`, `low_stock_threshold`, `created_at`, `updated_at`) VALUES
(1, 1, 'Classic Denim Jacket', 'Quality pre-loved item. Classic Denim Jacket in like_new condition. Great find!', 'Clothing', 'denim, jacket, casual', 850.00, 650.00, 3, 'products/placeholder.png', 1, 'like_new', 'm', 12, 5, @now, @now),
(2, 1, 'Floral Summer Dress', 'Quality pre-loved item. Floral Summer Dress in good condition. Great find!', 'Clothing', 'dress, floral, summer', 450.00, NULL, 5, 'products/placeholder.png', 1, 'good', 's', 8, 5, @now, @now),
(3, 1, 'Leather Crossbody Bag', 'Quality pre-loved item. Leather Crossbody Bag in good condition. Great find!', 'Accessories', 'bag, leather', 1200.00, 999.00, 2, 'products/placeholder.png', 1, 'good', NULL, 25, 5, @now, @now),
(4, 1, 'Striped Cotton Shirt', 'Quality pre-loved item. Striped Cotton Shirt in new condition. Great find!', 'Clothing', 'shirt, striped', 350.00, NULL, 8, 'products/placeholder.png', 1, 'new', 'l', 5, 5, @now, @now),
(5, 2, 'Classic Denim Jacket', 'Quality pre-loved item. Classic Denim Jacket in like_new condition. Great find!', 'Clothing', 'denim, jacket, casual', 850.00, NULL, 4, 'products/placeholder.png', 1, 'like_new', 'l', 15, 5, @now, @now),
(6, 2, 'High-Waist Trousers', 'Quality pre-loved item. High-Waist Trousers in like_new condition. Great find!', 'Clothing', 'pants, formal', 550.00, 400.00, 4, 'products/placeholder.png', 1, 'like_new', 'm', 9, 5, @now, @now),
(7, 2, 'Canvas Sneakers', 'Quality pre-loved item. Canvas Sneakers in good condition. Great find!', 'Shoes', 'shoes, casual', 800.00, NULL, 6, 'products/placeholder.png', 1, 'good', '42', 20, 5, @now, @now),
(8, 2, 'Vintage Sunglasses', 'Quality pre-loved item. Vintage Sunglasses in good condition. Great find!', 'Accessories', 'sunglasses, vintage', 280.00, NULL, 10, 'products/placeholder.png', 1, 'good', NULL, 3, 5, @now, @now),
(9, 3, 'Knit Cardigan', 'Quality pre-loved item. Knit Cardigan in fair condition. Great find!', 'Clothing', 'cardigan, knit', 380.00, 250.00, 2, 'products/placeholder.png', 1, 'fair', 'xl', 7, 5, @now, @now),
(10, 3, 'Cotton Tote Bag', 'Quality pre-loved item. Cotton Tote Bag in new condition. Great find!', 'Accessories', 'tote, eco', 220.00, 180.00, 15, 'products/placeholder.png', 1, 'new', NULL, 18, 5, @now, @now),
(11, 3, 'Running Shorts', 'Quality pre-loved item. Running Shorts in like_new condition. Great find!', 'Clothing', 'shorts, sports', 320.00, NULL, 7, 'products/placeholder.png', 1, 'like_new', 'm', 4, 5, @now, @now);

-- ------------------------------------------------------------
-- Orders (customers 5–8, mixed statuses)
-- ------------------------------------------------------------
INSERT INTO `orders` (`id`, `customer_id`, `seller_id`, `tracking_number`, `estimated_delivery_date`, `status`, `total_amount`, `shipping_address`, `customer_note`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 'TRK1A2B3C4D', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'delivered', 1300.00, '123 Main St, Barangay 1, Manila City 1000', NULL, DATE_SUB(@now, INTERVAL 10 DAY), @now),
(2, 5, 2, NULL, NULL, 'processing', 800.00, '123 Main St, Barangay 1, Manila City 1000', 'Please leave at gate.', DATE_SUB(@now, INTERVAL 2 DAY), @now),
(3, 6, 1, 'TRK5E6F7G8H', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'shipped', 1050.00, '456 Oak Ave, Quezon City 1100', NULL, DATE_SUB(@now, INTERVAL 5 DAY), @now),
(4, 7, 2, NULL, NULL, 'processing', 400.00, '789 Business Blvd, Makati 1200', NULL, DATE_SUB(@now, INTERVAL 1 DAY), @now),
(5, 8, 3, 'TRK9I0J1K2L', CURDATE(), 'delivered', 430.00, '321 Pine St, Cebu City 6000', NULL, DATE_SUB(@now, INTERVAL 14 DAY), @now);

-- ------------------------------------------------------------
-- Order items
-- ------------------------------------------------------------
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price_at_purchase`, `created_at`) VALUES
(1, 1, 2, 650.00, @now),
(2, 5, 1, 800.00, @now),
(3, 3, 1, 999.00, @now),
(3, 2, 1, 450.00, @now),
(4, 6, 1, 400.00, @now),
(5, 9, 1, 250.00, @now),
(5, 10, 1, 180.00, @now);

-- ------------------------------------------------------------
-- Reviews (for delivered orders only)
-- ------------------------------------------------------------
INSERT IGNORE INTO `reviews` (`customer_id`, `product_id`, `order_id`, `rating`, `body`, `created_at`, `updated_at`) VALUES
(5, 1, 1, 5, 'Great quality, exactly as described. Fast shipping!', @now, @now),
(8, 9, 5, 4, 'Very happy with my purchase. Will buy again.', @now, @now),
(8, 10, 5, 5, 'Item was in good condition. Thank you!', @now, @now);

-- ------------------------------------------------------------
-- Wishlists
-- ------------------------------------------------------------
INSERT IGNORE INTO `wishlists` (`customer_id`, `product_id`, `created_at`) VALUES
(5, 3, @now),
(5, 7, @now),
(6, 1, @now),
(6, 5, @now),
(7, 10, @now);

-- ------------------------------------------------------------
-- Platform announcements
-- ------------------------------------------------------------
INSERT IGNORE INTO `announcements` (`id`, `created_by`, `target_role`, `title`, `body`, `is_active`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'platform', 'Welcome to ThriftStore', 'Browse pre-loved items from verified sellers. Sustainable fashion, great prices.', 1, DATE_ADD(@now, INTERVAL 1 MONTH), @now, @now),
(2, 1, 'platform', 'New Sellers This Week', 'Check out the latest thrift stores and their curated collections.', 1, NULL, @now, @now);

-- ------------------------------------------------------------
-- System settings (defaults)
-- ------------------------------------------------------------
INSERT IGNORE INTO `system_settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
('logo_path', 'defaults/logo.png', @now, @now),
('background_path', 'defaults/background.jpg', @now, @now),
('gcash_qr_path', 'defaults/gcash-qr.png', @now, @now),
('gcash_number', '09XXXXXXXXX', @now, @now);

SET foreign_key_checks = 1;

-- Seeded users — login with password "password" for all (this file uses same bcrypt hash).
-- If you use Laravel AdminSeeder, admin@thriftstore.local will have password admin12345.
-- admin@thriftstore.local (admin)
-- maria@thriftstore.local, juan@thriftstore.local, ana@thriftstore.local (sellers)
-- carlos@thriftstore.local, liza@thriftstore.local, pedro@thriftstore.local, sofia@thriftstore.local, miguel@thriftstore.local (customers)
--
-- If your DB name is shop_db (e.g. from .env), change the line: USE `shop_db1`; to USE `shop_db`;
