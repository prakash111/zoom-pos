-- ==============================================================================
-- Zoom Sales CRM & Inventory POS — Complete Clean Database Schema
-- Version: 1.0.6 (CodeCanyon Release)
-- Includes: Core Multi-Tenant Platform with Retail & Restaurant POS Built-in
-- Generated at: 2026-10-04 14:59:28 UTC
-- ==============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- Table structure for `activation_codes`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `activation_codes`;
CREATE TABLE `activation_codes` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_prefix` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_uses` int unsigned DEFAULT NULL,
  `current_uses` int unsigned NOT NULL DEFAULT '0',
  `validity_days` int unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `failed_attempts` int unsigned NOT NULL DEFAULT '0',
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activation_codes_plan_name_foreign` (`plan_name`),
  KEY `activation_codes_code_prefix_index` (`code_prefix`),
  CONSTRAINT `activation_codes_plan_name_foreign` FOREIGN KEY (`plan_name`) REFERENCES `plans` (`name`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `admin_sessions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `admin_sessions`;
CREATE TABLE `admin_sessions` (
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform_admin_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  `ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`token`),
  KEY `admin_sessions_platform_admin_id_revoked_index` (`platform_admin_id`,`revoked`),
  CONSTRAINT `admin_sessions_platform_admin_id_foreign` FOREIGN KEY (`platform_admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `ai_queries`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `ai_queries`;
CREATE TABLE `ai_queries` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_queries_company_id_foreign` (`company_id`),
  KEY `ai_queries_user_id_foreign` (`user_id`),
  CONSTRAINT `ai_queries_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_queries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `app_builds`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `app_builds`;
CREATE TABLE `app_builds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `build_uid` varchar(64) NOT NULL,
  `batch_id` varchar(64) DEFAULT NULL,
  `license_key` varchar(64) NOT NULL,
  `license_id` int DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `order_reference` varchar(191) DEFAULT NULL,
  `client_email` varchar(191) NOT NULL,
  `platform` enum('android','web','windows','ios') NOT NULL,
  `source_type` enum('latest_github','uploaded_zip') NOT NULL DEFAULT 'latest_github',
  `app_name` varchar(191) NOT NULL DEFAULT 'Zoom Sales POS',
  `package_id` varchar(191) NOT NULL DEFAULT 'com.zoomnearby.pos',
  `build_version` varchar(64) NOT NULL DEFAULT '1.0.0',
  `server_url` varchar(255) NOT NULL DEFAULT 'https://saas.zoomnearby.com',
  `primary_color` varchar(32) NOT NULL DEFAULT '#4F46E5',
  `custom_logo_path` varchar(255) DEFAULT NULL,
  `branding_json` json DEFAULT NULL,
  `status` enum('queued','preparing','building','completed','failed','cancelled','expired') NOT NULL DEFAULT 'queued',
  `github_run_id` bigint DEFAULT NULL,
  `github_workflow_id` varchar(128) DEFAULT NULL,
  `artifact_path` varchar(255) DEFAULT NULL,
  `artifact_filename` varchar(191) DEFAULT NULL,
  `artifact_size_bytes` bigint DEFAULT NULL,
  `error_message` text,
  `email_sent` tinyint(1) NOT NULL DEFAULT '0',
  `build_duration_seconds` int DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `build_uid` (`build_uid`),
  KEY `idx_license` (`license_key`),
  KEY `idx_email` (`client_email`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------------------------------------------
-- Table structure for `audit_logs`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_company_id_created_at_index` (`company_id`,`created_at`),
  KEY `audit_logs_action_created_at_index` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `automated_reminder_dispatches`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `automated_reminder_dispatches`;
CREATE TABLE `automated_reminder_dispatches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_id` bigint unsigned NOT NULL,
  `document_type` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cycle_key` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispatch_key` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_for` timestamp NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `automated_reminder_dispatches_dispatch_key_unique` (`dispatch_key`),
  KEY `automated_reminder_dispatches_sale_id_foreign` (`sale_id`),
  KEY `auto_reminder_tenant_status_schedule_idx` (`company_id`,`status`,`scheduled_for`),
  KEY `auto_reminder_document_idx` (`company_id`,`sale_id`,`document_type`),
  CONSTRAINT `automated_reminder_dispatches_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `automated_reminder_dispatches_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `brands`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `brands_company_id_external_id_unique` (`company_id`,`external_id`),
  CONSTRAINT `brands_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `bundles`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `bundles`;
CREATE TABLE `bundles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `included_modules` json NOT NULL,
  `custom_features` json DEFAULT NULL,
  `app_builder_limit` int NOT NULL DEFAULT '20',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------------------------------------------
-- Table structure for `cache`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `cache_locks`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `cash_register_transactions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `cash_register_transactions`;
CREATE TABLE `cash_register_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cash_register_id` bigint unsigned NOT NULL,
  `voucher_number` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `balance_before` decimal(12,2) DEFAULT NULL,
  `balance_after` decimal(12,2) DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_register_transactions_company_external_id_unique` (`company_id`,`external_id`),
  KEY `cash_register_transactions_cash_register_id_foreign` (`cash_register_id`),
  KEY `cash_register_transactions_created_by_foreign` (`created_by`),
  KEY `cash_register_transactions_company_id_cash_register_id_index` (`company_id`,`cash_register_id`),
  CONSTRAINT `cash_register_transactions_cash_register_id_foreign` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_register_transactions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_register_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `cash_registers`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `cash_registers`;
CREATE TABLE `cash_registers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `terminal_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opened_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `opening_denominations` json DEFAULT NULL,
  `expected_closing_balance` decimal(12,2) DEFAULT NULL,
  `counted_closing_balance` decimal(12,2) DEFAULT NULL,
  `closing_denominations` json DEFAULT NULL,
  `cash_difference` decimal(12,2) DEFAULT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `opening_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_registers_company_external_id_unique` (`company_id`,`external_id`),
  KEY `cash_registers_opened_by_foreign` (`opened_by`),
  KEY `cash_registers_closed_by_foreign` (`closed_by`),
  KEY `cash_registers_company_id_status_index` (`company_id`,`status`),
  KEY `cash_registers_company_id_status_opened_at_index` (`company_id`,`status`,`opened_at`),
  KEY `cash_registers_store_id_foreign` (`store_id`),
  CONSTRAINT `cash_registers_closed_by_foreign` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_registers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_registers_opened_by_foreign` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_registers_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `categories`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'retail',
  `color` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_company_id_external_id_unique` (`company_id`,`external_id`),
  KEY `categories_is_demo_index` (`is_demo`),
  KEY `categories_type_index` (`type`),
  CONSTRAINT `categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `categories`
LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` (`id`, `company_id`, `external_id`, `name`, `code`, `sort_order`, `type`, `color`, `description`, `metadata`, `active`, `is_demo`, `created_at`, `updated_at`, `synced_at`) VALUES 
('1', 'emp_af66a533dd1dcd5e', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-10 13:32:57', '2026-09-10 13:32:57', NULL),
('2', 'emp_af66a533dd1dcd5e', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-10 13:32:57', '2026-09-10 13:32:57', NULL),
('3', 'emp_af66a533dd1dcd5e', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-10 13:32:57', '2026-09-10 13:32:57', NULL),
('4', 'emp_af66a533dd1dcd5e', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-10 13:32:57', '2026-09-10 13:32:57', NULL),
('50', 'emp_8d82bec4d87206b1', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-13 20:18:00', '2026-09-13 20:18:00', NULL),
('51', 'emp_8d82bec4d87206b1', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-13 20:18:00', '2026-09-13 20:18:00', NULL),
('52', 'emp_8d82bec4d87206b1', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-13 20:18:00', '2026-09-13 20:18:00', NULL),
('53', 'emp_8d82bec4d87206b1', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-13 20:18:00', '2026-09-13 20:18:00', NULL),
('62', 'emp_1ebd17c943660762', NULL, 'Smartphones & Mobiles', NULL, '0', 'device', '#0284c7', 'Mobile phones, iOS & Android devices', '{\"brands\": [\"Apple\", \"Samsung\", \"Google Pixel\", \"Xiaomi\", \"OnePlus\", \"Motorola\", \"Oppo\", \"Vivo\", \"Huawei\", \"Other\"], \"checklist_items\": [\"Power On / Booting\", \"Display & Touch Digitizer\", \"Front & Rear Cameras\", \"Charging Port & Battery Drain\", \"Ear Speaker & Loudspeaker\", \"Microphones & Call Quality\", \"Face ID / Fingerprint Sensor\", \"Wi-Fi & Cellular Signal\"], \"identifier_type\": \"IMEI / Serial Number\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:03:26', NULL),
('63', 'emp_1ebd17c943660762', NULL, 'Laptops & MacBooks', NULL, '0', 'device', '#0284c7', 'Laptops, MacBooks, gaming notebooks, and ultra-portables', '{\"brands\": [\"Apple MacBook\", \"Dell\", \"HP\", \"Lenovo ThinkPad\", \"Asus ROG\", \"Acer\", \"Microsoft Surface\", \"MSI\", \"Razer\", \"Other\"], \"checklist_items\": [\"Power On & POST\", \"Screen Display & Backlight\", \"Keyboard & Trackpad\", \"Battery Health & AC Adapter\", \"Storage & RAM Diagnostics\", \"USB & Type-C / HDMI Ports\", \"Internal Cooling Fan & Thermals\", \"Wi-Fi & Bluetooth Connectivity\"], \"identifier_type\": \"Serial Number\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:03:26', NULL),
('64', 'emp_1ebd17c943660762', NULL, 'Tablets & iPads', NULL, '0', 'device', '#0284c7', 'Tablets, iPads, and touch slate devices', '{\"brands\": [\"Apple iPad\", \"Samsung Galaxy Tab\", \"Microsoft Surface Pro\", \"Lenovo Tab\", \"Amazon Fire\", \"Other\"], \"checklist_items\": [\"Power On / Boot\", \"Touch Screen & Apple Pencil / Stylus\", \"Battery & Charging Current\", \"Front & Back Cameras\", \"Buttons (Power, Volume)\", \"Audio & Speakers\"], \"identifier_type\": \"Serial / IMEI\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:03:26', NULL),
('65', 'emp_1ebd17c943660762', NULL, 'Home Appliances', NULL, '0', 'device', '#0284c7', 'Kitchen, laundry, cooling, and small domestic appliances', '{\"brands\": [\"LG\", \"Samsung\", \"Whirlpool\", \"Bosch\", \"Panasonic\", \"Philips\", \"Haier\", \"Godrej\", \"Other\"], \"checklist_items\": [\"Power Input & Fuse\", \"Control Panel & Display\", \"Motor / Compressor Operation\", \"Heating / Cooling Test\", \"Water / Gas Leakage Inspection\", \"Cables, Hoses & Safety Ground\"], \"identifier_type\": \"Model / Serial Number\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:03:26', NULL),
('66', 'emp_1ebd17c943660762', NULL, 'Gaming Consoles', NULL, '0', 'device', '#ec4899', 'Video game consoles and handheld gaming devices', '{\"brands\": [\"Sony PlayStation 5\", \"Sony PlayStation 4\", \"Microsoft Xbox Series X/S\", \"Microsoft Xbox One\", \"Nintendo Switch\", \"Steam Deck\", \"Other\"], \"checklist_items\": [\"Power On & Boot to Dashboard\", \"HDMI Video & Audio Output\", \"Disc Drive / Cartridge Reader\", \"Controller Bluetooth Sync\", \"Cooling Fan & Overheating Status\", \"Wi-Fi & Ethernet Network\"], \"identifier_type\": \"Console Serial Number\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:04:00', NULL),
('67', 'emp_1ebd17c943660762', NULL, 'Audio & Headphones', NULL, '0', 'device', '#0284c7', 'Wireless earbuds, over-ear headphones, and portable speakers', '{\"brands\": [\"Sony\", \"Bose\", \"Apple AirPods\", \"JBL\", \"Sennheiser\", \"Marshall\", \"Beats\", \"Other\"], \"checklist_items\": [\"Power On & Bluetooth Pairing\", \"Left Channel Audio Output\", \"Right Channel Audio Output\", \"Active Noise Cancellation (ANC)\", \"Built-in Microphone Clarity\", \"Battery Capacity & Case Charging\"], \"identifier_type\": \"Serial Number\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:03:26', NULL),
('68', 'emp_1ebd17c943660762', NULL, 'Drones & Aerial Equipment', NULL, '0', 'device', '#0284c7', 'Drones, gimbals, and quadcopter accessories', '{\"brands\": [\"DJI\", \"Autel Robotics\", \"Parrot\", \"Skydio\", \"Holy Stone\", \"Other\"], \"checklist_items\": [\"Power On & Flight Controller Self-Test\", \"Propeller Motors & ESC Response\", \"Gimbal Stabilization & Camera Feed\", \"GPS Satellite Lock & Compass\", \"Obstacle Avoidance Sensors\", \"Remote Controller Link & Telemetry\"], \"identifier_type\": \"Aircraft Serial / Registration\"}', '1', '0', '2026-09-15 12:03:26', '2026-09-15 12:03:26', NULL),
('69', 'emp_1ebd17c943660762', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-15 12:18:14', '2026-09-15 12:18:14', NULL),
('70', 'emp_1ebd17c943660762', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-15 12:18:14', '2026-09-15 12:18:14', NULL),
('71', 'emp_1ebd17c943660762', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-15 12:18:14', '2026-09-15 12:18:14', NULL),
('72', 'emp_1ebd17c943660762', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-15 12:18:14', '2026-09-15 12:18:14', NULL),
('325', 'emp_7e513c6bfdec43e6', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('326', 'emp_7e513c6bfdec43e6', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('327', 'emp_7e513c6bfdec43e6', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('328', 'emp_7e513c6bfdec43e6', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('329', 'emp_7e513c6bfdec43e6', NULL, 'Starters', NULL, '0', 'restaurant', '#f59e0b', 'Appetizers, soups, and shared plates', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('330', 'emp_7e513c6bfdec43e6', NULL, 'Main Course', NULL, '0', 'restaurant', '#ef4444', 'Burgers, artisan pizzas, and chef specials', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('331', 'emp_7e513c6bfdec43e6', NULL, 'Hot Beverages', NULL, '0', 'restaurant', '#8b5cf6', 'Espressos, lattes, and specialty teas', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('332', 'emp_7e513c6bfdec43e6', NULL, 'Desserts', NULL, '0', 'restaurant', '#ec4899', 'Cakes, pastries, and artisanal ice creams', NULL, '1', '1', '2026-09-16 13:50:26', '2026-09-16 13:50:26', NULL),
('333', 'emp_7e513c6bfdec43e6', NULL, 'Antibiotics', NULL, '0', 'pharmacy', '#8b5cf6', 'Prescription antibacterial medications', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-27 16:29:18', NULL),
('334', 'emp_7e513c6bfdec43e6', NULL, 'Pain Relief', NULL, '0', 'pharmacy', '#f59e0b', 'Analgesics, antipyretics, and anti-inflammatories', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('335', 'emp_7e513c6bfdec43e6', NULL, 'First Aid', NULL, '0', 'pharmacy', '#10b981', 'Dressings, antiseptics, and emergency supplies', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('336', 'emp_7e513c6bfdec43e6', NULL, 'Vitamins & Supplements', NULL, '0', 'pharmacy', '#3b82f6', 'Daily multivitamins, minerals, and wellness items', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('337', 'emp_7e513c6bfdec43e6', NULL, 'Hair & Styling', NULL, '0', 'salon', '#8b5cf6', 'Cuts, blowouts, coloring, and styling', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('338', 'emp_7e513c6bfdec43e6', NULL, 'Facials & Skincare', NULL, '0', 'salon', '#ec4899', 'Rejuvenating facials, peels, and therapy', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('339', 'emp_7e513c6bfdec43e6', NULL, 'Spa & Body Treatments', NULL, '0', 'salon', '#06b6d4', 'Aromatherapy, deep tissue, and relaxation', NULL, '1', '1', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('344', 'emp_7e513c6bfdec43e6', NULL, 'Vegetables', NULL, '0', 'retail', '#22c55e', 'Fresh vegetables and greens', NULL, '1', '0', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('345', 'emp_7e513c6bfdec43e6', NULL, 'Fresh Fruit', NULL, '0', 'retail', '#f43f5e', 'Seasonal sweet fruits and berries', NULL, '1', '0', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('346', 'emp_7e513c6bfdec43e6', NULL, 'Carbohydrate', NULL, '0', 'retail', '#f59e0b', 'Fresh breads, grains, and baked goods', NULL, '1', '0', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('347', 'emp_7e513c6bfdec43e6', NULL, 'Snacks', NULL, '0', 'retail', '#a855f7', 'Healthy snacks and quick bites', NULL, '1', '0', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('348', 'emp_69df53ee9686f23b', NULL, 'Beverages', NULL, '0', 'retail', '#3b82f6', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-21 03:24:14', NULL),
('349', 'emp_69df53ee9686f23b', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('350', 'emp_69df53ee9686f23b', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('351', 'emp_69df53ee9686f23b', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('352', 'emp_69df53ee9686f23b', NULL, 'Vegetables', NULL, '0', 'retail', '#22c55e', 'Fresh vegetables and greens', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('353', 'emp_69df53ee9686f23b', NULL, 'Fresh Fruit', NULL, '0', 'retail', '#f43f5e', 'Seasonal sweet fruits and berries', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('354', 'emp_69df53ee9686f23b', NULL, 'Carbohydrate', NULL, '0', 'retail', '#f59e0b', 'Fresh breads, grains, and baked goods', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('355', 'emp_69df53ee9686f23b', NULL, 'Snacks', NULL, '0', 'retail', '#a855f7', 'Healthy snacks and quick bites', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('356', 'emp_8b2acdee34ae35a1', NULL, 'Starters', NULL, '0', 'restaurant', '#f59e0b', 'Appetizers, soups, and shared plates', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('357', 'emp_8b2acdee34ae35a1', NULL, 'Main Course', NULL, '0', 'restaurant', '#ef4444', 'Burgers, artisan pizzas, and chef specials', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('358', 'emp_8b2acdee34ae35a1', NULL, 'Hot Beverages', NULL, '0', 'restaurant', '#8b5cf6', 'Espressos, lattes, and specialty teas', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('359', 'emp_8b2acdee34ae35a1', NULL, 'Desserts', NULL, '0', 'restaurant', '#ec4899', 'Cakes, pastries, and artisanal ice creams', NULL, '1', '1', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL);
INSERT INTO `categories` (`id`, `company_id`, `external_id`, `name`, `code`, `sort_order`, `type`, `color`, `description`, `metadata`, `active`, `is_demo`, `created_at`, `updated_at`, `synced_at`) VALUES 
('360', 'emp_8b2acdee34ae35a1', NULL, 'Vegetables', NULL, '0', 'retail', '#22c55e', 'Fresh vegetables and greens', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('361', 'emp_8b2acdee34ae35a1', NULL, 'Fresh Fruit', NULL, '0', 'retail', '#f43f5e', 'Seasonal sweet fruits and berries', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('362', 'emp_8b2acdee34ae35a1', NULL, 'Carbohydrate', NULL, '0', 'retail', '#f59e0b', 'Fresh breads, grains, and baked goods', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('363', 'emp_8b2acdee34ae35a1', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled juices, milk, and drinks', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('364', 'emp_8b2acdee34ae35a1', NULL, 'Snacks', NULL, '0', 'retail', '#a855f7', 'Healthy snacks and quick bites', NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('365', 'emp_8b2acdee34ae35a1', NULL, 'Happy Hour Sale', NULL, '0', 'retail', NULL, NULL, NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('366', 'emp_8b2acdee34ae35a1', NULL, 'Burgers', NULL, '0', 'retail', NULL, NULL, NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('367', 'emp_8b2acdee34ae35a1', NULL, 'Tacos', NULL, '0', 'retail', NULL, NULL, NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('368', 'emp_8b2acdee34ae35a1', NULL, 'Lunch Special', NULL, '0', 'retail', NULL, NULL, NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('369', 'emp_8b2acdee34ae35a1', NULL, 'Salads & Soups', NULL, '0', 'retail', NULL, NULL, NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('370', 'emp_8b2acdee34ae35a1', NULL, 'Desserts & Sweets', NULL, '0', 'retail', NULL, NULL, NULL, '1', '0', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('371', 'emp_335c95c1360de705', NULL, 'Antibiotics', NULL, '0', 'pharmacy', '#ef4444', 'Prescription antibacterial medications', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('372', 'emp_335c95c1360de705', NULL, 'Pain Relief', NULL, '0', 'pharmacy', '#f59e0b', 'Analgesics, antipyretics, and anti-inflammatories', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('373', 'emp_335c95c1360de705', NULL, 'First Aid', NULL, '0', 'pharmacy', '#10b981', 'Dressings, antiseptics, and emergency supplies', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('374', 'emp_335c95c1360de705', NULL, 'Vitamins & Supplements', NULL, '0', 'pharmacy', '#3b82f6', 'Daily multivitamins, minerals, and wellness items', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('375', 'emp_335c95c1360de705', NULL, 'Vegetables', NULL, '0', 'retail', '#22c55e', 'Fresh vegetables and greens', NULL, '1', '0', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('376', 'emp_335c95c1360de705', NULL, 'Fresh Fruit', NULL, '0', 'retail', '#f43f5e', 'Seasonal sweet fruits and berries', NULL, '1', '0', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('377', 'emp_335c95c1360de705', NULL, 'Carbohydrate', NULL, '0', 'retail', '#f59e0b', 'Fresh breads, grains, and baked goods', NULL, '1', '0', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('378', 'emp_335c95c1360de705', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled juices, milk, and drinks', NULL, '1', '0', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('379', 'emp_335c95c1360de705', NULL, 'Snacks', NULL, '0', 'retail', '#a855f7', 'Healthy snacks and quick bites', NULL, '1', '0', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('380', 'emp_c96f27eaf74497c7', NULL, 'Hair & Styling', NULL, '0', 'salon', '#8b5cf6', 'Cuts, blowouts, coloring, and styling', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('381', 'emp_c96f27eaf74497c7', NULL, 'Facials & Skincare', NULL, '0', 'salon', '#ec4899', 'Rejuvenating facials, peels, and therapy', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('382', 'emp_c96f27eaf74497c7', NULL, 'Spa & Body Treatments', NULL, '0', 'salon', '#06b6d4', 'Aromatherapy, deep tissue, and relaxation', NULL, '1', '1', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('383', 'emp_c96f27eaf74497c7', NULL, 'Vegetables', NULL, '0', 'retail', '#22c55e', 'Fresh vegetables and greens', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('384', 'emp_c96f27eaf74497c7', NULL, 'Fresh Fruit', NULL, '0', 'retail', '#f43f5e', 'Seasonal sweet fruits and berries', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('385', 'emp_c96f27eaf74497c7', NULL, 'Carbohydrate', NULL, '0', 'retail', '#f59e0b', 'Fresh breads, grains, and baked goods', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('386', 'emp_c96f27eaf74497c7', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled juices, milk, and drinks', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('387', 'emp_c96f27eaf74497c7', NULL, 'Snacks', NULL, '0', 'retail', '#a855f7', 'Healthy snacks and quick bites', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('392', 'emp_8ac4a905318d6a15', NULL, 'Vegetables', NULL, '0', 'retail', '#22c55e', 'Fresh vegetables and greens', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('393', 'emp_8ac4a905318d6a15', NULL, 'Fresh Fruit', NULL, '0', 'retail', '#f43f5e', 'Seasonal sweet fruits and berries', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('394', 'emp_8ac4a905318d6a15', NULL, 'Carbohydrate', NULL, '0', 'retail', '#f59e0b', 'Fresh breads, grains, and baked goods', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('395', 'emp_8ac4a905318d6a15', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled juices, milk, and drinks', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('396', 'emp_8ac4a905318d6a15', NULL, 'Snacks', NULL, '0', 'retail', '#a855f7', 'Healthy snacks and quick bites', NULL, '1', '0', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('397', 'emp_b8eac7b503bb8399', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-18 11:24:24', '2026-09-18 11:24:24', NULL),
('398', 'emp_b8eac7b503bb8399', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-18 11:24:24', '2026-09-18 11:24:24', NULL),
('399', 'emp_b8eac7b503bb8399', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#ec4899', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-18 11:24:24', '2026-09-18 16:13:32', NULL),
('400', 'emp_b8eac7b503bb8399', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-18 11:24:24', '2026-09-18 11:24:24', NULL),
('401', 'emp_191eeaa69efdf908', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('402', 'emp_191eeaa69efdf908', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('403', 'emp_191eeaa69efdf908', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('404', 'emp_191eeaa69efdf908', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('405', 'emp_47d372d6657b77a8', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('406', 'emp_47d372d6657b77a8', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('407', 'emp_47d372d6657b77a8', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('408', 'emp_47d372d6657b77a8', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:46', '2026-09-19 05:16:46', NULL),
('409', 'emp_4bbec4a0c544b4b9', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('410', 'emp_4bbec4a0c544b4b9', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('411', 'emp_4bbec4a0c544b4b9', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('412', 'emp_4bbec4a0c544b4b9', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('413', 'emp_5bb0b9b78f8a109e', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL);
INSERT INTO `categories` (`id`, `company_id`, `external_id`, `name`, `code`, `sort_order`, `type`, `color`, `description`, `metadata`, `active`, `is_demo`, `created_at`, `updated_at`, `synced_at`) VALUES 
('414', 'emp_5bb0b9b78f8a109e', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('415', 'emp_5bb0b9b78f8a109e', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('416', 'emp_5bb0b9b78f8a109e', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('417', 'emp_71e01b428e7b9051', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('418', 'emp_71e01b428e7b9051', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('419', 'emp_71e01b428e7b9051', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('420', 'emp_71e01b428e7b9051', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:47', '2026-09-19 05:16:47', NULL),
('421', 'emp_d81b91035f1d8238', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('422', 'emp_d81b91035f1d8238', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('423', 'emp_d81b91035f1d8238', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('424', 'emp_d81b91035f1d8238', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('425', 'emp_f30dbe971a26c3a7', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('426', 'emp_f30dbe971a26c3a7', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('427', 'emp_f30dbe971a26c3a7', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('428', 'emp_f30dbe971a26c3a7', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 05:16:48', '2026-09-19 05:16:48', NULL),
('429', 'emp_b35da03e43e5bc76', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 12:09:10', '2026-09-19 12:09:10', NULL),
('430', 'emp_b35da03e43e5bc76', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 12:09:10', '2026-09-19 12:09:10', NULL),
('431', 'emp_b35da03e43e5bc76', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 12:09:10', '2026-09-19 12:09:10', NULL),
('432', 'emp_b35da03e43e5bc76', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 12:09:10', '2026-09-19 12:09:10', NULL),
('433', 'emp_a9140de48b0c6abe', NULL, 'Diversos', NULL, '0', 'retail', '#4f46e5', 'Diversos', NULL, '1', '0', '2026-09-19 12:34:56', '2026-09-19 12:34:56', NULL),
('434', 'emp_da8ca5d11dc1213f', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-19 21:40:07', '2026-09-19 21:40:07', NULL),
('435', 'emp_da8ca5d11dc1213f', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-19 21:40:07', '2026-09-19 21:40:07', NULL),
('436', 'emp_da8ca5d11dc1213f', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-19 21:40:07', '2026-09-19 21:40:07', NULL),
('437', 'emp_da8ca5d11dc1213f', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-19 21:40:07', '2026-09-19 21:40:07', NULL),
('438', 'emp_622e7c5306c6c0ea', NULL, 'Beverages', NULL, '0', 'retail', '#06b6d4', 'Chilled beverages, juices, and specialty drinks', NULL, '1', '1', '2026-09-21 04:46:26', '2026-09-21 04:46:26', NULL),
('439', 'emp_622e7c5306c6c0ea', NULL, 'Packaged Snacks', NULL, '0', 'retail', '#a855f7', 'Crisps, energy bars, and packaged sweets', NULL, '1', '1', '2026-09-21 04:46:26', '2026-09-21 04:46:26', NULL),
('440', 'emp_622e7c5306c6c0ea', NULL, 'Electronics & Accessories', NULL, '0', 'retail', '#3b82f6', 'Cables, chargers, and mobile gadgets', NULL, '1', '1', '2026-09-21 04:46:26', '2026-09-21 04:46:26', NULL),
('441', 'emp_622e7c5306c6c0ea', NULL, 'Household Goods', NULL, '0', 'retail', '#10b981', 'Everyday cleaning and personal care essentials', NULL, '1', '1', '2026-09-21 04:46:26', '2026-09-21 04:46:26', NULL),
('442', 'emp_7e513c6bfdec43e6', NULL, 'Smartphones & Mobiles', NULL, '0', 'device', NULL, NULL, '{\"brands\": [\"Apple\", \"Samsung\", \"Google Pixel\", \"Xiaomi\", \"OnePlus\", \"Motorola\", \"Oppo\", \"Vivo\", \"Huawei\", \"Other\"], \"common_issues\": [\"Broken / Shattered Screen\", \"Battery Not Charging / Drains Fast\", \"Water / Liquid Damage\", \"Camera Lens Cracked\", \"No Power / Boot Loop\", \"Speaker Distortion\"], \"checklist_items\": [\"Power On / Booting\", \"Display & Touch Digitizer\", \"Front & Rear Cameras\", \"Charging Port & Battery Drain\", \"Ear Speaker & Loudspeaker\", \"Microphones & Call Quality\", \"Face ID / Fingerprint Sensor\", \"Wi-Fi & Cellular Signal\"], \"identifier_type\": \"IMEI / Serial Number\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-01 12:29:09', NULL),
('443', 'emp_7e513c6bfdec43e6', NULL, 'Laptops & MacBooks', NULL, '0', 'device', NULL, NULL, '{\"brands\": [\"Apple MacBook\", \"Dell\", \"HP\", \"Lenovo ThinkPad\", \"Asus ROG\", \"Acer\", \"Microsoft Surface\", \"MSI\", \"Razer\", \"Other\"], \"common_issues\": [\"Cracked LCD / Glitched Screen\", \"Thermal Overheating / Fan Noise\", \"Liquid Spill on Keyboard\", \"Broken Hinge or Chassis\", \"SSD / OS Boot Failure\", \"Battery Swelling / Not Holding Charge\"], \"checklist_items\": [\"Power On & POST\", \"Screen Display & Backlight\", \"Keyboard & Trackpad\", \"Battery Health & AC Adapter\", \"Storage & RAM Diagnostics\", \"USB & Type-C / HDMI Ports\", \"Internal Cooling Fan & Thermals\", \"Wi-Fi & Bluetooth Connectivity\"], \"identifier_type\": \"Serial Number\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-01 12:29:09', NULL),
('444', 'emp_7e513c6bfdec43e6', NULL, 'Tablets & iPads', NULL, '0', 'device', NULL, NULL, '{\"brands\": [\"Apple iPad\", \"Samsung Galaxy Tab\", \"Microsoft Surface Pro\", \"Lenovo Tab\", \"Amazon Fire\", \"Other\"], \"common_issues\": [\"Cracked Front Glass Digitizer\", \"Bent Frame / Housing\", \"Loose Charging Port\", \"Battery Not Charging\"], \"checklist_items\": [\"Power On / Boot\", \"Touch Screen & Apple Pencil / Stylus\", \"Battery & Charging Current\", \"Front & Back Cameras\", \"Buttons (Power, Volume)\", \"Audio & Speakers\"], \"identifier_type\": \"Serial / IMEI\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-01 12:29:09', NULL),
('445', 'emp_7e513c6bfdec43e6', NULL, 'Home Appliances', NULL, '0', 'device', NULL, NULL, '{\"brands\": [\"LG\", \"Samsung\", \"Whirlpool\", \"Bosch\", \"Panasonic\", \"Philips\", \"Haier\", \"Godrej\", \"Other\"], \"common_issues\": [\"No Power / Fuse Trips\", \"Motor or Compressor Noise\", \"Water Leakage\", \"Not Heating / Cooling\", \"Control Board Error\"], \"checklist_items\": [\"Power Input & Fuse\", \"Control Panel & Display\", \"Motor / Compressor Operation\", \"Heating / Cooling Test\", \"Water / Gas Leakage Inspection\", \"Cables, Hoses & Safety Ground\"], \"identifier_type\": \"Model / Serial Number\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-01 12:29:09', NULL),
('446', 'emp_7e513c6bfdec43e6', NULL, 'Gaming Consoles', NULL, '0', 'device', NULL, NULL, '{\"brands\": [\"Sony PlayStation 5\", \"Sony PlayStation 4\", \"Microsoft Xbox Series X/S\", \"Microsoft Xbox One\", \"Nintendo Switch\", \"Steam Deck\", \"Other\"], \"common_issues\": [\"Damaged / Loose HDMI Port\", \"Overheating & Instant Shutdown\", \"Disc Read Error\", \"No Power / BLOD / WLOD\", \"Drifting Stick / Controller Port Fault\"], \"checklist_items\": [\"Power On & Boot to Dashboard\", \"HDMI Video & Audio Output\", \"Disc Drive / Cartridge Reader\", \"Controller Bluetooth Sync\", \"Cooling Fan & Overheating Status\", \"Wi-Fi & Ethernet Network\"], \"identifier_type\": \"Console Serial Number\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-01 12:29:09', NULL),
('447', 'emp_7e513c6bfdec43e6', NULL, 'Audio & Headphones', NULL, '0', 'device', '#4f46e5', NULL, '{\"brands\": [\"Sony\", \"Bose\", \"Apple AirPods\", \"JBL\", \"Sennheiser\", \"Marshall\", \"Beats\", \"Other\"], \"common_issues\": [\"One Side Audio Not Working\", \"Battery Drains in 15 Minutes\", \"Charging Case Port Broken\", \"Distorted Sound / Buzzing Noise\"], \"checklist_items\": [\"Power On & Bluetooth Pairing\", \"Left Channel Audio Output\", \"Right Channel Audio Output\", \"Active Noise Cancellation (ANC)\", \"Built-in Microphone Clarity\", \"Battery Capacity & Case Charging\"], \"identifier_type\": \"Serial Number\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-03 12:57:51', NULL),
('448', 'emp_7e513c6bfdec43e6', NULL, 'Drones & Aerial Equipment', NULL, '0', 'device', NULL, NULL, '{\"brands\": [\"DJI\", \"Autel Robotics\", \"Parrot\", \"Skydio\", \"Holy Stone\", \"Other\"], \"common_issues\": [\"Crashed Arm / Broken Propeller Motor\", \"Gimbal Overload or Ribbon Cable Tear\", \"ESC Calibration Error\", \"Camera Vision Sensor Error\"], \"checklist_items\": [\"Power On & Flight Controller Self-Test\", \"Propeller Motors & ESC Response\", \"Gimbal Stabilization & Camera Feed\", \"GPS Satellite Lock & Compass\", \"Obstacle Avoidance Sensors\", \"Remote Controller Link & Telemetry\"], \"identifier_type\": \"Aircraft Serial / Registration\"}', '1', '0', '2026-10-01 12:29:09', '2026-10-01 12:29:09', NULL);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `companies`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unique_account_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'US',
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `currency_symbol` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '$',
  `currency_decimals` tinyint unsigned NOT NULL DEFAULT '2',
  `currency_symbol_position` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prefix',
  `other_currencies` json DEFAULT NULL,
  `language` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `default_locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drawer_cover` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#2d7a58',
  `accent_color` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drawer_bg` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drawer_gradient_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `drawer_gradient_start` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drawer_gradient_end` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drawer_gradient_direction` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'top_to_bottom',
  `theme_color` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `pos_layout` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `receipt_format` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '80mm',
  `store_banner_tag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'SPECIAL STORE DEALS',
  `store_banner_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Grab Up To 50% Off On Selected Products',
  `store_banner_subtitle` text COLLATE utf8mb4_unicode_ci,
  `store_banner_cta_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Shop Now',
  `store_banner_cta_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#products-section',
  `store_banner_image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_banner_is_active` tinyint(1) NOT NULL DEFAULT '1',
  `enable_product_reviews` tinyint(1) NOT NULL DEFAULT '1',
  `require_review_approval` tinyint(1) NOT NULL DEFAULT '0',
  `enable_google_login` tinyint(1) NOT NULL DEFAULT '0',
  `google_client_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_client_secret` text COLLATE utf8mb4_unicode_ci,
  `storefront_payment_gateways` json DEFAULT NULL,
  `require_customer_verification` tinyint(1) NOT NULL DEFAULT '0',
  `verification_channels` json DEFAULT NULL,
  `enable_order_notifications` tinyint(1) NOT NULL DEFAULT '0',
  `order_notification_channels` json DEFAULT NULL,
  `order_notification_events` json DEFAULT NULL,
  `default_commission_rate` decimal(8,2) NOT NULL DEFAULT '0.00',
  `default_commission_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `pos_mode` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `licensed_modules` json DEFAULT NULL,
  `restaurant_mode_locked` tinyint(1) NOT NULL DEFAULT '0',
  `nav_config` json DEFAULT NULL,
  `navigation_menu_customization` json DEFAULT NULL,
  `navigation_labels` json DEFAULT NULL,
  `form_field_customizations` json DEFAULT NULL,
  `enable_consignments` tinyint(1) NOT NULL DEFAULT '1',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_seeding_complete` tinyint(1) NOT NULL DEFAULT '0',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `is_profile_completed` tinyint(1) NOT NULL DEFAULT '0',
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activation_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `max_users` int unsigned DEFAULT NULL,
  `max_devices` int unsigned DEFAULT NULL,
  `pricing_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_api_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_api_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tax_api_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotation_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_settings` json DEFAULT NULL,
  `invoice_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `quote_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bank_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dispensing_disclaimer` text COLLATE utf8mb4_unicode_ci,
  `repair_warranty_terms` text COLLATE utf8mb4_unicode_ci,
  `salon_policy_terms` text COLLATE utf8mb4_unicode_ci,
  `repair_checklist_schema` json DEFAULT NULL,
  `pix_key_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_merchant_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_merchant_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_qr_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_fee_debit` decimal(5,2) NOT NULL DEFAULT '1.50',
  `card_fee_credit_1x` decimal(5,2) NOT NULL DEFAULT '3.20',
  `card_fee_credit_installments` json DEFAULT NULL,
  `barcode_scale_prefix` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2',
  `barcode_scale_type` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'weight',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `repair_prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'REP-',
  `prescription_prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RX-',
  `salon_prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SAL-',
  `show_cash_register_alerts` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_unique_account_id_unique` (`unique_account_id`),
  UNIQUE KEY `companies_activation_key_unique` (`activation_key`),
  UNIQUE KEY `companies_slug_unique` (`slug`),
  UNIQUE KEY `companies_custom_domain_unique` (`custom_domain`),
  KEY `companies_plan_name_foreign` (`plan_name`),
  CONSTRAINT `companies_plan_name_foreign` FOREIGN KEY (`plan_name`) REFERENCES `plans` (`name`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `company_addons`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `company_addons`;
CREATE TABLE `company_addons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `addon_slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_addons_company_id_addon_slug_index` (`company_id`,`addon_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `company_translations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `company_translations`;
CREATE TABLE `company_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `comp_loc_key_unique` (`company_id`,`locale`,`key`),
  KEY `company_translations_company_id_index` (`company_id`),
  KEY `company_translations_locale_index` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `configurations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `configurations`;
CREATE TABLE `configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `configurations_company_id_key_unique` (`company_id`,`key`),
  CONSTRAINT `configurations_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `consignment_items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `consignment_items`;
CREATE TABLE `consignment_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `consignment_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispatched_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `returned_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `sold_quantity` decimal(10,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sold_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `consignment_items_consignment_id_foreign` (`consignment_id`),
  CONSTRAINT `consignment_items_consignment_id_foreign` FOREIGN KEY (`consignment_id`) REFERENCES `consignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `consignments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `consignments`;
CREATE TABLE `consignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `consignment_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `dispatched_at` datetime DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `total_dispatched_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_sold_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_returned_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sale_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `consignments_company_external_id_unique` (`company_id`,`external_id`),
  KEY `consignments_company_id_status_index` (`company_id`,`status`),
  KEY `consignments_consignment_number_index` (`consignment_number`),
  CONSTRAINT `consignments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `contact_inquiries`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `contact_inquiries`;
CREATE TABLE `contact_inquiries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom_fields` json DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `coupon_usages`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `coupon_usages`;
CREATE TABLE `coupon_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint unsigned NOT NULL,
  `company_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coupon_usages_coupon_id_index` (`coupon_id`),
  KEY `coupon_usages_company_id_index` (`company_id`),
  KEY `coupon_usages_customer_id_index` (`customer_id`),
  KEY `coupon_usages_customer_email_index` (`customer_email`),
  KEY `coupon_usages_customer_phone_index` (`customer_phone`),
  KEY `coupon_usages_sale_id_index` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `coupons`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percentage','fixed_amount') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(12,2) NOT NULL,
  `min_order_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `max_discount_amount` decimal(12,2) DEFAULT NULL,
  `usage_limit_total` int DEFAULT NULL,
  `usage_limit_per_customer` int NOT NULL DEFAULT '1',
  `used_count` int NOT NULL DEFAULT '0',
  `starts_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupons_company_id_code_unique` (`company_id`,`code`),
  KEY `coupons_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `custom_notification_channels`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `custom_notification_channels`;
CREATE TABLE `custom_notification_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POST',
  `payload_format` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json',
  `headers` json DEFAULT NULL,
  `auth_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `auth_value` text COLLATE utf8mb4_unicode_ci,
  `payload_template` text COLLATE utf8mb4_unicode_ci,
  `event_types` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `custom_notification_channels_company_id_is_active_index` (`company_id`,`is_active`),
  CONSTRAINT `custom_notification_channels_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `customer_addresses`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home',
  `street_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'India',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_addresses_customer_id_foreign` (`customer_id`),
  KEY `customer_addresses_company_id_customer_id_index` (`company_id`,`customer_id`),
  CONSTRAINT `customer_addresses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_addresses_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `customer_ledgers`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `customer_ledgers`;
CREATE TABLE `customer_ledgers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `order_payment_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_ledgers_customer_id_foreign` (`customer_id`),
  KEY `customer_ledgers_sale_id_foreign` (`sale_id`),
  KEY `customer_ledgers_order_payment_id_foreign` (`order_payment_id`),
  KEY `customer_ledgers_created_by_foreign` (`created_by`),
  KEY `customer_ledgers_company_id_customer_id_index` (`company_id`,`customer_id`),
  KEY `customer_ledgers_is_demo_index` (`is_demo`),
  CONSTRAINT `customer_ledgers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_ledgers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_ledgers_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_ledgers_order_payment_id_foreign` FOREIGN KEY (`order_payment_id`) REFERENCES `order_payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_ledgers_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `customer_wallet_transactions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `customer_wallet_transactions`;
CREATE TABLE `customer_wallet_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `order_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `running_balance` decimal(14,2) NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_wallet_transactions_tenant_id_index` (`tenant_id`),
  KEY `customer_wallet_transactions_customer_id_index` (`customer_id`),
  KEY `customer_wallet_transactions_order_id_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `customer_wishlists`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `customer_wishlists`;
CREATE TABLE `customer_wishlists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_wishlists_customer_id_product_id_unique` (`customer_id`,`product_id`),
  KEY `customer_wishlists_product_id_foreign` (`product_id`),
  KEY `customer_wishlists_company_id_customer_id_index` (`company_id`,`customer_id`),
  CONSTRAINT `customer_wishlists_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_wishlists_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_wishlists_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `customers`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tax_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `person_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_code_expires_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loyalty_tier_id` bigint unsigned DEFAULT NULL,
  `points_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `wallet_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_lifetime_spend` decimal(14,2) NOT NULL DEFAULT '0.00',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loyalty_points` int unsigned NOT NULL DEFAULT '0',
  `due_balance` decimal(12,2) NOT NULL DEFAULT '0.00',
  `state_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gstin` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taxpayer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `age` smallint unsigned DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allergies` text COLLATE utf8mb4_unicode_ci,
  `custom_fields` json DEFAULT NULL,
  `prescribing_doctor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doctor_registration_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customers_company_id_external_id_unique` (`company_id`,`external_id`),
  KEY `customers_is_demo_index` (`is_demo`),
  KEY `customers_auth_token_index` (`auth_token`),
  KEY `customers_loyalty_tier_id_foreign` (`loyalty_tier_id`),
  CONSTRAINT `customers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customers_loyalty_tier_id_foreign` FOREIGN KEY (`loyalty_tier_id`) REFERENCES `loyalty_tiers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `desktop_sync_receipts`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `desktop_sync_receipts`;
CREATE TABLE `desktop_sync_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `operation_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `desktop_sync_receipts_unique` (`company_id`,`operation_type`,`external_id`),
  CONSTRAINT `desktop_sync_receipts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `dining_floors`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `dining_floors`;
CREATE TABLE `dining_floors` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_index` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dining_floors_company_id_foreign` (`company_id`),
  KEY `dining_floors_is_demo_index` (`is_demo`),
  CONSTRAINT `dining_floors_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `dining_tables`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `dining_tables`;
CREATE TABLE `dining_tables` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dining_floor_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seating_capacity` int NOT NULL DEFAULT '4',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `current_sale_id` bigint unsigned DEFAULT NULL,
  `guest_count` int NOT NULL DEFAULT '0',
  `qr_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dining_tables_qr_token_unique` (`qr_token`),
  KEY `dining_tables_company_id_foreign` (`company_id`),
  KEY `dining_tables_dining_floor_id_foreign` (`dining_floor_id`),
  KEY `dining_tables_is_demo_index` (`is_demo`),
  CONSTRAINT `dining_tables_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dining_tables_dining_floor_id_foreign` FOREIGN KEY (`dining_floor_id`) REFERENCES `dining_floors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `dismissed_notifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `dismissed_notifications`;
CREATE TABLE `dismissed_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notification_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_notif_unique` (`company_id`,`notification_type`,`notification_id`),
  KEY `dismissed_notifications_company_id_index` (`company_id`),
  KEY `dismissed_notifications_notification_type_index` (`notification_type`),
  KEY `dismissed_notifications_notification_id_index` (`notification_id`),
  KEY `dismissed_notifications_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `dynamic_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `dynamic_settings`;
CREATE TABLE `dynamic_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dynamic_settings_tenant_id_index` (`tenant_id`),
  KEY `dynamic_settings_company_id_index` (`company_id`),
  KEY `dynamic_settings_group_index` (`group`),
  KEY `dynamic_settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `email_verifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `email_verifications`;
CREATE TABLE `email_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verifications_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `failed_jobs`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `faqs`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `faqs`;
CREATE TABLE `faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faqs_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `hrm_attendances`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `hrm_attendances`;
CREATE TABLE `hrm_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `clock_in_at` time DEFAULT NULL,
  `clock_out_at` time DEFAULT NULL,
  `total_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `clock_in_device` enum('pos_terminal','web','mobile','biometric','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pos_terminal',
  `status` enum('present','late','half_day','absent','on_leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hrm_attendances_employee_id_date_unique` (`employee_id`,`date`),
  KEY `hrm_attendances_tenant_id_index` (`tenant_id`),
  KEY `hrm_attendances_store_id_index` (`store_id`),
  KEY `hrm_attendances_date_index` (`date`),
  CONSTRAINT `hrm_attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `hrm_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `hrm_departments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `hrm_departments`;
CREATE TABLE `hrm_departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hrm_departments_tenant_id_index` (`tenant_id`),
  KEY `hrm_departments_store_id_index` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `hrm_designations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `hrm_designations`;
CREATE TABLE `hrm_designations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hrm_designations_department_id_foreign` (`department_id`),
  KEY `hrm_designations_tenant_id_index` (`tenant_id`),
  CONSTRAINT `hrm_designations_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `hrm_departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `hrm_employees`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `hrm_employees`;
CREATE TABLE `hrm_employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `user_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_code` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `designation_id` bigint unsigned DEFAULT NULL,
  `joining_date` date NOT NULL,
  `exit_date` date DEFAULT NULL,
  `salary_basis` enum('monthly','hourly','commission_only') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `basic_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `sales_commission_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','on_leave','suspended','terminated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hrm_employees_tenant_id_employee_code_unique` (`tenant_id`,`employee_code`),
  KEY `hrm_employees_department_id_foreign` (`department_id`),
  KEY `hrm_employees_designation_id_foreign` (`designation_id`),
  KEY `hrm_employees_tenant_id_index` (`tenant_id`),
  KEY `hrm_employees_store_id_index` (`store_id`),
  KEY `hrm_employees_user_id_index` (`user_id`),
  KEY `hrm_employees_employee_code_index` (`employee_code`),
  CONSTRAINT `hrm_employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `hrm_departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hrm_employees_designation_id_foreign` FOREIGN KEY (`designation_id`) REFERENCES `hrm_designations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `hrm_leaves`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `hrm_leaves`;
CREATE TABLE `hrm_leaves` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `leave_type` enum('casual','sick','paid','unpaid','maternity') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'casual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` smallint unsigned NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hrm_leaves_employee_id_foreign` (`employee_id`),
  KEY `hrm_leaves_tenant_id_index` (`tenant_id`),
  CONSTRAINT `hrm_leaves_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `hrm_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `hrm_payrolls`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `hrm_payrolls`;
CREATE TABLE `hrm_payrolls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `month` smallint unsigned NOT NULL,
  `year` smallint unsigned NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `commission_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `allowances` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','approved','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hrm_payrolls_employee_id_month_year_unique` (`employee_id`,`month`,`year`),
  KEY `hrm_payrolls_tenant_id_index` (`tenant_id`),
  KEY `hrm_payrolls_store_id_index` (`store_id`),
  CONSTRAINT `hrm_payrolls_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `hrm_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `job_batches`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `jobs`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `kitchen_tickets`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `kitchen_tickets`;
CREATE TABLE `kitchen_tickets` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `kot_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dining_table_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dine_in',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `server_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `items` json DEFAULT NULL,
  `kitchen_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_to_kitchen_at` timestamp NULL DEFAULT NULL,
  `prep_minutes` int unsigned DEFAULT NULL,
  `intimation_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `target_completion_at` timestamp NULL DEFAULT NULL,
  `alarm_at` timestamp NULL DEFAULT NULL,
  `alarm_sent_at` timestamp NULL DEFAULT NULL,
  `alarm_dismissed_at` timestamp NULL DEFAULT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `served_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kitchen_tickets_company_id_foreign` (`company_id`),
  KEY `kitchen_tickets_sale_id_foreign` (`sale_id`),
  KEY `kot_alarm_due_idx` (`company_id`,`alarm_at`,`alarm_sent_at`),
  KEY `kitchen_tickets_is_demo_index` (`is_demo`),
  CONSTRAINT `kitchen_tickets_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kitchen_tickets_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `languages`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `languages`;
CREATE TABLE `languages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `native_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flag` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direction` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ltr',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `languages`
LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` (`id`, `code`, `name`, `native_name`, `flag`, `direction`, `is_active`, `is_default`, `created_at`, `updated_at`) VALUES 
('1', 'en', 'English', 'English', '🇺🇸', 'ltr', '1', '1', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('2', 'es', 'Spanish', 'Español', '🇪🇸', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('3', 'fr', 'French', 'Français', '🇫🇷', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('4', 'de', 'German', 'Deutsch', '🇩🇪', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('5', 'ar', 'Arabic', 'العربية', '🇸🇦', 'rtl', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('6', 'hi', 'Hindi', 'हिन्दी', '🇮🇳', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('7', 'pt', 'Portuguese', 'Português', '🇧🇷', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('8', 'it', 'Italian', 'Italiano', '🇮🇹', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('9', 'zh', 'Chinese', '中文', '🇨🇳', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('10', 'ja', 'Japanese', '日本語', '🇯🇵', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('11', 'ru', 'Russian', 'Русский', '🇷🇺', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('12', 'id', 'Indonesian', 'Bahasa Indonesia', '🇮🇩', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('13', 'tr', 'Turkish', 'Türkçe', '🇹🇷', 'ltr', '1', '0', '2026-08-23 23:13:10', '2026-08-23 23:13:10'),
('14', 'nl', 'Dutch', 'Nederlands', '🇳🇱', 'ltr', '1', '0', '2026-09-25 06:40:02', '2026-09-25 06:40:02');
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `lead_mod_activities`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `lead_mod_activities`;
CREATE TABLE `lead_mod_activities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'call',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `due_date` datetime DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_mod_activities_company_id_status_index` (`company_id`,`status`),
  KEY `lead_mod_activities_lead_id_status_index` (`lead_id`,`status`),
  KEY `lead_mod_activities_company_id_index` (`company_id`),
  KEY `lead_mod_activities_lead_id_index` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `lead_mod_leads`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `lead_mod_leads`;
CREATE TABLE `lead_mod_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lead_code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `source_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `stage` enum('new','contacted','qualified','proposal_sent','won','lost') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `priority` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `estimated_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `expected_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `assigned_to` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `requirement_summary` text COLLATE utf8mb4_unicode_ci,
  `lost_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_mod_leads_lead_code_unique` (`lead_code`),
  KEY `lead_mod_leads_company_id_status_index` (`company_id`,`status`),
  KEY `lead_mod_leads_company_id_stage_index` (`company_id`,`stage`),
  KEY `lead_mod_leads_company_id_priority_index` (`company_id`,`priority`),
  KEY `lead_mod_leads_company_id_index` (`company_id`),
  KEY `lead_mod_leads_source_id_index` (`source_id`),
  KEY `lead_mod_leads_stage_index` (`stage`),
  KEY `lead_mod_leads_assigned_to_index` (`assigned_to`),
  KEY `lead_mod_leads_customer_id_index` (`customer_id`),
  CONSTRAINT `lead_mod_leads_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lead_mod_leads_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `lead_mod_sources`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `lead_mod_sources`;
CREATE TABLE `lead_mod_sources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_mod_sources_company_id_is_active_index` (`company_id`,`is_active`),
  KEY `lead_mod_sources_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- View structure for `leads`
-- -------------------------------------------------------------
DROP VIEW IF EXISTS `leads`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `leads` AS select `lead_mod_leads`.`id` AS `id`,`lead_mod_leads`.`company_id` AS `company_id`,`lead_mod_leads`.`lead_code` AS `lead_code`,`lead_mod_leads`.`name` AS `name`,`lead_mod_leads`.`title` AS `title`,`lead_mod_leads`.`company_name` AS `company_name`,`lead_mod_leads`.`email` AS `email`,`lead_mod_leads`.`phone` AS `phone`,`lead_mod_leads`.`source_id` AS `source_id`,`lead_mod_leads`.`source_name` AS `source_name`,`lead_mod_leads`.`source` AS `source`,`lead_mod_leads`.`status` AS `status`,`lead_mod_leads`.`stage` AS `stage`,`lead_mod_leads`.`priority` AS `priority`,`lead_mod_leads`.`estimated_value` AS `estimated_value`,`lead_mod_leads`.`expected_value` AS `expected_value`,`lead_mod_leads`.`assigned_to` AS `assigned_to`,`lead_mod_leads`.`notes` AS `notes`,`lead_mod_leads`.`requirement_summary` AS `requirement_summary`,`lead_mod_leads`.`lost_reason` AS `lost_reason`,`lead_mod_leads`.`converted_at` AS `converted_at`,`lead_mod_leads`.`customer_id` AS `customer_id`,`lead_mod_leads`.`created_at` AS `created_at`,`lead_mod_leads`.`updated_at` AS `updated_at`,`lead_mod_leads`.`deleted_at` AS `deleted_at` from `lead_mod_leads`;

-- -------------------------------------------------------------
-- Table structure for `licenses`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `licenses`;
CREATE TABLE `licenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `license_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_slug` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bundle_id` int DEFAULT NULL,
  `client_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registered_domain` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bound_domain` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bound_ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_domains` json DEFAULT NULL,
  `license_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular',
  `plan` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_builder_monthly_limit` int DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `branding_json` json DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `last_verified_ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `licenses_license_key_unique` (`license_key`),
  KEY `licenses_registered_domain_index` (`registered_domain`),
  KEY `licenses_bound_domain_index` (`bound_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `loyalty_points_transactions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `loyalty_points_transactions`;
CREATE TABLE `loyalty_points_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `order_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` decimal(12,2) NOT NULL,
  `monetary_equivalent` decimal(12,2) NOT NULL DEFAULT '0.00',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loyalty_points_transactions_tenant_id_index` (`tenant_id`),
  KEY `loyalty_points_transactions_customer_id_index` (`customer_id`),
  KEY `loyalty_points_transactions_order_id_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `loyalty_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `loyalty_settings`;
CREATE TABLE `loyalty_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_points_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `spend_amount_per_point` decimal(10,2) NOT NULL DEFAULT '100.00',
  `points_awarded` decimal(10,2) NOT NULL DEFAULT '1.00',
  `redemption_value_per_point` decimal(10,2) NOT NULL DEFAULT '1.00',
  `min_points_to_redeem` int unsigned NOT NULL DEFAULT '50',
  `max_redemption_percentage` int unsigned NOT NULL DEFAULT '50',
  `is_wallet_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loyalty_settings_tenant_id_store_id_unique` (`tenant_id`,`store_id`),
  KEY `loyalty_settings_tenant_id_index` (`tenant_id`),
  KEY `loyalty_settings_store_id_index` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `loyalty_tiers`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `loyalty_tiers`;
CREATE TABLE `loyalty_tiers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `badge_color` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#10B981',
  `min_spend_threshold` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `points_multiplier` decimal(3,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loyalty_tiers_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `menu_items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'header',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_id` bigint unsigned DEFAULT NULL,
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_index` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `menu_items_page_id_foreign` (`page_id`),
  CONSTRAINT `menu_items_page_id_foreign` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `message_queue`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `message_queue`;
CREATE TABLE `message_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `message_queue_sale_id_foreign` (`sale_id`),
  KEY `message_queue_company_id_status_index` (`company_id`,`status`),
  CONSTRAINT `message_queue_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_queue_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `migrations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `migrations`
LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES 
('1', '0001_01_01_000001_create_cache_table', '1'),
('2', '0001_01_01_000002_create_jobs_table', '1'),
('3', '2026_08_21_205731_create_plans_table', '1'),
('4', '2026_08_21_205732_create_platform_admins_table', '1'),
('5', '2026_08_21_205733_create_admin_sessions_table', '1'),
('6', '2026_08_21_205734_create_platform_system_table', '1'),
('7', '2026_08_21_205735_create_platform_branding_table', '1'),
('8', '2026_08_21_205736_create_platform_notifications_table', '1'),
('9', '2026_08_21_205737_create_payment_gateway_settings_table', '1'),
('10', '2026_08_21_205738_create_activation_codes_table', '1'),
('11', '2026_08_21_205739_create_companies_table', '1'),
('12', '2026_08_21_205740_create_users_table', '1'),
('13', '2026_08_21_205741_create_sessions_table', '1'),
('14', '2026_08_21_205742_create_subscriptions_table', '1'),
('15', '2026_08_21_205743_create_categories_table', '1'),
('16', '2026_08_21_205744_create_brands_table', '1'),
('17', '2026_08_21_205745_create_units_table', '1'),
('18', '2026_08_21_205746_create_products_table', '1'),
('19', '2026_08_21_205747_create_customers_table', '1'),
('20', '2026_08_21_205748_create_suppliers_table', '1'),
('21', '2026_08_21_205749_create_sales_table', '1'),
('22', '2026_08_21_205750_create_tax_rules_table', '1'),
('23', '2026_08_21_205751_create_payment_transactions_table', '1'),
('24', '2026_08_21_205752_create_permissions_table', '1'),
('25', '2026_08_21_205753_create_ai_queries_table', '1'),
('26', '2026_08_21_205754_create_audit_logs_table', '1'),
('27', '2026_08_21_205755_create_tenant_notifications_table', '1'),
('28', '2026_08_21_205756_create_pending_registrations_table', '1'),
('29', '2026_08_21_205757_create_configurations_table', '1'),
('30', '2026_08_21_205758_create_of_kv_store_table', '1'),
('31', '2026_08_21_222129_create_published_catalogs_table', '1'),
('32', '2026_08_22_041000_add_branding_and_color_to_companies_table', '1'),
('33', '2026_08_22_041001_create_payment_methods_table', '1'),
('34', '2026_08_22_042500_add_website_to_companies_table', '1'),
('35', '2026_08_22_044500_create_restaurant_subsystem_tables', '1'),
('36', '2026_08_22_050000_add_pos_mode_to_companies_table', '1'),
('37', '2026_08_22_060000_create_subscription_invoices_and_tenant_registration_tables', '1'),
('38', '2026_08_22_063145_add_theme_and_pos_layout_to_companies_table', '1'),
('39', '2026_08_22_073046_add_smtp_from_fields_to_platform_branding_table', '1'),
('40', '2026_08_22_075652_add_custom_domain_to_companies_table', '1'),
('41', '2026_08_22_085227_create_languages_and_company_translations_table', '1'),
('42', '2026_08_22_174116_create_order_payments_and_financial_ledger_tables', '1'),
('43', '2026_08_22_224146_add_dynamic_branding_and_landing_customization_to_platform_branding', '1'),
('44', '2026_08_23_000001_add_currency_formatting_to_companies_table', '1'),
('45', '2026_08_23_000002_create_cash_register_tables', '1'),
('46', '2026_08_23_000003_create_pages_table', '1'),
('47', '2026_08_23_000004_add_landing_page_settings_to_platform_branding_table', '1'),
('48', '2026_08_23_000005_create_contact_inquiries_table', '1'),
('49', '2026_08_23_000006_add_store_type_to_contact_inquiries_table', '1'),
('50', '2026_08_23_000007_add_salesperson_commission_terms_receipt_to_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES 
('51', '2026_08_23_200001_add_performance_and_query_indexes_to_tables', '1'),
('52', '2026_08_23_214500_create_tenant_translations_and_add_locale_to_users_and_companies_table', '1'),
('53', '2026_08_23_220500_add_terms_column_to_sales_table', '1'),
('54', '2026_08_24_000001_enhance_cash_registers_tables', '2'),
('55', '2026_08_24_000002_enhance_tax_rules_and_create_api_keys_table', '3'),
('56', '2026_08_24_000003_add_tax_name_and_tax_rate_to_sales_table', '4'),
('57', '2026_08_24_000004_add_is_active_to_plans_table', '5'),
('58', '2026_08_24_180000_create_menu_items_table', '6'),
('59', '2026_08_24_190000_create_pos_targets_consignments_and_merchant_fees_tables', '7'),
('60', '2026_08_25_150000_create_service_orders_and_update_tenant_features', '8'),
('61', '2026_08_27_120000_bind_tenant_api_keys_to_users', '9'),
('62', '2026_08_27_121000_create_desktop_sync_receipts_table', '9'),
('63', '2026_08_27_130000_add_external_id_to_desktop_sync_tables', '10'),
('64', '2026_08_27_130100_add_synced_at_to_legacy_desktop_sync_tables', '10'),
('65', '2026_08_27_140000_create_message_queue_table', '10'),
('66', '2026_09_01_093404_create_customer_ledgers_table', '11'),
('67', '2026_09_01_093405_add_due_balance_to_customers_table', '11'),
('68', '2026_09_01_093406_add_metadata_to_payment_methods_table', '11'),
('69', '2026_09_01_093407_create_custom_notification_channels_table', '11'),
('70', '2026_09_01_093408_add_restaurant_mode_lock_to_companies_table', '11'),
('71', '2026_09_01_100000_add_spice_levels_to_products_table', '12'),
('72', '2026_09_02_081708_add_icon_to_custom_notification_channels_table', '13'),
('73', '2026_09_02_090011_add_prep_time_fields_to_kitchen_tickets_table', '14'),
('74', '2026_09_02_091618_create_password_reset_tokens_table', '15'),
('75', '2026_09_02_000000_add_drawer_cover_to_companies_table', '16'),
('76', '2026_09_02_120000_add_nav_config_to_companies_table', '17'),
('77', '2026_09_03_000000_add_timezone_to_companies_table', '18'),
('78', '2026_09_03_140000_create_system_push_notification_architecture', '19'),
('79', '2026_09_03_233000_add_licensed_modules_to_companies_table', '20'),
('80', '2026_09_04_020000_create_sdui_modules_and_screens_tables', '21'),
('81', '2026_09_04_030000_add_branding_colors_to_companies_table', '22'),
('82', '2026_09_04_050000_add_payload_format_to_custom_notification_channels_table', '23'),
('83', '2026_09_04_060000_add_gradient_columns_to_companies_table', '24'),
('84', '2026_09_04_070000_add_demo_and_mode_seeding_columns', '25'),
('85', '2026_09_04_140000_add_nullable_cash_register_to_pos_sales', '26'),
('86', '2026_09_04_120000_create_system_translations_table', '27'),
('88', '2026_09_04_210000_create_pharmacy_and_repair_pos_tables', '28'),
('89', '2026_09_04_220000_create_repair_device_categories_table', '29'),
('90', '2026_09_05_000000_add_package_columns_to_sdui_modules_table', '30'),
('93', '2026_09_05_010000_add_is_specialist_to_users_table', '31'),
('94', '2026_09_05_020000_create_salon_appointments_table', '32'),
('95', '2026_09_05_030000_add_advance_paid_to_salon_appointments_table', '33'),
('96', '2026_09_05_160000_add_type_and_metadata_to_categories_table', '34'),
('97', '2026_09_05_170000_add_navigation_menu_customization_to_companies_table', '35'),
('98', '2026_09_05_180000_clean_rebuild_repair_module_tables', '36'),
('99', '2026_09_05_184000_add_sort_order_and_code_to_categories_table', '37'),
('100', '2026_09_06_120000_create_roles_table', '38'),
('101', '2026_09_06_130000_add_vertical_context_to_core_platform', '39'),
('102', '2026_09_06_150000_update_service_orders_customer_id_and_category_types', '40'),
('103', '2026_09_06_160000_add_dynamic_schema_tax_and_reminders', '41');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES 
('104', '2026_09_06_210000_add_form_field_customizations_and_service_catalog', '42'),
('105', '2026_09_06_234000_create_email_verifications_table_and_add_verification_code_to_users', '43'),
('106', '2026_09_07_120000_add_vertical_receipt_terms_to_companies_table', '44'),
('107', '2026_09_07_130000_add_repair_checklist_schema_to_companies_table', '45'),
('111', '2026_09_08_100000_add_license_columns_to_sdui_modules_table', '46'),
('113', '2026_09_08_000001_create_pharmacy_module_tables', '47'),
('114', '2026_09_09_000000_add_landing_downloads_and_section_meta_to_platform_branding', '48'),
('115', '2026_09_08_000001_create_repair_module_tables', '49'),
('116', '2026_09_08_000001_create_salon_module_tables', '50'),
('117', '2026_09_09_120000_create_sync_tombstones_table', '51'),
('118', '2026_09_09_130000_add_landing_features_and_testimonials_to_platform_branding', '52'),
('119', '2026_09_10_100000_add_is_profile_completed_to_companies_table', '53'),
('120', '2026_09_10_140000_add_auth_theme_to_platform_branding', '54'),
('121', '2026_09_10_150000_clear_hardcoded_auth_marketing_copy', '55'),
('122', '2026_09_10_160000_add_is_demo_to_companies_table', '56'),
('123', '2026_09_10_170000_add_dock_position_to_users_table', '57'),
('124', '2026_09_11_120000_create_tenant_notification_gateways_table', '58'),
('125', '2026_09_12_100000_create_tenant_navigation_and_features_tables', '58'),
('126', '2026_09_12_160000_create_dynamic_settings_table', '58'),
('127', '2026_09_12_201241_change_module_tables_company_id_to_string', '59'),
('128', '2026_09_12_213000_modify_leads_table_integrate_core_crm', '59'),
('132', '2026_09_14_120000_create_automated_reminder_dispatches_table', '62'),
('133', '2026_09_14_092629_add_deleted_at_to_lead_mod_leads_table', '63'),
('134', '2026_09_14_094100_add_lead_id_to_sales_table', '64'),
('147', '2026_09_12_000001_create_lead_module_tables', '65'),
('148', '2026_09_12_000002_modify_leads_table_integrate_core_crm', '65'),
('149', '2026_09_13_110000_enhance_reminders_table_fields', '65'),
('150', '2026_09_14_160000_create_dismissed_notifications_table', '66'),
('151', '2026_09_15_083000_create_tenant_settings_table', '67'),
('152', '2026_09_15_091500_alter_tenant_id_to_string_in_settings_table', '68'),
('153', '2026_09_15_220000_add_landing_content_to_platform_branding_table', '69'),
('154', '2026_09_16_080000_create_system_settings_table', '70'),
('155', '2026_09_16_120000_add_type_to_sdui_modules_table', '71'),
('156', '2026_09_19_000001_clean_stale_navigation_cache_for_pharmacy_demo', '72'),
('157', '2026_09_19_160000_add_custom_fields_to_contact_inquiries_table', '73'),
('158', '2026_09_19_180000_add_description_to_products_table', '74'),
('159', '2026_09_19_181000_add_meta_to_published_catalogs_table', '75'),
('160', '2026_09_19_205004_add_features_and_extensions_to_plans_table', '76'),
('161', '2026_09_20_060000_create_customer_storefront_tables', '77'),
('162', '2026_09_20_132539_add_products_limit_to_plans_table', '78'),
('163', '2026_09_20_160000_add_storefront_features_to_companies_table', '79'),
('164', '2026_09_20_160001_create_coupons_and_usages_tables', '79'),
('165', '2026_09_20_160002_add_tracking_code_to_sales_table', '79'),
('166', '2026_09_20_190000_create_faqs_and_customer_enhancements_tables', '80'),
('167', '2026_09_20_210000_create_product_reviews_and_settings_table', '81'),
('168', '2026_09_20_202207_fix_company_id_type_in_coupons_and_reviews_tables', '82'),
('169', '2026_09_20_220000_add_customer_verification_and_order_notifications', '83'),
('170', '2026_09_21_040000_create_tenant_inquiries_table', '84'),
('171', '2026_09_21_050000_cleanup_duplicate_storefront_navigation_items', '85'),
('172', '2026_09_21_060000_create_tenant_custom_pages_and_menus_tables', '86');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES 
('173', '2026_09_21_070000_add_contact_fields_to_platform_branding_table', '87'),
('174', '2026_09_21_080000_create_tenant_document_templates_table', '88'),
('175', '2026_09_21_000001_add_cash_register_alert_preference_to_companies', '89'),
('176', '2026_09_21_000002_create_stores_and_store_stock', '89'),
('177', '2026_09_22_193000_update_stores_table_isolation', '90'),
('178', '2026_09_22_200000_add_total_amount_column_to_sales_table', '91'),
('179', '2026_09_23_000001_create_licenses_table', '92'),
('180', '2026_09_23_130000_seed_core_builtin_modules_to_sdui_modules_table', '93'),
('182', '2026_10_03_000001_create_hrm_module_tables', '94'),
('183', '2026_10_03_000002_add_hrm_feature_to_plans_table', '95'),
('184', '2026_10_04_000001_extend_users_for_hrm_and_custom_fields', '96'),
('185', '2026_10_04_000001_create_loyalty_and_wallet_tables', '97');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `notification_reminders`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `notification_reminders`;
CREATE TABLE `notification_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channels` json DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `attempts` smallint unsigned NOT NULL DEFAULT '0',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_reminders_status_scheduled_at_index` (`status`,`scheduled_at`),
  KEY `notification_reminders_company_id_index` (`company_id`),
  KEY `notification_reminders_module_type_index` (`module_type`),
  KEY `notification_reminders_event_type_index` (`event_type`),
  KEY `notification_reminders_reference_id_index` (`reference_id`),
  KEY `notification_reminders_customer_id_index` (`customer_id`),
  KEY `notification_reminders_scheduled_at_index` (`scheduled_at`),
  KEY `notification_reminders_status_index` (`status`),
  CONSTRAINT `notification_reminders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_reminders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `of_kv_store`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `of_kv_store`;
CREATE TABLE `of_kv_store` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `of_kv_store_company_id_store_key_unique` (`company_id`,`store_key`),
  CONSTRAINT `of_kv_store_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `order_payments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `order_payments`;
CREATE TABLE `order_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_id` bigint unsigned NOT NULL,
  `cash_register_id` bigint unsigned DEFAULT NULL,
  `payment_method` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `installments` tinyint unsigned NOT NULL DEFAULT '1',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tendered` decimal(12,2) DEFAULT NULL,
  `change_returned` decimal(12,2) NOT NULL DEFAULT '0.00',
  `merchant_fee_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `merchant_fee_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reference_number` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pix_payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_payments_company_external_id_unique` (`company_id`,`external_id`),
  KEY `order_payments_sale_id_foreign` (`sale_id`),
  KEY `order_payments_company_id_sale_id_index` (`company_id`,`sale_id`),
  KEY `order_payments_company_id_payment_method_index` (`company_id`,`payment_method`),
  KEY `order_payments_company_id_created_at_index` (`company_id`,`created_at`),
  KEY `order_payments_cash_register_id_foreign` (`cash_register_id`),
  KEY `order_payments_company_id_cash_register_id_index` (`company_id`,`cash_register_id`),
  KEY `order_payments_store_id_foreign` (`store_id`),
  CONSTRAINT `order_payments_cash_register_id_foreign` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_payments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_payments_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_payments_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `orders`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_token` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` int DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `product_slug` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bundle_slug` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `checkout_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  UNIQUE KEY `order_token` (`order_token`),
  KEY `idx_token` (`order_token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `pages`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `meta_description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_footer` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pages_slug_unique` (`slug`),
  KEY `pages_created_by_foreign` (`created_by`),
  CONSTRAINT `pages_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `password_reset_tokens`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `payment_gateway_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `payment_gateway_settings`;
CREATE TABLE `payment_gateway_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gateway` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'test',
  `public_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secret_key` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `webhook_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `extra` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_gateway_settings_gateway_unique` (`gateway`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `payment_methods`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `order_index` int NOT NULL DEFAULT '0',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_methods_company_id_foreign` (`company_id`),
  CONSTRAINT `payment_methods_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `payment_methods`
LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
INSERT INTO `payment_methods` (`id`, `company_id`, `name`, `code`, `is_active`, `order_index`, `description`, `metadata`, `created_at`, `updated_at`) VALUES 
('pm_05b7daa740f7abb6', 'emp_5bb0b9b78f8a109e', 'Cash', 'cash', '1', '1', 'Physical cash payment', NULL, '2026-09-19 05:16:47', '2026-09-19 05:16:47'),
('pm_0a2c3d9dc736972a', 'emp_b864698f5153ccfc', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 05:14:54', '2026-09-16 05:14:54'),
('pm_1589def2b97ee481', 'emp_b8eac7b503bb8399', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-18 11:23:07', '2026-09-18 11:23:07'),
('pm_237eb3871ca4c8e0', 'emp_4bbec4a0c544b4b9', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 14:54:02', '2026-09-16 14:54:02'),
('pm_2b2e67a6c9763c28', 'emp_71e01b428e7b9051', 'Card', 'card', '1', '2', 'Credit or Debit Card', NULL, '2026-09-19 05:16:47', '2026-09-19 05:16:47'),
('pm_2d910762d6df5bf0', 'emp_8ac4a905318d6a15', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 13:50:30', '2026-09-16 13:50:30'),
('pm_2e215616983a25d9', 'emp_7e513c6bfdec43e6', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 13:50:26', '2026-09-16 13:50:26'),
('pm_30af248b133d434d', 'emp_8d82bec4d87206b1', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-13 20:17:30', '2026-09-13 20:17:30'),
('pm_31bf9021186dfe18', 'emp_8b2acdee34ae35a1', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 13:50:28', '2026-09-16 13:50:28'),
('pm_33d0cf885e70d7e2', 'emp_b35da03e43e5bc76', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-19 11:59:44', '2026-09-19 11:59:44'),
('pm_34d2b43adf71a1a7', 'emp_d81b91035f1d8238', 'Cash', 'cash', '1', '1', 'Physical cash payment', NULL, '2026-09-19 05:16:48', '2026-09-19 05:16:48'),
('pm_3f4064be6a6f18fc', 'emp_71e01b428e7b9051', 'Cash', 'cash', '1', '1', 'Physical cash payment', NULL, '2026-09-19 05:16:47', '2026-09-19 05:16:47'),
('pm_4a2fbda801236b09', 'emp_b8eac7b503bb8399', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-18 11:23:07', '2026-09-18 11:23:07'),
('pm_4befd94aabe43d7e', 'emp_622e7c5306c6c0ea', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-21 04:45:51', '2026-09-21 04:45:51'),
('pm_4d615a5964193e82', 'emp_b35da03e43e5bc76', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-19 11:59:44', '2026-09-19 11:59:44'),
('pm_4e8f2e18d3107114', 'emp_69df53ee9686f23b', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 13:50:27', '2026-09-16 13:50:27'),
('pm_4f4f7f3b8b515d44', 'emp_da8ca5d11dc1213f', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-19 21:39:38', '2026-09-19 21:39:38'),
('pm_56743c275c09d90d', 'emp_1ebd17c943660762', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-15 11:06:40', '2026-09-15 11:06:40'),
('pm_576395b0734c51ca', 'emp_8d82bec4d87206b1', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-13 20:17:30', '2026-09-13 20:17:30'),
('pm_597158a806a03637', 'emp_5bb0b9b78f8a109e', 'Transfer', 'transfer', '1', '3', 'Bank Transfer / Wire', NULL, '2026-09-19 05:16:47', '2026-09-19 05:16:47'),
('pm_5a49d3d46ffbfdab', 'emp_335c95c1360de705', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 13:50:28', '2026-09-16 13:50:28'),
('pm_65bcd60b49bb60a8', 'emp_d81b91035f1d8238', 'Card', 'card', '1', '2', 'Credit or Debit Card', NULL, '2026-09-19 05:16:48', '2026-09-19 05:16:48'),
('pm_6837aee822a417ea', 'emp_c96f27eaf74497c7', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 13:50:29', '2026-09-16 13:50:29'),
('pm_79164a107819d02a', 'emp_f30dbe971a26c3a7', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 15:58:43', '2026-09-16 15:58:43'),
('pm_7916b8961d00e80f', 'emp_af66a533dd1dcd5e', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-10 13:31:58', '2026-09-10 13:31:58'),
('pm_7e8cc07cc6af8040', 'emp_a9140de48b0c6abe', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-19 11:47:32', '2026-09-19 11:47:32'),
('pm_84751b849fc9bf0e', 'emp_af66a533dd1dcd5e', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-10 13:31:58', '2026-09-10 13:31:58'),
('pm_8553d515f5493599', 'emp_8b2acdee34ae35a1', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 13:50:28', '2026-09-16 13:50:28'),
('pm_856b16d330e54c0c', 'emp_335c95c1360de705', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 13:50:28', '2026-09-16 13:50:28'),
('pm_8c6ef96542311eb6', 'emp_b8eac7b503bb8399', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-18 11:23:07', '2026-09-18 11:23:07'),
('pm_8e6a12728114c49b', 'emp_71e01b428e7b9051', 'Transfer', 'transfer', '1', '3', 'Bank Transfer / Wire', NULL, '2026-09-19 05:16:47', '2026-09-19 05:16:47'),
('pm_8fcaca14059f7caa', 'emp_b864698f5153ccfc', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 05:14:54', '2026-09-16 05:14:54'),
('pm_8fea7b92a99b8eda', 'emp_335c95c1360de705', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 13:50:28', '2026-09-16 13:50:28'),
('pm_93a7180cc8c97b57', 'emp_f30dbe971a26c3a7', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 15:58:43', '2026-09-16 15:58:43'),
('pm_94fed38d2719e578', 'emp_69df53ee9686f23b', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 13:50:27', '2026-09-16 13:50:27'),
('pm_a01a5ed739ca1639', 'emp_7e513c6bfdec43e6', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 13:50:26', '2026-09-16 13:50:26'),
('pm_a2c68ea1eadabaca', 'emp_8ac4a905318d6a15', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 13:50:30', '2026-09-16 13:50:30'),
('pm_a42c552b8238d9c5', 'emp_a9140de48b0c6abe', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-19 11:47:32', '2026-09-19 11:47:32'),
('pm_a9f57fe8bf282fdb', 'emp_da8ca5d11dc1213f', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-19 21:39:38', '2026-09-19 21:39:38'),
('pm_af4b23bebff5229c', 'emp_a9140de48b0c6abe', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-19 11:47:32', '2026-09-19 11:47:32'),
('pm_b222039d3df757c3', 'emp_47d372d6657b77a8', 'Transfer', 'transfer', '1', '3', 'Bank Transfer / Wire', NULL, '2026-09-14 08:50:54', '2026-09-14 08:50:54'),
('pm_bbc926172108b051', 'emp_c96f27eaf74497c7', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 13:50:29', '2026-09-16 13:50:29'),
('pm_bbf8a3c0430ea054', 'emp_47d372d6657b77a8', 'Card', 'card', '1', '2', 'Credit or Debit Card', NULL, '2026-09-14 08:50:54', '2026-09-14 08:50:54'),
('pm_becea983bd2afdc5', 'emp_d81b91035f1d8238', 'Transfer', 'transfer', '1', '3', 'Bank Transfer / Wire', NULL, '2026-09-19 05:16:48', '2026-09-19 05:16:48'),
('pm_cab7db471db949bd', 'emp_7e513c6bfdec43e6', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 13:50:26', '2026-09-16 13:50:26'),
('pm_ce4cbdb049fba3b0', 'emp_191eeaa69efdf908', 'Transfer', 'transfer', '1', '3', 'Bank Transfer / Wire', NULL, '2026-09-19 05:16:46', '2026-09-19 05:16:46'),
('pm_d0938c0f6a666cb7', 'emp_8d82bec4d87206b1', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-13 20:17:30', '2026-09-13 20:17:30'),
('pm_db8850f32abd4315', 'emp_191eeaa69efdf908', 'Card', 'card', '1', '2', 'Credit or Debit Card', NULL, '2026-09-19 05:16:46', '2026-09-19 05:16:46'),
('pm_deaa5f7534e489b7', 'emp_da8ca5d11dc1213f', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-19 21:39:38', '2026-09-19 21:39:38'),
('pm_e30a4432034d46d0', 'emp_1ebd17c943660762', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-15 11:06:40', '2026-09-15 11:06:40');
INSERT INTO `payment_methods` (`id`, `company_id`, `name`, `code`, `is_active`, `order_index`, `description`, `metadata`, `created_at`, `updated_at`) VALUES 
('pm_e3b33460630256e0', 'emp_47d372d6657b77a8', 'Cash', 'cash', '1', '1', 'Physical cash payment', NULL, '2026-09-14 08:50:54', '2026-09-14 08:50:54'),
('pm_e3b45862820c7326', 'emp_622e7c5306c6c0ea', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-21 04:45:51', '2026-09-21 04:45:51'),
('pm_e459099cb159deaf', 'emp_8ac4a905318d6a15', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 13:50:30', '2026-09-16 13:50:30'),
('pm_e85570a788d3b51a', 'emp_622e7c5306c6c0ea', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-21 04:45:51', '2026-09-21 04:45:51'),
('pm_e8919aeff924ff4a', 'emp_5bb0b9b78f8a109e', 'Card', 'card', '1', '2', 'Credit or Debit Card', NULL, '2026-09-19 05:16:47', '2026-09-19 05:16:47'),
('pm_e8a1f73589256973', 'emp_a9140de48b0c6abe', 'PIX', 'pix', '1', '4', NULL, NULL, '2026-09-19 12:47:17', '2026-09-19 12:47:17'),
('pm_e90755a205f486bf', 'emp_1ebd17c943660762', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-15 11:06:40', '2026-09-15 11:06:40'),
('pm_eb5eb045d9f8e1fd', 'emp_191eeaa69efdf908', 'Cash', 'cash', '1', '1', 'Physical cash payment', NULL, '2026-09-19 05:16:46', '2026-09-19 05:16:46'),
('pm_ec7363374d626573', 'emp_af66a533dd1dcd5e', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-10 13:31:58', '2026-09-10 13:31:58'),
('pm_ee937f1519de6261', 'emp_f30dbe971a26c3a7', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 15:58:43', '2026-09-16 15:58:43'),
('pm_eed7b6b7097cf828', 'emp_8b2acdee34ae35a1', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 13:50:28', '2026-09-16 13:50:28'),
('pm_ef4822cfeaa99a98', 'emp_4bbec4a0c544b4b9', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-16 14:54:02', '2026-09-16 14:54:02'),
('pm_f0e49a9d4a58a98c', 'emp_c96f27eaf74497c7', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 13:50:29', '2026-09-16 13:50:29'),
('pm_f67e3d185159eff7', 'emp_4bbec4a0c544b4b9', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 14:54:02', '2026-09-16 14:54:02'),
('pm_f684172951e93606', 'emp_b35da03e43e5bc76', 'Transfer', 'transfer', '1', '3', NULL, NULL, '2026-09-19 11:59:44', '2026-09-19 11:59:44'),
('pm_f9c9120db6089dc1', 'emp_69df53ee9686f23b', 'Card', 'card', '1', '2', NULL, NULL, '2026-09-16 13:50:27', '2026-09-16 13:50:27'),
('pm_fdc63e1a492b2b6d', 'emp_b864698f5153ccfc', 'Cash', 'cash', '1', '1', NULL, NULL, '2026-09-16 05:14:54', '2026-09-16 05:14:54');
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `payment_transactions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_secondary_ref` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `checkout_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_transactions_company_id_foreign` (`company_id`),
  KEY `payment_transactions_gateway_ref_index` (`gateway_ref`),
  CONSTRAINT `payment_transactions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `payments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reference` varchar(191) NOT NULL,
  `gateway` varchar(40) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `product_slug` varchar(64) NOT NULL,
  `bundle_slug` varchar(64) DEFAULT NULL,
  `license_id` int DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `order_status` varchar(20) DEFAULT NULL,
  `target_domain` varchar(191) DEFAULT NULL,
  `items_json` json DEFAULT NULL,
  `checkout_json` json DEFAULT NULL,
  `order_error` varchar(255) DEFAULT NULL,
  `builder_email_sent` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `idx_license` (`license_id`),
  KEY `idx_reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -------------------------------------------------------------
-- Table structure for `pending_registrations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pending_registrations`;
CREATE TABLE `pending_registrations` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `otp_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pending_registrations_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `permissions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_user_id_module_action_unique` (`user_id`,`module`,`action`),
  KEY `permissions_company_id_foreign` (`company_id`),
  CONSTRAINT `permissions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `pharmacy_batches`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pharmacy_batches`;
CREATE TABLE `pharmacy_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `batch_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `stock_qty` int NOT NULL DEFAULT '0',
  `alert_days_before_expiry` smallint unsigned NOT NULL DEFAULT '90',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rack_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacy_batches_company_id_product_id_expiry_date_index` (`company_id`,`product_id`,`expiry_date`),
  KEY `pharmacy_batches_company_id_is_active_expiry_date_index` (`company_id`,`is_active`,`expiry_date`),
  KEY `pharmacy_batches_company_id_index` (`company_id`),
  KEY `pharmacy_batches_tenant_id_index` (`tenant_id`),
  KEY `pharmacy_batches_product_id_index` (`product_id`),
  KEY `pharmacy_batches_batch_number_index` (`batch_number`),
  KEY `pharmacy_batches_expiry_date_index` (`expiry_date`),
  KEY `pharmacy_batches_is_active_index` (`is_active`),
  KEY `pharmacy_batches_is_demo_index` (`is_demo`),
  KEY `pharmacy_batches_rack_location_index` (`rack_location`),
  CONSTRAINT `pharmacy_batches_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pharmacy_batches_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `pharmacy_mod_drug_batches`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pharmacy_mod_drug_batches`;
CREATE TABLE `pharmacy_mod_drug_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `mrp` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `supplier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacy_mod_drug_batches_company_id_expiry_date_index` (`company_id`,`expiry_date`),
  KEY `pharmacy_mod_drug_batches_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `pharmacy_mod_prescription_items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pharmacy_mod_prescription_items`;
CREATE TABLE `pharmacy_mod_prescription_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint unsigned NOT NULL,
  `drug_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT '1.00',
  `instructions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacy_mod_prescription_items_prescription_id_index` (`prescription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `pharmacy_mod_prescriptions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pharmacy_mod_prescriptions`;
CREATE TABLE `pharmacy_mod_prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rx_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doctor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pharmacy_mod_prescriptions_rx_number_unique` (`rx_number`),
  KEY `pharmacy_mod_prescriptions_company_id_status_index` (`company_id`,`status`),
  KEY `pharmacy_mod_prescriptions_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `pharmacy_prescriptions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `pharmacy_prescriptions`;
CREATE TABLE `pharmacy_prescriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prescription_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `patient_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doctor_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doctor_registration_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prescription_date` date NOT NULL,
  `diagnosis` text COLLATE utf8mb4_unicode_ci,
  `medicines` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `dispensed_at` datetime DEFAULT NULL,
  `dispensed_by_user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rx_image_url` text COLLATE utf8mb4_unicode_ci,
  `dosage_duration_days` smallint unsigned DEFAULT NULL,
  `refill_reminder_at` datetime DEFAULT NULL,
  `refill_reminder_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacy_prescriptions_company_id_status_index` (`company_id`,`status`),
  KEY `pharmacy_prescriptions_company_id_index` (`company_id`),
  KEY `pharmacy_prescriptions_tenant_id_index` (`tenant_id`),
  KEY `pharmacy_prescriptions_prescription_number_index` (`prescription_number`),
  KEY `pharmacy_prescriptions_customer_id_index` (`customer_id`),
  KEY `pharmacy_prescriptions_patient_name_index` (`patient_name`),
  KEY `pharmacy_prescriptions_doctor_name_index` (`doctor_name`),
  KEY `pharmacy_prescriptions_prescription_date_index` (`prescription_date`),
  KEY `pharmacy_prescriptions_status_index` (`status`),
  KEY `pharmacy_prescriptions_dispensed_at_index` (`dispensed_at`),
  KEY `pharmacy_prescriptions_dispensed_by_user_id_index` (`dispensed_by_user_id`),
  KEY `pharmacy_prescriptions_sale_id_index` (`sale_id`),
  KEY `pharmacy_prescriptions_is_demo_index` (`is_demo`),
  KEY `pharmacy_prescriptions_refill_reminder_at_index` (`refill_reminder_at`),
  CONSTRAINT `pharmacy_prescriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `plan_addons`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `plan_addons`;
CREATE TABLE `plan_addons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_yearly` decimal(10,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_addons_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `plan_addons`
LOCK TABLES `plan_addons` WRITE;
/*!40000 ALTER TABLE `plan_addons` DISABLE KEYS */;
INSERT INTO `plan_addons` (`id`, `name`, `slug`, `description`, `price_monthly`, `price_yearly`, `is_active`, `created_at`, `updated_at`) VALUES 
('1', 'HRM & Payroll Module', 'hrm_payroll', 'Complete Staff Directory, PIN Clock In/Out attendance, leave requests, and commission-based payroll calculation.', '499.00', '4999.00', '1', '2026-10-03 18:51:03', '2026-10-03 18:51:03');
/*!40000 ALTER TABLE `plan_addons` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `plans`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `duration_days` int unsigned DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `features` json DEFAULT NULL,
  `limits` json DEFAULT NULL,
  `invoice_limit` int NOT NULL DEFAULT '-1',
  `products_limit` int NOT NULL DEFAULT '-1',
  `device_limit` int NOT NULL DEFAULT '-1',
  `staff_limit` int NOT NULL DEFAULT '-1',
  `extensions` json DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `has_hrm_module` tinyint(1) NOT NULL DEFAULT '0',
  `max_staff_limit` int NOT NULL DEFAULT '2',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `store_limit` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `plans`
LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
INSERT INTO `plans` (`name`, `display_name`, `billing_cycle`, `duration_days`, `price`, `currency`, `features`, `limits`, `invoice_limit`, `products_limit`, `device_limit`, `staff_limit`, `extensions`, `active`, `is_active`, `has_hrm_module`, `max_staff_limit`, `created_at`, `updated_at`, `store_limit`) VALUES 
('enterprise', 'Enterprise', 'yearly', '365', '499.00', 'USD', '{\"api_access\": 1, \"hrm_module\": 1, \"quotations\": 1, \"consignments\": 1, \"customer_crm\": 1, \"online_store\": 1, \"cash_register\": 1, \"multi_location\": 1, \"restaurant_mode\": 1, \"automatic_backup\": 1, \"thermal_printing\": 1, \"analytics_reports\": 1, \"app_builder_access\": 1, \"custom_integrations\": 1, \"dedicated_account_manager\": 1, \"white_label_custom_branding\": 1, \"android_web_and_windows_builds\": 1, \"cloud_build_history_and_alerts\": 1}', '{\"filiais\": 20, \"invoices\": -1, \"products\": -1, \"usuarios\": 100, \"dispositivos\": 50, \"armazenamento_mb\": 51200}', '-1', '-1', '50', '100', NULL, '1', '1', '1', '100', '2026-10-03 18:51:03', '2026-10-03 18:51:03', '20'),
('professional', 'Professional', 'yearly', '365', '199.00', 'USD', '{\"api_access\": 1, \"hrm_module\": 1, \"quotations\": 1, \"consignments\": 1, \"customer_crm\": 1, \"online_store\": 1, \"cash_register\": 1, \"multi_location\": 1, \"restaurant_mode\": 1, \"automatic_backup\": 1, \"thermal_printing\": 1, \"analytics_reports\": 1, \"app_builder_access\": 1, \"white_label_custom_branding\": 1, \"android_web_and_windows_builds\": 1, \"cloud_build_history_and_alerts\": 1}', '{\"filiais\": 5, \"invoices\": -1, \"products\": -1, \"usuarios\": 25, \"dispositivos\": 10, \"armazenamento_mb\": 10240}', '-1', '-1', '10', '25', '[\"leadmanagement\"]', '1', '1', '1', '25', '2026-08-23 23:02:22', '2026-10-03 18:51:03', '5'),
('starter', 'Starter', 'monthly', '30', '19.00', 'USD', '{\"api_access\": 1, \"hrm_module\": 0, \"quotations\": 1, \"consignments\": 1, \"customer_crm\": 1, \"online_store\": 1, \"cash_register\": 1, \"multi_location\": 0, \"automatic_backup\": 0, \"thermal_printing\": 1, \"analytics_reports\": 1}', '{\"filiais\": 1, \"invoices\": 500, \"products\": 1000, \"usuarios\": 5, \"dispositivos\": 3, \"armazenamento_mb\": 2048}', '500', '1000', '3', '5', '[\"leadmanagement\"]', '1', '1', '0', '5', '2026-08-23 23:02:22', '2026-10-03 18:51:03', '1'),
('trial', 'Trial', 'trial', '14', '0.00', 'USD', '{\"api_access\": true, \"quotations\": true, \"consignments\": true, \"customer_crm\": true, \"online_store\": true, \"cash_register\": true, \"multi_location\": false, \"restaurant_mode\": true, \"service_booking\": true, \"automatic_backup\": false, \"pharmacy_batches\": true, \"repair_workbench\": true, \"thermal_printing\": true, \"analytics_reports\": true}', '{\"filiais\": 1, \"invoices\": 50, \"products\": 100, \"usuarios\": 2, \"dispositivos\": 1, \"armazenamento_mb\": 500}', '50', '100', '1', '2', '[\"leadmanagement\", \"hrm\", \"loyalty\"]', '1', '1', '0', '2', '2026-08-23 23:02:22', '2026-10-04 06:33:31', '1');
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `platform_admins`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `platform_admins`;
CREATE TABLE `platform_admins` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'super_admin',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_admins_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `platform_branding`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `platform_branding`;
CREATE TABLE `platform_branding` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Smart Inventory & Sales',
  `logo_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `favicon_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_page_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `landing_page_id` bigint unsigned DEFAULT NULL,
  `primary_color` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secondary_color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '#0F172A',
  `accent_color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '#FF7A00',
  `splash_bg_color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '#0F172A',
  `auth_bg_color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '#F8FAFC',
  `auth_headline` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'Run your business smarter.',
  `auth_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Sales, inventory & orders — all in one place.',
  `superadmin_sidebar_color` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#4338ca',
  `landing_primary_color` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#10b981',
  `landing_accent_color` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#d7f24e',
  `landing_hero_badge` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_hero_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_hero_subtitle` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `landing_hero_cta_primary_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_hero_cta_primary_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_hero_cta_secondary_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_hero_cta_secondary_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_hero_banner_image_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_playstore_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_playstore_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `landing_windows_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_windows_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `landing_sections_config` json DEFAULT NULL,
  `landing_section_meta` json DEFAULT NULL,
  `landing_faqs` json DEFAULT NULL,
  `landing_features` json DEFAULT NULL,
  `landing_testimonials` json DEFAULT NULL,
  `landing_content` json DEFAULT NULL,
  `support_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `support_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `head_office_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `working_hours` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` int unsigned DEFAULT NULL,
  `smtp_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `smtp_encryption` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_from_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_from_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiration_reminder_thresholds` json DEFAULT NULL,
  `otp_registration_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `platform_branding_landing_page_id_foreign` (`landing_page_id`),
  CONSTRAINT `platform_branding_landing_page_id_foreign` FOREIGN KEY (`landing_page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `platform_notifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `platform_notifications`;
CREATE TABLE `platform_notifications` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todos',
  `target_plan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `platform_system`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `platform_system`;
CREATE TABLE `platform_system` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `product_reviews`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint unsigned NOT NULL DEFAULT '5',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '1',
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_reviews_company_id_product_id_is_approved_index` (`company_id`,`product_id`,`is_approved`),
  KEY `product_reviews_company_id_customer_id_index` (`company_id`,`customer_id`),
  KEY `product_reviews_company_id_index` (`company_id`),
  KEY `product_reviews_product_id_index` (`product_id`),
  KEY `product_reviews_customer_id_index` (`customer_id`),
  KEY `product_reviews_is_approved_index` (`is_approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `product_store_stock`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `product_store_stock`;
CREATE TABLE `product_store_stock` (
  `product_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`,`store_id`),
  KEY `product_store_stock_store_id_foreign` (`store_id`),
  CONSTRAINT `product_store_stock_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_store_stock_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `products`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `generic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `composition` text COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_id` bigint unsigned DEFAULT NULL,
  `brand_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price` decimal(12,2) DEFAULT NULL,
  `app_builder_limit` int NOT NULL DEFAULT '10',
  `app_builder_monthly_limit` int NOT NULL DEFAULT '10',
  `variants` json DEFAULT NULL,
  `modifiers` json DEFAULT NULL,
  `spice_levels` json DEFAULT NULL,
  `profit_margin` decimal(5,2) DEFAULT NULL,
  `current_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `minimum_stock` decimal(12,3) NOT NULL DEFAULT '0.000',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `batch_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mfg_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `requires_prescription` tinyint(1) NOT NULL DEFAULT '0',
  `narcotic_schedule` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_minutes` smallint unsigned DEFAULT NULL,
  `hsn_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sac_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `tax_rule_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT '0',
  `taxable` tinyint(1) NOT NULL DEFAULT '1',
  `tax_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `zero_rate` tinyint(1) NOT NULL DEFAULT '0',
  `reverse_charge` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `follow_up_days` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_company_id_external_id_unique` (`company_id`,`external_id`),
  KEY `products_category_id_foreign` (`category_id`),
  KEY `products_brand_id_foreign` (`brand_id`),
  KEY `products_company_id_code_index` (`company_id`,`code`),
  KEY `products_company_id_barcode_index` (`company_id`,`barcode`),
  KEY `products_company_id_active_index` (`company_id`,`active`),
  KEY `products_is_demo_index` (`is_demo`),
  KEY `products_batch_number_index` (`batch_number`),
  KEY `products_expiry_date_index` (`expiry_date`),
  KEY `products_generic_name_index` (`generic_name`),
  KEY `products_narcotic_schedule_index` (`narcotic_schedule`),
  KEY `products_type_index` (`type`),
  KEY `products_category_type_index` (`category_type`),
  CONSTRAINT `products_brand_id_foreign` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `published_catalogs`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `published_catalogs`;
CREATE TABLE `published_catalogs` (
  `id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `meta` json DEFAULT NULL,
  `product_ids` json NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `published_catalogs_company_id_foreign` (`company_id`),
  CONSTRAINT `published_catalogs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `push_devices`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `push_devices`;
CREATE TABLE `push_devices` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_devices_token_unique` (`token`),
  KEY `push_devices_user_id_foreign` (`user_id`),
  KEY `push_devices_company_id_revoked_at_index` (`company_id`,`revoked_at`),
  CONSTRAINT `push_devices_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `push_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `push_notification_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `push_notification_settings`;
CREATE TABLE `push_notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `fcm_project_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_service_account_json` longtext COLLATE utf8mb4_unicode_ci,
  `fcm_server_key` text COLLATE utf8mb4_unicode_ci,
  `android_api_key` text COLLATE utf8mb4_unicode_ci,
  `android_app_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `messaging_sender_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_channel_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'delayed_orders_alarm',
  `order_channel_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Delayed order alarms',
  `order_sound` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alarm',
  `invoice_channel_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'due_invoice_reminders',
  `invoice_channel_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Due invoice reminders',
  `invoice_sound` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alarm',
  `alarm_repeat_seconds` smallint unsigned NOT NULL DEFAULT '60',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `reminders`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'follow_up',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `call_script` text COLLATE utf8mb4_unicode_ci,
  `due_date` timestamp NOT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `remindable_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remindable_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reminders_remindable_type_remindable_id_index` (`remindable_type`,`remindable_id`),
  KEY `reminders_company_id_index` (`company_id`),
  KEY `reminders_customer_id_index` (`customer_id`),
  KEY `reminders_type_index` (`type`),
  KEY `reminders_due_date_index` (`due_date`),
  KEY `reminders_status_index` (`status`),
  KEY `reminders_tenant_id_index` (`tenant_id`),
  KEY `reminders_user_id_index` (`user_id`),
  KEY `reminders_due_at_index` (`due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `repair_mod_device_categories`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `repair_mod_device_categories`;
CREATE TABLE `repair_mod_device_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_diagnostic_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `checklist_points` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_mod_device_categories_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `repair_mod_ticket_items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `repair_mod_ticket_items`;
CREATE TABLE `repair_mod_ticket_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `item_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'part',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_mod_ticket_items_ticket_id_index` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `repair_mod_tickets`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `repair_mod_tickets`;
CREATE TABLE `repair_mod_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ticket_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imei_serial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reported_issue` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `estimated_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `advance_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `inspection_checklist` json DEFAULT NULL,
  `technician_notes` text COLLATE utf8mb4_unicode_ci,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repair_mod_tickets_ticket_number_unique` (`ticket_number`),
  KEY `repair_mod_tickets_company_id_status_index` (`company_id`,`status`),
  KEY `repair_mod_tickets_company_id_index` (`company_id`),
  KEY `repair_mod_tickets_category_id_index` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `repair_ticket_items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `repair_ticket_items`;
CREATE TABLE `repair_ticket_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `item_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'spare_part',
  `quantity` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `billed_to_customer` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `repair_ticket_items_company_id_ticket_id_item_type_index` (`company_id`,`ticket_id`,`item_type`),
  KEY `repair_ticket_items_company_id_index` (`company_id`),
  KEY `repair_ticket_items_tenant_id_index` (`tenant_id`),
  KEY `repair_ticket_items_ticket_id_index` (`ticket_id`),
  KEY `repair_ticket_items_product_id_index` (`product_id`),
  KEY `repair_ticket_items_item_type_index` (`item_type`),
  KEY `repair_ticket_items_tax_id_index` (`tax_id`),
  CONSTRAINT `repair_ticket_items_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_ticket_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_ticket_items_tax_id_foreign` FOREIGN KEY (`tax_id`) REFERENCES `tax_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_ticket_items_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `repair_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `repair_tickets`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `repair_tickets`;
CREATE TABLE `repair_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number_or_imei` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passcode_pattern` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `problem_reported` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technician_diagnosis` text COLLATE utf8mb4_unicode_ci,
  `physical_condition_notes` text COLLATE utf8mb4_unicode_ci,
  `assigned_technician_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `priority` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `estimated_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `advance_deposit` decimal(12,2) NOT NULL DEFAULT '0.00',
  `advance_payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `advance_sale_id` bigint unsigned DEFAULT NULL,
  `final_sale_id` bigint unsigned DEFAULT NULL,
  `inspection_checklist` json DEFAULT NULL,
  `expected_delivery_at` datetime DEFAULT NULL,
  `intake_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `diagnostic_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `repair_tickets_company_id_ticket_number_unique` (`company_id`,`ticket_number`),
  KEY `repair_tickets_company_id_status_index` (`company_id`,`status`),
  KEY `repair_tickets_company_id_assigned_technician_id_status_index` (`company_id`,`assigned_technician_id`,`status`),
  KEY `repair_tickets_company_id_index` (`company_id`),
  KEY `repair_tickets_tenant_id_index` (`tenant_id`),
  KEY `repair_tickets_customer_id_index` (`customer_id`),
  KEY `repair_tickets_category_id_index` (`category_id`),
  KEY `repair_tickets_serial_number_or_imei_index` (`serial_number_or_imei`),
  KEY `repair_tickets_assigned_technician_id_index` (`assigned_technician_id`),
  KEY `repair_tickets_status_index` (`status`),
  KEY `repair_tickets_priority_index` (`priority`),
  KEY `repair_tickets_advance_sale_id_index` (`advance_sale_id`),
  KEY `repair_tickets_final_sale_id_index` (`final_sale_id`),
  KEY `repair_tickets_expected_delivery_at_index` (`expected_delivery_at`),
  KEY `repair_tickets_intake_at_index` (`intake_at`),
  KEY `repair_tickets_completed_at_index` (`completed_at`),
  KEY `repair_tickets_delivered_at_index` (`delivered_at`),
  KEY `repair_tickets_is_demo_index` (`is_demo`),
  CONSTRAINT `repair_tickets_advance_sale_id_foreign` FOREIGN KEY (`advance_sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_tickets_assigned_technician_id_foreign` FOREIGN KEY (`assigned_technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_tickets_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_tickets_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `repair_tickets_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `repair_tickets_final_sale_id_foreign` FOREIGN KEY (`final_sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `roles`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `permissions` json DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_company_id_slug_unique` (`company_id`,`slug`),
  KEY `roles_company_id_index` (`company_id`),
  KEY `roles_is_system_index` (`is_system`),
  KEY `roles_is_demo_index` (`is_demo`),
  CONSTRAINT `roles_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `roles`
LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `company_id`, `name`, `slug`, `is_system`, `description`, `permissions`, `is_demo`, `created_at`, `updated_at`) VALUES 
('1', 'emp_da8ca5d11dc1213f', 'Branch Administrator', 'branch_administrator', '0', NULL, '{\"stores\": [\"view\", \"manage\"]}', '0', '2026-09-22 10:03:39', '2026-09-22 10:03:39'),
('2', NULL, 'tenant_admin', 'tenant_admin', '1', 'Tenant Administrator', '{\"hrm\": [\"module.access\", \"employees.view\", \"employees.create\", \"employees.edit\", \"employees.delete\", \"attendance.view\", \"attendance.clock_in_out\", \"attendance.edit_manual\", \"leaves.view\", \"leaves.apply\", \"leaves.approve_reject\", \"payroll.view\", \"payroll.generate\", \"payroll.pay\", \"payroll.view_own\", \"settings.manage\"], \"loyalty\": [\"module.access\", \"customer.balance_view\", \"points.redeem\", \"wallet.topup\", \"wallet.charge\", \"tiers.manage\", \"settings.edit\"]}', '0', '2026-10-03 17:02:21', '2026-10-03 22:51:16'),
('3', NULL, 'store_manager', 'store_manager', '1', 'Store Manager', '{\"hrm\": [\"module.access\", \"employees.view\", \"attendance.view\", \"attendance.clock_in_out\", \"attendance.edit_manual\", \"leaves.view\", \"leaves.approve_reject\", \"payroll.view\"], \"loyalty\": [\"module.access\", \"customer.balance_view\", \"points.redeem\", \"wallet.topup\", \"wallet.charge\"]}', '0', '2026-10-03 17:02:21', '2026-10-03 22:51:16'),
('4', NULL, 'cashier', 'cashier', '1', 'POS Cashier / Staff', '{\"hrm\": [\"attendance.clock_in_out\", \"leaves.apply\", \"payroll.view_own\"], \"loyalty\": [\"customer.balance_view\", \"points.redeem\", \"wallet.charge\"]}', '0', '2026-10-03 17:02:21', '2026-10-03 22:51:16');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `sale_items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `batch_id` bigint unsigned DEFAULT NULL,
  `staff_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL DEFAULT '1.000',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `taxable_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_items_sale_id_foreign` (`sale_id`),
  KEY `sale_items_company_id_sale_id_line_type_index` (`company_id`,`sale_id`,`line_type`),
  KEY `sale_items_company_id_index` (`company_id`),
  KEY `sale_items_product_id_index` (`product_id`),
  KEY `sale_items_batch_id_index` (`batch_id`),
  KEY `sale_items_staff_id_index` (`staff_id`),
  KEY `sale_items_line_type_index` (`line_type`),
  CONSTRAINT `sale_items_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_items_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sales`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cash_register_id` bigint unsigned DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `due_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `due_reminder_at` timestamp NULL DEFAULT NULL,
  `due_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `due_reminder_dismissed_at` timestamp NULL DEFAULT NULL,
  `discount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `merchant_fee_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `merchant_fee_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_rate` decimal(8,3) NOT NULL DEFAULT '0.000',
  `tax_breakdown` json DEFAULT NULL,
  `einvoice_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `einvoice_irn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `einvoice_qr` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `einvoice_signed_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installments` tinyint unsigned NOT NULL DEFAULT '1',
  `agreed_payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `payment_status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `payment_terms` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_rate` decimal(8,2) NOT NULL DEFAULT '0.00',
  `commission_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `commission_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `items` json DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `operation_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dine_in',
  `dining_table_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guest_count` int NOT NULL DEFAULT '1',
  `pickup_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `driver_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispatch_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kot_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_invoice` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `module_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pos',
  `reference_ticket_id` bigint unsigned DEFAULT NULL,
  `doctor_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stylist_ids` json DEFAULT NULL,
  `store_id` bigint unsigned DEFAULT NULL,
  `total_amount` decimal(15,2) GENERATED ALWAYS AS (`total`) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_company_id_external_id_unique` (`company_id`,`external_id`),
  KEY `sales_customer_id_foreign` (`customer_id`),
  KEY `sales_user_id_foreign` (`user_id`),
  KEY `sales_company_id_status_index` (`company_id`,`status`),
  KEY `sales_company_id_created_at_index` (`company_id`,`created_at`),
  KEY `sales_company_id_operation_type_status_index` (`company_id`,`operation_type`,`status`),
  KEY `sales_company_id_user_id_status_index` (`company_id`,`user_id`,`status`),
  KEY `sales_company_id_customer_id_status_index` (`company_id`,`customer_id`,`status`),
  KEY `sales_company_id_due_date_index` (`company_id`,`due_date`),
  KEY `sales_company_id_sale_number_index` (`company_id`,`sale_number`),
  KEY `sale_due_reminder_idx` (`company_id`,`due_reminder_at`,`due_reminder_sent_at`),
  KEY `sales_is_demo_index` (`is_demo`),
  KEY `sales_cash_register_id_foreign` (`cash_register_id`),
  KEY `sales_company_id_cash_register_id_index` (`company_id`,`cash_register_id`),
  KEY `sales_module_type_index` (`module_type`),
  KEY `sales_reference_ticket_id_index` (`reference_ticket_id`),
  KEY `sales_lead_id_index` (`lead_id`),
  KEY `sales_tracking_code_index` (`tracking_code`),
  KEY `sales_store_id_foreign` (`store_id`),
  CONSTRAINT `sales_cash_register_id_foreign` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sales_targets`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sales_targets`;
CREATE TABLE `sales_targets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` smallint unsigned NOT NULL,
  `month` tinyint unsigned NOT NULL,
  `target_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_targets_company_external_id_unique` (`company_id`,`external_id`),
  KEY `sales_targets_company_id_year_month_index` (`company_id`,`year`,`month`),
  CONSTRAINT `sales_targets_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `salon_appointments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `salon_appointments`;
CREATE TABLE `salon_appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `customer_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `specialist_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `custom_fields` json DEFAULT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `advance_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `deposit_payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `chair_label` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_items` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salon_appointments_company_id_appointment_number_unique` (`company_id`,`appointment_number`),
  KEY `salon_appointments_company_id_specialist_id_starts_at_index` (`company_id`,`specialist_id`,`starts_at`),
  KEY `salon_appointments_company_id_index` (`company_id`),
  KEY `salon_appointments_tenant_id_index` (`tenant_id`),
  KEY `salon_appointments_customer_id_index` (`customer_id`),
  KEY `salon_appointments_product_id_index` (`product_id`),
  KEY `salon_appointments_specialist_id_index` (`specialist_id`),
  KEY `salon_appointments_starts_at_index` (`starts_at`),
  KEY `salon_appointments_ends_at_index` (`ends_at`),
  KEY `salon_appointments_status_index` (`status`),
  KEY `salon_appointments_sale_id_index` (`sale_id`),
  KEY `salon_appointments_is_demo_index` (`is_demo`),
  KEY `salon_appointments_chair_label_index` (`chair_label`),
  CONSTRAINT `salon_appointments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salon_appointments_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salon_appointments_sale_id_foreign` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `salon_mod_appointments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `salon_mod_appointments`;
CREATE TABLE `salon_mod_appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stylist_id` bigint unsigned DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'booked',
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salon_mod_appointments_reference_unique` (`reference`),
  KEY `salon_mod_appointments_company_id_status_index` (`company_id`,`status`),
  KEY `salon_mod_appointments_company_id_scheduled_at_index` (`company_id`,`scheduled_at`),
  KEY `salon_mod_appointments_company_id_index` (`company_id`),
  KEY `salon_mod_appointments_stylist_id_index` (`stylist_id`),
  KEY `salon_mod_appointments_service_id_index` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `salon_mod_services`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `salon_mod_services`;
CREATE TABLE `salon_mod_services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_minutes` int NOT NULL DEFAULT '30',
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salon_mod_services_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `salon_mod_stylists`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `salon_mod_stylists`;
CREATE TABLE `salon_mod_stylists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialties` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salon_mod_stylists_company_id_index` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sdui_modules`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sdui_modules`;
CREATE TABLE `sdui_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_system_version` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `package_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `requires_license` tinyint(1) NOT NULL DEFAULT '0',
  `license_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unlicensed',
  `license_key_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_key_prefix` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_key_encrypted` text COLLATE utf8mb4_unicode_ci,
  `license_driver` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_buyer` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_verified_at` timestamp NULL DEFAULT NULL,
  `license_expires_at` timestamp NULL DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'widgets',
  `layout_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard_grid',
  `features` json DEFAULT NULL,
  `routes` json DEFAULT NULL,
  `navigation` json DEFAULT NULL,
  `translation_keys` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `registration_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sdui_modules_slug_unique` (`slug`),
  KEY `sdui_modules_is_active_index` (`is_active`),
  KEY `sdui_modules_registration_allowed_index` (`registration_allowed`),
  KEY `sdui_modules_source_type_requires_license_index` (`source_type`,`requires_license`),
  KEY `sdui_modules_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sdui_screens`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sdui_screens`;
CREATE TABLE `sdui_screens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sdui_module_id` bigint unsigned DEFAULT NULL,
  `key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sdui_screens_key_unique` (`key`),
  KEY `sdui_screens_sdui_module_id_foreign` (`sdui_module_id`),
  KEY `sdui_screens_is_active_index` (`is_active`),
  CONSTRAINT `sdui_screens_sdui_module_id_foreign` FOREIGN KEY (`sdui_module_id`) REFERENCES `sdui_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `service_orders`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `service_orders`;
CREATE TABLE `service_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reported_defect` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `technical_diagnosis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parts_used` json DEFAULT NULL,
  `parts_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `labor_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `is_tax_inclusive` tinyint(1) NOT NULL DEFAULT '0',
  `tax_breakdown` json DEFAULT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `priority` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `warranty_period` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '90 days',
  `warranty_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `received_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `technician_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `extra_attributes` json DEFAULT NULL,
  `sale_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_orders_company_external_id_unique` (`company_id`,`external_id`),
  KEY `service_orders_company_id_status_index` (`company_id`,`status`),
  KEY `service_orders_order_number_index` (`order_number`),
  KEY `service_orders_serial_number_index` (`serial_number`),
  KEY `service_orders_is_demo_index` (`is_demo`),
  CONSTRAINT `service_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sessions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `token` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  `impersonated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`token`),
  KEY `sessions_company_id_revoked_index` (`company_id`,`revoked`),
  KEY `sessions_user_id_revoked_index` (`user_id`,`revoked`),
  CONSTRAINT `sessions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `k` varchar(64) NOT NULL,
  `v` text,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table `settings`
LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`k`, `v`) VALUES 
('builder_default_monthly_limit', '10'),
('builder_plan_limits', '{\"trial\":2,\"free\":2,\"basic\":10,\"starter\":10,\"regular\":10,\"pro\":30,\"professional\":30,\"extended\":-1,\"enterprise\":-1,\"unlimited\":-1}'),
('github_branch', 'feat/windows-offline-sync'),
('github_repo', 'prakash111/zoom-pos'),
('github_token', ''),
('landing_page_config', '{\n    \"brand_name\": \"Zoom POS & Market\",\n    \"brand_tagline\": \"Smarter Business. Greater Control.\",\n    \"support_email\": \"support@zoomnearby.com\",\n    \"currency_code\": \"USD\",\n    \"currency_symbol\": \"$\",\n    \"default_theme_mode\": \"dark\",\n    \"active_color_preset\": \"midnight_obsidian\",\n    \"section_colors\": {\n        \"hero\": {\n            \"name\": \"Hero Banner Section\",\n            \"icon\": \"\\ud83d\\ude80\",\n            \"dark_bg\": \"#070a1a\",\n            \"light_bg\": \"#ffffff\"\n        },\n        \"category_strip\": {\n            \"name\": \"Category Overview Strip\",\n            \"icon\": \"\\ud83c\\udff7\\ufe0f\",\n            \"dark_bg\": \"#0c1029\",\n            \"light_bg\": \"#f8fafc\"\n        },\n        \"features\": {\n            \"name\": \"Core Platform Features\",\n            \"icon\": \"\\u26a1\",\n            \"dark_bg\": \"#070a1a\",\n            \"light_bg\": \"#ffffff\"\n        },\n        \"business_types\": {\n            \"name\": \"Business Types & Verticals\",\n            \"icon\": \"\\ud83d\\uded2\",\n            \"dark_bg\": \"#0c1029\",\n            \"light_bg\": \"#f8fafc\"\n        },\n        \"industries\": {\n            \"name\": \"Commercial Industries Grid\",\n            \"icon\": \"\\ud83c\\udfed\",\n            \"dark_bg\": \"#070a1a\",\n            \"light_bg\": \"#ffffff\"\n        },\n        \"modules\": {\n            \"name\": \"Modular Add-Ons Overview\",\n            \"icon\": \"\\ud83d\\udce6\",\n            \"dark_bg\": \"#0c1029\",\n            \"light_bg\": \"#f8fafc\"\n        },\n        \"demos\": {\n            \"name\": \"Multi-Platform Live Demos\",\n            \"icon\": \"\\ud83d\\udcf1\",\n            \"dark_bg\": \"#070a1a\",\n            \"light_bg\": \"#ffffff\"\n        },\n        \"pricing\": {\n            \"name\": \"Pricing & Bundles Section\",\n            \"icon\": \"\\ud83d\\udcb0\",\n            \"dark_bg\": \"#0c1029\",\n            \"light_bg\": \"#f8fafc\"\n        },\n        \"why_us\": {\n            \"name\": \"Why Zoom POS? & Metrics\",\n            \"icon\": \"\\ud83c\\udfc6\",\n            \"dark_bg\": \"#070a1a\",\n            \"light_bg\": \"#ffffff\"\n        },\n        \"ecosystem\": {\n            \"name\": \"Ecosystem Architecture Section\",\n            \"icon\": \"\\ud83c\\udf10\",\n            \"dark_bg\": \"#0c1029\",\n            \"light_bg\": \"#f8fafc\"\n        },\n        \"cta_banner\": {\n            \"name\": \"Ready to Build CTA Banner\",\n            \"icon\": \"\\ud83d\\udce3\",\n            \"dark_bg\": \"linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)\",\n            \"light_bg\": \"linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)\"\n        },\n        \"faq\": {\n            \"name\": \"Frequently Asked Questions (FAQ)\",\n            \"icon\": \"\\u2753\",\n            \"dark_bg\": \"#070a1a\",\n            \"light_bg\": \"#ffffff\"\n        },\n        \"footer\": {\n            \"name\": \"Footer\",\n            \"icon\": \"\\ud83d\\udc63\",\n            \"dark_bg\": \"#050714\",\n            \"light_bg\": \"#0f172a\"\n        }\n    },\n    \"show_top_nav\": true,\n    \"sticky_top_nav\": true,\n    \"nav_show_brand\": true,\n    \"nav_show_links\": true,\n    \"nav_show_language\": true,\n    \"nav_show_themes\": true,\n    \"nav_show_dark_toggle\": true,\n    \"nav_show_demo_btn\": true,\n    \"nav_show_buy_btn\": true,\n    \"hero_badge\": \"Complete Business Management Platform\",\n    \"hero_title\": \"Run Your Entire Business<br>From <span>One Powerful POS</span>\",\n    \"hero_subtitle\": \"Retail, Restaurant & Service \\u2014 sales, inventory, customers, reports and more in one self-hosted platform.\",\n    \"cta_primary\": \"Buy Now\",\n    \"cta_secondary\": \"Live Demo\",\n    \"cta_verify\": \"Verify License\",\n    \"demo_admin_url\": \"https://saas.zoomnearby.com/login\",\n    \"demo_admin_title\": \"SuperAdmin SaaS Portal\",\n    \"demo_admin_desc\": \"Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.\",\n    \"demo_store_url\": \"https://saas.zoomnearby.com/store/login\",\n    \"demo_store_title\": \"Store & Cashier Backoffice\",\n    \"demo_store_desc\": \"Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.\",\n    \"demo_flutter_web_url\": \"https://saas.zoomnearby.com/pos-web/\",\n    \"demo_flutter_web_title\": \"Flutter Web POS\",\n    \"demo_flutter_web_desc\": \"Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.\",\n    \"demo_flutter_windows_url\": \"https://saas.zoomnearby.com/Zoom-Sales-CRM-Inventory-Setup-1.0.5.exe\",\n    \"demo_flutter_windows_title\": \"Flutter Windows Desktop App\",\n    \"demo_flutter_windows_desc\": \"Native 64-bit Windows desktop installer with ESC/POS thermal receipt printer integration.\",\n    \"demo_flutter_android_url\": \"https://saas.zoomnearby.com/zoom-sales-crm-inventory-v1.0.5.apk\",\n    \"demo_flutter_android_title\": \"Flutter Android POS App\",\n    \"demo_flutter_android_desc\": \"Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.\",\n    \"documentation_url\": \"https://saas.zoomnearby.com/documentation\",\n    \"documentation_title\": \"Documentation & Setup Guide\",\n    \"documentation_desc\": \"Comprehensive developer and administrator installation guide.\",\n    \"demo_other_links\": [],\n    \"metric_1_val\": \"$49.00\",\n    \"metric_1_label\": \"One-Time Core Script Price\",\n    \"metric_2_val\": \"100%\",\n    \"metric_2_label\": \"Self-Hosted Source Code\",\n    \"metric_3_val\": \"Instant\",\n    \"metric_3_label\": \"License Key Email Delivery\",\n    \"metric_4_val\": \"Unlimited\",\n    \"metric_4_label\": \"Stores, Cashiers & Registers\",\n    \"discount_tier_1\": 10,\n    \"discount_tier_2\": 15,\n    \"discount_tier_3\": 20,\n    \"features\": [\n        {\n            \"icon\": \"\\ud83d\\uded2\",\n            \"title\": \"Retail POS Engine\",\n            \"desc\": \"High-speed barcode scanner checkout, variant inventory, customer credit accounts, return handling, and price label printing.\"\n        },\n        {\n            \"icon\": \"\\ud83c\\udf7d\\ufe0f\",\n            \"title\": \"Restaurant & Dine-In (Tables + KOT)\",\n            \"desc\": \"Visual floor & table layout management, Kitchen Order Tickets (KOT) printing/display, waiter ordering, and split bill checkout.\"\n        },\n        {\n            \"icon\": \"\\u2615\",\n            \"title\": \"Caf\\u00e9 & Quick-Service Counter\",\n            \"desc\": \"Rapid touch-optimized ordering, modifiers/addons, kitchen queue tokens, and swift card/cash cashier settlement.\"\n        },\n        {\n            \"icon\": \"\\ud83c\\udfec\",\n            \"title\": \"Multi-Store & Warehousing\",\n            \"desc\": \"Manage multiple stores and stock warehouses from one screen. Inter-branch stock transfers and low inventory warnings.\"\n        },\n        {\n            \"icon\": \"\\ud83d\\udda8\\ufe0f\",\n            \"title\": \"Thermal Receipt & Barcode Printing\",\n            \"desc\": \"Direct ESC/POS 80mm & 58mm thermal printer support, PDF invoices, customized receipts, and automatic barcode sticker generator.\"\n        },\n        {\n            \"icon\": \"\\ud83c\\udf10\",\n            \"title\": \"Multi-Tenant SaaS Architecture\",\n            \"desc\": \"Create pricing subscription packages, allow business tenants to register, manage their billing, and connect custom domains.\"\n        }\n    ],\n    \"faqs\": [\n        {\n            \"q\": \"What is included in the Core main script?\",\n            \"a\": \"The Core main script includes full Retail POS, Restaurant POS (with Table Management, Kitchen Order Tickets / KOT, and Waiter workflow), and Caf\\u00e9 / Quick-Service modes built-in out of the box. It also includes multi-store warehousing, thermal receipt printing (80mm/58mm), barcode generation, customer ledgers, and the complete multi-tenant SaaS billing engine.\"\n        },\n        {\n            \"q\": \"What do I receive after completing payment?\",\n            \"a\": \"Immediately upon purchase, your license details are rendered on screen and sent to your registered email address. This includes your official license key for the Core SaaS platform, plus individual license keys for any add-on modules purchased in your bundle, with simple setup steps.\"\n        },\n        {\n            \"q\": \"Can I host this on any domain, cPanel, or VPS?\",\n            \"a\": \"Yes! The system is designed to run on any standard hosting environment with PHP 8.2+ and MySQL. It runs perfectly on cPanel, CloudPanel, DirectAdmin, Ubuntu VPS, AWS, or DigitalOcean with standard Apache or Nginx.\"\n        },\n        {\n            \"q\": \"How does bundle pricing work?\",\n            \"a\": \"You can purchase the Core SaaS script for $49.00. If you wish to bundle other modules (such as Lead Manager, Pharmacy POS, or Salon), you can either select our discounted ready-made bundles or use our interactive bundle builder to select exactly the modules you need with automatic bundle discounts applied.\"\n        },\n        {\n            \"q\": \"How do I activate vertical modules like Lead Manager?\",\n            \"a\": \"In your self-hosted SaaS SuperAdmin panel, navigate to Modules. Find the purchased module, click Activate / Download, and enter the module\'s license key sent to your email. The system securely downloads the module package from the central license server and installs it automatically.\"\n        },\n        {\n            \"q\": \"Are there any recurring monthly subscription fees?\",\n            \"a\": \"No! You pay once for a lifetime perpetual license. You own the code and can use it forever on your registered domain without recurring platform fees.\"\n        }\n    ],\n    \"app_builder\": {\n        \"enabled\": true,\n        \"badge\": \"White-Label Cloud App Builder\",\n        \"title\": \"Build Your Branded Mobile & Desktop Apps Without Local SDKs\",\n        \"subtitle\": \"Compile production-ready Flutter apps directly in the cloud. Customize your app name, logo, color palette, and package ID, then let our automated GitHub Actions cloud pipeline generate Android APK/AAB, Windows Desktop (.exe), and Web PWA binaries.\",\n        \"doc_button_text\": \"Builder Documentation\",\n        \"doc_button_url\": \"https://saas.zoomnearby.com/documentation/index.html#app-builder-guide-and-eligibility\",\n        \"launch_button_text\": \"Launch Builder\",\n        \"launch_button_url\": \"https://saas.zoomnearby.com/app-builder/\",\n        \"eligibility_title\": \"Eligibility Note:\",\n        \"eligibility_text\": \"App Builder compilation quotas are tied to Core SaaS Script licenses and bundles containing Core. Standalone modules or plugins do not have independent build quotas.\",\n        \"eligibility_badge\": \"Core Script: Included\",\n        \"features\": [\n            {\n                \"icon\": \"\\u2601\\ufe0f\",\n                \"title\": \"Zero-SDK Cloud Compilation\",\n                \"body\": \"No need to install Flutter, Dart, Android Studio, Gradle, or Visual Studio C++ compilers on your computer. Builds are compiled in isolated GitHub Actions cloud environments.\",\n                \"badge\": \"Zero local configuration\",\n                \"color\": \"blue\"\n            },\n            {\n                \"icon\": \"\\ud83c\\udfa8\",\n                \"title\": \"100% White-Label Branding\",\n                \"body\": \"Customize your app name, custom package identifier (com.yourbrand.pos), primary & accent brand colors, app icon, and splash screen to match your visual identity.\",\n                \"badge\": \"Custom logos & colors\",\n                \"color\": \"purple\"\n            },\n            {\n                \"icon\": \"\\ud83d\\udcf1\",\n                \"title\": \"Multi-Platform Generation\",\n                \"body\": \"Export Android APK binaries and Play Store AAB bundles, native Windows desktop executable packages (.exe/.zip) with offline sync, and deployable Web PWAs.\",\n                \"badge\": \"Android \\u2022 Windows \\u2022 Web\",\n                \"color\": \"emerald\"\n            },\n            {\n                \"icon\": \"\\ud83d\\udcec\",\n                \"title\": \"Build History & Email Alerts\",\n                \"body\": \"Track all your builds in the persistent history dashboard. Download completed artifacts directly, and receive automatic email alerts with secure download links upon build completion.\",\n                \"badge\": \"Instant download & notifications\",\n                \"color\": \"amber\"\n            }\n        ],\n        \"bg_mode\": \"theme_matching\",\n        \"bg_color_start\": \"#0d1428\",\n        \"bg_color_end\": \"#070b1a\",\n        \"border_color\": \"rgba(59, 130, 246, 0.28)\",\n        \"accent_color\": \"#3b82f6\"\n    },\n    \"business_types_cards\": {\n        \"retail\": {\n            \"key\": \"retail\",\n            \"title\": \"Retail & Supermarkets\",\n            \"icon\": \"\\ud83d\\uded2\",\n            \"tag_text\": \"Built-in Core Platform\",\n            \"tag_class\": \"tag-core\",\n            \"display_mode\": \"image\",\n            \"image_url\": \"assets/images/retail-pos-mockup.png\",\n            \"icon_bg\": \"#eef2ff\",\n            \"icon_color\": \"#4f46e5\",\n            \"badge_label\": \"Retail POS\",\n            \"badge_sub\": \"Barcode & Counter Setup\",\n            \"btn_text\": \"Explore Retail \\u2192\",\n            \"btn_type\": \"link\",\n            \"btn_url\": \"#pricing\",\n            \"module_slug\": \"\",\n            \"features\": [\n                \"Barcode Scanning\",\n                \"Multi-Variant Stock\",\n                \"GST / VAT Taxes\",\n                \"Customer Ledgers\",\n                \"Purchase Orders\",\n                \"Returns & Refunds\",\n                \"Thermal Receipts\",\n                \"Price Label Print\"\n            ]\n        },\n        \"restaurant\": {\n            \"key\": \"restaurant\",\n            \"title\": \"Restaurant, Caf\\u00e9 & QSR\",\n            \"icon\": \"\\ud83c\\udf7d\\ufe0f\",\n            \"tag_text\": \"Built-in Core Platform\",\n            \"tag_class\": \"tag-core\",\n            \"display_mode\": \"image\",\n            \"image_url\": \"assets/images/restaurant-pos-mockup.png\",\n            \"icon_bg\": \"#eff6ff\",\n            \"icon_color\": \"#2563eb\",\n            \"badge_label\": \"Restaurant & Caf\\u00e9\",\n            \"badge_sub\": \"Tables, KOT & Takeaway\",\n            \"btn_text\": \"Explore Restaurant \\u2192\",\n            \"btn_type\": \"link\",\n            \"btn_url\": \"#pricing\",\n            \"module_slug\": \"\",\n            \"features\": [\n                \"Visual Floor Tables\",\n                \"Kitchen Tickets (KOT)\",\n                \"Waiter Tablet App\",\n                \"Food Modifiers\",\n                \"Recipe Stock Costing\",\n                \"Contactless QR Menu\",\n                \"Split Bill by Seat\",\n                \"Queue Order Tokens\"\n            ]\n        },\n        \"pharmacy\": {\n            \"key\": \"pharmacy\",\n            \"title\": \"Pharmacy & Healthcare\",\n            \"icon\": \"\\ud83d\\udc8a\",\n            \"tag_text\": \"Vertical Module\",\n            \"tag_class\": \"tag-vertical\",\n            \"display_mode\": \"image\",\n            \"image_url\": \"assets/images/pharmacy-pos-mockup.png\",\n            \"icon_bg\": \"#123456\",\n            \"icon_color\": \"#abcdef\",\n            \"badge_label\": \"Custom Pharmacy\",\n            \"badge_sub\": \"Batch & Expiry Control\",\n            \"btn_text\": \"Buy Pharmacy Module \\u2192\",\n            \"btn_type\": \"checkout\",\n            \"btn_url\": \"\",\n            \"module_slug\": \"pharmacy\",\n            \"features\": [\n                \"Batch & Lot Numbers\",\n                \"Expiry Date Alerts\",\n                \"Prescription Intake\",\n                \"Prescribing Doctors\",\n                \"Generic Salt Search\",\n                \"Schedule H Audit Log\",\n                \"Supplier Batch PO\",\n                \"Barcode Dispense\"\n            ]\n        },\n        \"salon\": {\n            \"key\": \"salon\",\n            \"title\": \"Salon, Spa & Wellness\",\n            \"icon\": \"\\u2702\\ufe0f\",\n            \"tag_text\": \"Vertical Module\",\n            \"tag_class\": \"tag-vertical\",\n            \"display_mode\": \"image\",\n            \"image_url\": \"assets/images/salon-pos-mockup.png\",\n            \"icon_bg\": \"#ffe4e6\",\n            \"icon_color\": \"#e11d48\",\n            \"badge_label\": \"Glamour Spa POS\",\n            \"badge_sub\": \"Appointments & Stylists\",\n            \"btn_text\": \"Buy Salon Module \\u2192\",\n            \"btn_type\": \"checkout\",\n            \"btn_url\": \"\",\n            \"module_slug\": \"salon\",\n            \"features\": [\n                \"Booking Calendar\",\n                \"Stylist Allocation\",\n                \"Service Durations\",\n                \"Staff Commissions\",\n                \"Tip Management\",\n                \"SMS/WhatsApp Alert\",\n                \"Client History\",\n                \"Loyalty Points\"\n            ]\n        },\n        \"repair\": {\n            \"key\": \"repair\",\n            \"title\": \"Repair Service & Workbench\",\n            \"icon\": \"\\ud83d\\udd27\",\n            \"tag_text\": \"Vertical Module\",\n            \"tag_class\": \"tag-vertical\",\n            \"display_mode\": \"image\",\n            \"image_url\": \"assets/images/repair-pos-mockup.png\",\n            \"icon_bg\": \"#fffbeb\",\n            \"icon_color\": \"#d97706\",\n            \"badge_label\": \"Repair Workbench\",\n            \"badge_sub\": \"Tickets, Parts & Diagnosis\",\n            \"btn_text\": \"Buy Repair Module \\u2192\",\n            \"btn_type\": \"checkout\",\n            \"btn_url\": \"\",\n            \"module_slug\": \"repairtechnician\",\n            \"features\": [\n                \"Intake Job Tickets\",\n                \"Condition Checklist\",\n                \"Diagnostic Notes\",\n                \"Spare Parts Billing\",\n                \"Labor Fees\",\n                \"Technician Kanban\",\n                \"Lifecycle Status\",\n                \"SMS Pickup Alerts\"\n            ]\n        },\n        \"crm\": {\n            \"key\": \"crm\",\n            \"title\": \"Lead Management CRM\",\n            \"icon\": \"\\ud83d\\udcca\",\n            \"tag_text\": \"\\ud83d\\udd25 Top Add-On\",\n            \"tag_class\": \"tag-vertical\",\n            \"display_mode\": \"image\",\n            \"image_url\": \"assets/images/lead-crm-mockup.png\",\n            \"icon_bg\": \"#ecfdf5\",\n            \"icon_color\": \"#059669\",\n            \"badge_label\": \"Lead CRM Pipeline\",\n            \"badge_sub\": \"Enquiries to Customers\",\n            \"btn_text\": \"Buy Lead Module \\u2192\",\n            \"btn_type\": \"checkout\",\n            \"btn_url\": \"\",\n            \"module_slug\": \"leadmanagement\",\n            \"features\": [\n                \"Visual Kanban Board\",\n                \"Multi-Channel Leads\",\n                \"Follow-Up Tasks\",\n                \"Quotations Linking\",\n                \"1-Click Deal Won\",\n                \"Auto Provisioning\",\n                \"Source Attribution\",\n                \"Conversion Metrics\"\n            ]\n        }\n    }\n}');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `store_user`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `store_user`;
CREATE TABLE `store_user` (
  `store_id` bigint unsigned NOT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`store_id`,`user_id`),
  KEY `store_user_role_id_foreign` (`role_id`),
  KEY `store_user_user_id_foreign` (`user_id`),
  CONSTRAINT `store_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `store_user_store_id_foreign` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `store_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `stores`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pincode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_header` text COLLATE utf8mb4_unicode_ci,
  `receipt_footer` text COLLATE utf8mb4_unicode_ci,
  `invoice_prefix` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INV-',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `settings` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stores_company_id_code_unique` (`company_id`,`code`),
  KEY `stores_company_id_is_active_index` (`company_id`,`is_active`),
  KEY `stores_tenant_id_index` (`tenant_id`),
  KEY `stores_branch_code_index` (`branch_code`),
  CONSTRAINT `stores_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stores_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `subscription_invoices`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `subscription_invoices`;
CREATE TABLE `subscription_invoices` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscription_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `currency` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tax_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GST',
  `tax_breakdown` json DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free_trial',
  `payment_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paid',
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `seller_details` json DEFAULT NULL,
  `buyer_details` json DEFAULT NULL,
  `pdf_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_invoices_invoice_number_unique` (`invoice_number`),
  KEY `subscription_invoices_subscription_id_foreign` (`subscription_id`),
  KEY `subscription_invoices_company_id_status_index` (`company_id`,`status`),
  CONSTRAINT `subscription_invoices_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_invoices_subscription_id_foreign` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `subscriptions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `origin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_admin',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_plan_name_foreign` (`plan_name`),
  KEY `subscriptions_company_id_status_index` (`company_id`,`status`),
  CONSTRAINT `subscriptions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_plan_name_foreign` FOREIGN KEY (`plan_name`) REFERENCES `plans` (`name`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `suppliers`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `legal_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_company_id_external_id_unique` (`company_id`,`external_id`),
  CONSTRAINT `suppliers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `sync_tombstones`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `sync_tombstones`;
CREATE TABLE `sync_tombstones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `server_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_tombstones_unique` (`company_id`,`entity`,`external_id`),
  KEY `sync_tombstones_delta_idx` (`company_id`,`entity`,`deleted_at`),
  CONSTRAINT `sync_tombstones_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `system_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `system_translations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `system_translations`;
CREATE TABLE `system_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `locale` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `version` bigint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_translations_locale_key_unique` (`locale`,`key`),
  KEY `system_translations_locale_index` (`locale`),
  KEY `system_translations_module_index` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tax_rules`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tax_rules`;
CREATE TABLE `tax_rules` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rate` decimal(6,3) NOT NULL DEFAULT '0.000',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `country` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `region` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calc_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_inclusive` tinyint(1) NOT NULL DEFAULT '0',
  `is_compound` tinyint(1) NOT NULL DEFAULT '0',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sub_components` json DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `effective_from` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tax_rules_company_id_country_active_index` (`company_id`,`country`,`active`),
  KEY `tax_rules_is_demo_index` (`is_demo`),
  CONSTRAINT `tax_rules_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_api_keys`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_api_keys`;
CREATE TABLE `tenant_api_keys` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_api_keys_token_unique` (`token`),
  KEY `tenant_api_keys_company_id_active_index` (`company_id`,`active`),
  KEY `tenant_api_keys_user_id_index` (`user_id`),
  CONSTRAINT `tenant_api_keys_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_api_keys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_custom_fields`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_custom_fields`;
CREATE TABLE `tenant_custom_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `field_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `options` json DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_custom_fields_tenant_id_module_field_key_unique` (`tenant_id`,`module`,`field_key`),
  KEY `tenant_custom_fields_tenant_id_index` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_custom_pages`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_custom_pages`;
CREATE TABLE `tenant_custom_pages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_pages_comp_slug_unique` (`company_id`,`slug`),
  KEY `tenant_custom_pages_company_id_index` (`company_id`),
  KEY `tenant_custom_pages_tenant_id_index` (`tenant_id`),
  KEY `tenant_custom_pages_slug_index` (`slug`),
  KEY `tenant_custom_pages_is_published_index` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_document_templates`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_document_templates`;
CREATE TABLE `tenant_document_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `template_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme_color` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#10b981',
  `logo_placement` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left',
  `header_title` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_conditions` text COLLATE utf8mb4_unicode_ci,
  `show_qr_code` tinyint(1) NOT NULL DEFAULT '1',
  `show_tax_breakup` tinyint(1) NOT NULL DEFAULT '1',
  `footer_notes` text COLLATE utf8mb4_unicode_ci,
  `send_as_attachment` tinyint(1) NOT NULL DEFAULT '1',
  `send_text_with_link` tinyint(1) NOT NULL DEFAULT '0',
  `message_body_template` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_features`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_features`;
CREATE TABLE `tenant_features` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `feature_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_features_tenant_id_feature_key_unique` (`tenant_id`,`feature_key`),
  KEY `tenant_features_tenant_id_index` (`tenant_id`),
  KEY `tenant_features_feature_key_index` (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_inquiries`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_inquiries`;
CREATE TABLE `tenant_inquiries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unread',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_inquiries_company_id_status_index` (`company_id`,`status`),
  KEY `tenant_inquiries_company_id_created_at_index` (`company_id`,`created_at`),
  CONSTRAINT `tenant_inquiries_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_navigation_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_navigation_settings`;
CREATE TABLE `tenant_navigation_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_navigation_settings_tenant_id_module_key_unique` (`tenant_id`,`module_key`),
  KEY `tenant_navigation_settings_tenant_id_index` (`tenant_id`),
  KEY `tenant_navigation_settings_module_key_index` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_notification_gateways`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_notification_gateways`;
CREATE TABLE `tenant_notification_gateways` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `credentials` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_notification_gateways_company_id_channel_unique` (`company_id`,`channel`),
  KEY `tenant_notification_gateways_tenant_id_index` (`tenant_id`),
  KEY `tenant_notification_gateways_channel_index` (`channel`),
  KEY `tenant_notification_gateways_is_enabled_index` (`is_enabled`),
  CONSTRAINT `tenant_notification_gateways_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_notifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_notifications`;
CREATE TABLE `tenant_notifications` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_status` tinyint(1) NOT NULL DEFAULT '0',
  `cta_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cta_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_notifications_company_id_read_status_index` (`company_id`,`read_status`),
  CONSTRAINT `tenant_notifications_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_settings`;
CREATE TABLE `tenant_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_settings_tenant_id_key_unique` (`tenant_id`,`key`),
  KEY `tenant_settings_tenant_id_index` (`tenant_id`),
  KEY `tenant_settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_store_menus`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_store_menus`;
CREATE TABLE `tenant_store_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'header_nav',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom_url',
  `target_url` text COLLATE utf8mb4_unicode_ci,
  `page_id` bigint unsigned DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `target` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_store_menus_company_id_index` (`company_id`),
  KEY `tenant_store_menus_tenant_id_index` (`tenant_id`),
  KEY `tenant_store_menus_location_index` (`location`),
  KEY `tenant_store_menus_type_index` (`type`),
  KEY `tenant_store_menus_page_id_index` (`page_id`),
  KEY `tenant_store_menus_category_id_index` (`category_id`),
  KEY `tenant_store_menus_sort_order_index` (`sort_order`),
  KEY `tenant_store_menus_is_visible_index` (`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `tenant_translations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_translations`;
CREATE TABLE `tenant_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `group` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '*',
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_loc_grp_key_unique` (`tenant_id`,`locale`,`group`,`key`),
  KEY `tenant_translations_tenant_id_index` (`tenant_id`),
  KEY `tenant_translations_locale_index` (`locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `units`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abbreviation` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_company_id_external_id_unique` (`company_id`,`external_id`),
  CONSTRAINT `units_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `units`
LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` (`id`, `company_id`, `external_id`, `name`, `abbreviation`, `created_at`, `updated_at`, `synced_at`) VALUES 
('98', 'emp_7e513c6bfdec43e6', NULL, 'Piece', 'pcs', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('99', 'emp_7e513c6bfdec43e6', NULL, 'Kilogram', 'kg', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('100', 'emp_7e513c6bfdec43e6', NULL, 'Box', 'bx', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('101', 'emp_7e513c6bfdec43e6', NULL, 'Pack', 'pk', '2026-09-16 13:50:27', '2026-09-16 13:50:27', NULL),
('102', 'emp_69df53ee9686f23b', NULL, 'Piece', 'pcs', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('103', 'emp_69df53ee9686f23b', NULL, 'Kilogram', 'kg', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('104', 'emp_69df53ee9686f23b', NULL, 'Box', 'bx', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('105', 'emp_69df53ee9686f23b', NULL, 'Pack', 'pk', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('106', 'emp_8b2acdee34ae35a1', NULL, 'Piece', 'pcs', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('107', 'emp_8b2acdee34ae35a1', NULL, 'Kilogram', 'kg', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('108', 'emp_8b2acdee34ae35a1', NULL, 'Box', 'bx', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('109', 'emp_8b2acdee34ae35a1', NULL, 'Pack', 'pk', '2026-09-16 13:50:28', '2026-09-16 13:50:28', NULL),
('110', 'emp_335c95c1360de705', NULL, 'Piece', 'pcs', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('111', 'emp_335c95c1360de705', NULL, 'Kilogram', 'kg', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('112', 'emp_335c95c1360de705', NULL, 'Box', 'bx', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('113', 'emp_335c95c1360de705', NULL, 'Pack', 'pk', '2026-09-16 13:50:29', '2026-09-16 13:50:29', NULL),
('114', 'emp_c96f27eaf74497c7', NULL, 'Piece', 'pcs', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('115', 'emp_c96f27eaf74497c7', NULL, 'Kilogram', 'kg', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('116', 'emp_c96f27eaf74497c7', NULL, 'Box', 'bx', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('117', 'emp_c96f27eaf74497c7', NULL, 'Pack', 'pk', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('118', 'emp_8ac4a905318d6a15', NULL, 'Piece', 'pcs', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('119', 'emp_8ac4a905318d6a15', NULL, 'Kilogram', 'kg', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('120', 'emp_8ac4a905318d6a15', NULL, 'Box', 'bx', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('121', 'emp_8ac4a905318d6a15', NULL, 'Pack', 'pk', '2026-09-16 13:50:30', '2026-09-16 13:50:30', NULL),
('122', 'emp_a9140de48b0c6abe', NULL, 'Unidade', 'Unid', '2026-09-19 12:34:34', '2026-09-19 12:34:34', NULL);
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

-- -------------------------------------------------------------
-- Table structure for `users`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pin_code` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operador',
  `locale` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_rate` decimal(8,2) NOT NULL DEFAULT '0.00',
  `commission_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `shift` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dock_position` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_specialist` tinyint(1) NOT NULL DEFAULT '0',
  `invitation_code_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invitation_expires_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `verification_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_code_expires_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_fields` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `current_store_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_company_id_login_unique` (`company_id`,`login`),
  KEY `users_company_id_email_index` (`company_id`,`email`),
  KEY `users_is_demo_index` (`is_demo`),
  KEY `users_is_specialist_index` (`is_specialist`),
  KEY `users_current_store_id_foreign` (`current_store_id`),
  CONSTRAINT `users_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_current_store_id_foreign` FOREIGN KEY (`current_store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `vendor_bill_payments`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `vendor_bill_payments`;
CREATE TABLE `vendor_bill_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_bill_id` bigint unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_date` date NOT NULL,
  `reference_number` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_bill_payments_company_external_id_unique` (`company_id`,`external_id`),
  KEY `vendor_bill_payments_vendor_bill_id_foreign` (`vendor_bill_id`),
  KEY `vendor_bill_payments_created_by_foreign` (`created_by`),
  KEY `vendor_bill_payments_company_id_vendor_bill_id_index` (`company_id`,`vendor_bill_id`),
  CONSTRAINT `vendor_bill_payments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_bill_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bill_payments_vendor_bill_id_foreign` FOREIGN KEY (`vendor_bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table structure for `vendor_bills`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `vendor_bills`;
CREATE TABLE `vendor_bills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `vendor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bill_number` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operational',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `due_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_method` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_bills_company_external_id_unique` (`company_id`,`external_id`),
  KEY `vendor_bills_supplier_id_foreign` (`supplier_id`),
  KEY `vendor_bills_created_by_foreign` (`created_by`),
  KEY `vendor_bills_company_id_status_index` (`company_id`,`status`),
  KEY `vendor_bills_company_id_due_date_index` (`company_id`,`due_date`),
  KEY `vendor_bills_company_id_category_index` (`company_id`,`category`),
  CONSTRAINT `vendor_bills_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_bills_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bills_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Default Demo Accounts & Tenant Structure
-- -------------------------------------------------------------
LOCK TABLES `companies` WRITE;
INSERT INTO `companies` (`id`, `name`, `subdomain`, `email`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES (1, 'Zoom Demo Store', 'demo', 'store@demo.com', '+1234567890', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
UNLOCK TABLES;

LOCK TABLES `stores` WRITE;
INSERT INTO `stores` (`id`, `company_id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES (1, 1, 'Main Retail & Cafe Branch', 'STORE-01', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
UNLOCK TABLES;

LOCK TABLES `users` WRITE;
INSERT INTO `users` (`id`, `company_id`, `store_id`, `name`, `email`, `password`, `is_active`, `created_at`, `updated_at`) VALUES 
(1, NULL, NULL, 'Super Administrator', 'admin@zoompos.com', '$2y$12$huVEQ55RErfp32zQmTogG.ORbgAWBHgoDvKqr/aZgvQl5xvHwiyLK', 1, NOW(), NOW()),
(2, 1, 1, 'Store Manager', 'store@demo.com', '$2y$12$huVEQ55RErfp32zQmTogG.ORbgAWBHgoDvKqr/aZgvQl5xvHwiyLK', 1, NOW(), NOW()),
(3, 1, 1, 'Cashier Demo', 'cashier@demo.com', '$2y$12$huVEQ55RErfp32zQmTogG.ORbgAWBHgoDvKqr/aZgvQl5xvHwiyLK', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `password`=VALUES(`password`);
UNLOCK TABLES;

LOCK TABLES `model_has_roles` WRITE;
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES 
(1, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2),
(4, 'App\\Models\\User', 3)
ON DUPLICATE KEY UPDATE `role_id`=VALUES(`role_id`);
UNLOCK TABLES;

LOCK TABLES `restaurant_tables` WRITE;
INSERT INTO `restaurant_tables` (`id`, `company_id`, `store_id`, `name`, `seating_capacity`, `status`, `created_at`, `updated_at`) VALUES 
(1, 1, 1, 'Table T-01', 4, 'available', NOW(), NOW()),
(2, 1, 1, 'Table T-02', 2, 'available', NOW(), NOW()),
(3, 1, 1, 'Table T-03', 6, 'available', NOW(), NOW()),
(4, 1, 1, 'VIP Lounge-01', 8, 'available', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
UNLOCK TABLES;

SET FOREIGN_KEY_CHECKS=1;
