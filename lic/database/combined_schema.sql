-- ==============================================================================
-- ZoomNearby Central Platform & App Builder — Combined Production Schema
-- Safe to import directly via Hostinger phpMyAdmin or CLI
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Settings Table
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) PRIMARY KEY,
  `v` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Products Table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `custom_features` JSON NULL,
  `app_builder_limit` INT NOT NULL DEFAULT 10,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `package_uploaded_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bundles Table
CREATE TABLE IF NOT EXISTS `bundles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `included_modules` JSON NOT NULL,
  `custom_features` JSON NULL,
  `app_builder_limit` INT NOT NULL DEFAULT 20,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Licenses Table
CREATE TABLE IF NOT EXISTS `licenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `license_key` VARCHAR(64) NOT NULL UNIQUE,
  `product_slug` VARCHAR(64) NOT NULL,
  `client_email` VARCHAR(191) NOT NULL DEFAULT '',
  `bound_domain` VARCHAR(191) NULL,
  `bound_ip` VARCHAR(64) NULL,
  `plan` VARCHAR(64) NULL,
  `status` ENUM('active','suspended','revoked','expired') NOT NULL DEFAULT 'active',
  `valid_until` DATE NULL,
  `last_verified_at` TIMESTAMP NULL,
  `last_verified_ip` VARCHAR(64) NULL,
  `registered_domain` VARCHAR(191) NULL,
  `allowed_domains` JSON NULL,
  `license_type` VARCHAR(32) NOT NULL DEFAULT 'regular',
  `branding_json` JSON NULL,
  `payment_reference` VARCHAR(191) NULL,
  `app_builder_monthly_limit` INT NULL DEFAULT NULL,
  `bundle_id` INT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_lookup` (`license_key`, `product_slug`),
  INDEX `idx_product` (`product_slug`),
  INDEX `idx_email` (`client_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Payments & Orders Table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(191) NOT NULL UNIQUE,
  `gateway` VARCHAR(40) NOT NULL DEFAULT '',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `product_slug` VARCHAR(64) NOT NULL,
  `bundle_slug` VARCHAR(64) NULL,
  `license_id` INT NULL,
  `email` VARCHAR(191) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'paid',
  `order_status` VARCHAR(20) NULL DEFAULT NULL,
  `order_token` VARCHAR(48) NULL UNIQUE,
  `target_domain` VARCHAR(191) NULL,
  `checkout_json` JSON NULL,
  `items_json` JSON NULL,
  `gateway_reference` VARCHAR(191) NULL,
  `gateway_url` TEXT NULL,
  `order_error` TEXT NULL,
  `builder_email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_license` (`license_id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_order_token` (`order_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5b. Dedicated Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_token` VARCHAR(48) NULL UNIQUE,
  `reference` VARCHAR(191) NOT NULL UNIQUE,
  `payment_id` INT NULL,
  `email` VARCHAR(191) NOT NULL DEFAULT '',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `product_slug` VARCHAR(64) NULL,
  `bundle_slug` VARCHAR(64) NULL,
  `order_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `checkout_json` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token` (`order_token`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `orders` (`order_token`, `reference`, `payment_id`, `email`, `amount`, `currency`, `product_slug`, `bundle_slug`, `order_status`, `checkout_json`, `created_at`)
SELECT `order_token`, `reference`, `id`, `email`, `amount`, `currency`, `product_slug`, `bundle_slug`, COALESCE(`order_status`, 'completed'), `checkout_json`, `created_at`
FROM `payments` WHERE `order_token` IS NOT NULL OR `order_status` IS NOT NULL;

-- 6. Cloud App Builds Table
CREATE TABLE IF NOT EXISTS `app_builds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `build_uid` VARCHAR(64) NOT NULL UNIQUE,
  `batch_id` VARCHAR(64) NULL,
  `license_id` INT NULL,
  `license_key` VARCHAR(64) NOT NULL,
  `order_id` INT NULL,
  `order_reference` VARCHAR(191) NULL,
  `client_email` VARCHAR(191) NOT NULL,
  `platform` ENUM('android', 'web', 'windows', 'ios') NOT NULL,
  `source_type` ENUM('latest_github', 'uploaded_zip') NOT NULL DEFAULT 'latest_github',
  `app_name` VARCHAR(191) NOT NULL DEFAULT 'Zoom Sales POS',
  `package_id` VARCHAR(191) NOT NULL DEFAULT 'com.zoomnearby.zoompos',
  `build_version` VARCHAR(64) NOT NULL DEFAULT '1.0.0',
  `server_url` VARCHAR(255) NOT NULL DEFAULT 'https://saas.zoomnearby.com',
  `primary_color` VARCHAR(32) NOT NULL DEFAULT '#4F46E5',
  `custom_logo_path` VARCHAR(255) NULL,
  `branding_json` JSON NULL,
  `status` ENUM('queued', 'preparing', 'building', 'completed', 'failed', 'cancelled', 'expired') NOT NULL DEFAULT 'queued',
  `github_run_id` BIGINT NULL,
  `github_workflow_id` VARCHAR(128) NULL,
  `artifact_path` VARCHAR(255) NULL,
  `artifact_filename` VARCHAR(191) NULL,
  `artifact_size_bytes` BIGINT NULL,
  `error_message` TEXT NULL,
  `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `build_duration_seconds` INT NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_license` (`license_key`),
  INDEX `idx_batch_id` (`batch_id`),
  INDEX `idx_email` (`client_email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Seed Settings
INSERT INTO `settings` (`k`, `v`) VALUES
  ('validation_mode', 'native'),
  ('gateway', 'razorpay'),
  ('currency', 'USD'),
  ('mail_driver', 'mail'),
  ('mail_from_address', 'licenses@zoomnearby.com'),
  ('mail_from_name', 'ZoomNearby Central Platform'),
  ('github_repo', 'prakash111/zoom-pos'),
  ('github_branch', 'feat/windows-offline-sync'),
  ('github_token', 'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN'),
  ('builder_default_monthly_limit', '3'),
  ('builder_plan_limits', '{"trial":1,"free":1,"starter":3,"core":3,"regular":3,"basic":3,"business":30,"pro":30,"professional":30,"all-in-one":30,"extended":30,"enterprise":30,"unlimited":-1}'),
  ('admin_username', 'admin'),
  ('admin_password_hash', '$2y$12$Hr/RyC8mKlojdQeKQHJoXOYQKThG.Y1JJmS0Dnj0L1PGVXvdqDc1i')
ON DUPLICATE KEY UPDATE `k` = `k`;

-- 8. Seed Official Products
INSERT INTO `products` (`slug`, `name`, `description`, `price`, `currency`, `app_builder_limit`, `is_active`) VALUES
  ('core',             'Core SaaS Platform (Retail, Restaurant & Café)', 'Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café / Quick-Service modules.', 49.00, 'USD', 3, 1),
  ('leadmanagement',   'Lead Management System',    'Lead tracking, CRM pipeline, follow-ups, and sales conversion module.', 29.00, 'USD', 0, 1),
  ('pharmacy',         'Pharmacy POS for SaaS',     'Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake and dispensing.', 25.00, 'USD', 0, 1),
  ('salon',            'Salon Management System',   'Standalone salon vertical: service catalogue, stylists / specialists, appointment booking and lifecycle.', 25.00, 'USD', 0, 1),
  ('repairtechnician', 'Repair Service Provider',   'Standalone repair vertical: device intake tickets, diagnostic checklist, parts & labor, lifecycle status and pickup.', 25.00, 'USD', 0, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `price` = VALUES(`price`), `app_builder_limit` = VALUES(`app_builder_limit`);

-- 9. Seed Official Bundles (Only 2 Public Plans: Core $49 & All-in-One $119)
INSERT INTO `bundles` (`slug`, `name`, `description`, `price`, `currency`, `included_modules`, `app_builder_limit`, `is_active`) VALUES
  ('core-lead', 'Main Script + Lead Manager Bundle', 'Main SaaS Core Script bundled with the Lead Management & CRM module.', 69.00, 'USD', '["core", "leadmanagement"]', 15, 0),
  ('all-in-one', 'All-in-One Enterprise Bundle', 'Includes Main Core SaaS script plus all business vertical modules: Lead Manager, Pharmacy POS, Salon Management, and Repair Technician.', 119.00, 'USD', '["core", "leadmanagement", "pharmacy", "salon", "repairtechnician"]', 30, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `price` = VALUES(`price`), `included_modules` = VALUES(`included_modules`), `app_builder_limit` = VALUES(`app_builder_limit`), `is_active` = VALUES(`is_active`);

SET FOREIGN_KEY_CHECKS = 1;
