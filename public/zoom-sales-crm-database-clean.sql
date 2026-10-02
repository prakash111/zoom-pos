-- ==============================================================================
-- Zoom Sales CRM & Inventory POS — Complete Clean Database Schema
-- Version: 1.0.5 (CodeCanyon Release)
-- Includes: Retail & Restaurant Modules Built-in
-- ==============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- 1. Table Definitions
-- -------------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!50717 EXECUTE s */;
/*!50717 DEALLOCATE PREPARE s */;
/*!50717 EXECUTE s */;
/*!50717 DEALLOCATE PREPARE s */;
DROP TABLE IF EXISTS `activation_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ai_queries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=953 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `automated_reminder_dispatches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_register_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_registers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=442 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consignment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `consignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_notification_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=291 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  CONSTRAINT `customers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=420 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `desktop_sync_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dining_floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dining_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dismissed_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dynamic_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otp_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verifications_email_index` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kitchen_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_mod_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_mod_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_mod_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leads`;
/*!50001 DROP VIEW IF EXISTS `leads`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `leads` AS SELECT 
 1 AS `id`,
 1 AS `company_id`,
 1 AS `lead_code`,
 1 AS `name`,
 1 AS `title`,
 1 AS `company_name`,
 1 AS `email`,
 1 AS `phone`,
 1 AS `source_id`,
 1 AS `source_name`,
 1 AS `source`,
 1 AS `status`,
 1 AS `stage`,
 1 AS `priority`,
 1 AS `estimated_value`,
 1 AS `expected_value`,
 1 AS `assigned_to`,
 1 AS `notes`,
 1 AS `requirement_summary`,
 1 AS `lost_reason`,
 1 AS `converted_at`,
 1 AS `customer_id`,
 1 AS `created_at`,
 1 AS `updated_at`,
 1 AS `deleted_at`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `licenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `license_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_slug` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registered_domain` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bound_domain` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bound_ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_domains` json DEFAULT NULL,
  `license_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular',
  `plan` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notification_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `of_kv_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=221 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_gateway_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pending_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_mod_drug_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_mod_prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_mod_prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pharmacy_prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `store_limit` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_branding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_system`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_system` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_store_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=856 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `published_catalogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `push_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `push_notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_mod_device_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_mod_ticket_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_mod_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_ticket_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `repair_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3563 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=1879 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salon_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salon_mod_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salon_mod_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salon_mod_stylists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sdui_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sdui_screens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `service_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `store_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_tombstones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2015 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_custom_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_features`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=910 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_navigation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=910 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_notification_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_store_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bill_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendor_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `leads`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`saas-db`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `leads` AS select `lead_mod_leads`.`id` AS `id`,`lead_mod_leads`.`company_id` AS `company_id`,`lead_mod_leads`.`lead_code` AS `lead_code`,`lead_mod_leads`.`name` AS `name`,`lead_mod_leads`.`title` AS `title`,`lead_mod_leads`.`company_name` AS `company_name`,`lead_mod_leads`.`email` AS `email`,`lead_mod_leads`.`phone` AS `phone`,`lead_mod_leads`.`source_id` AS `source_id`,`lead_mod_leads`.`source_name` AS `source_name`,`lead_mod_leads`.`source` AS `source`,`lead_mod_leads`.`status` AS `status`,`lead_mod_leads`.`stage` AS `stage`,`lead_mod_leads`.`priority` AS `priority`,`lead_mod_leads`.`estimated_value` AS `estimated_value`,`lead_mod_leads`.`expected_value` AS `expected_value`,`lead_mod_leads`.`assigned_to` AS `assigned_to`,`lead_mod_leads`.`notes` AS `notes`,`lead_mod_leads`.`requirement_summary` AS `requirement_summary`,`lead_mod_leads`.`lost_reason` AS `lost_reason`,`lead_mod_leads`.`converted_at` AS `converted_at`,`lead_mod_leads`.`customer_id` AS `customer_id`,`lead_mod_leads`.`created_at` AS `created_at`,`lead_mod_leads`.`updated_at` AS `updated_at`,`lead_mod_leads`.`deleted_at` AS `deleted_at` from `lead_mod_leads` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50112 PREPARE s FROM @disable_bulk_load */;
/*!50112 EXECUTE s */;
/*!50112 DEALLOCATE PREPARE s */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- -------------------------------------------------------------
-- 2. Core Migrations History
-- -------------------------------------------------------------
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('1', '0001_01_01_000001_create_cache_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('2', '0001_01_01_000002_create_jobs_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('3', '2026_08_21_205731_create_plans_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('4', '2026_08_21_205732_create_platform_admins_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('5', '2026_08_21_205733_create_admin_sessions_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('6', '2026_08_21_205734_create_platform_system_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('7', '2026_08_21_205735_create_platform_branding_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('8', '2026_08_21_205736_create_platform_notifications_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('9', '2026_08_21_205737_create_payment_gateway_settings_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('10', '2026_08_21_205738_create_activation_codes_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('11', '2026_08_21_205739_create_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('12', '2026_08_21_205740_create_users_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('13', '2026_08_21_205741_create_sessions_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('14', '2026_08_21_205742_create_subscriptions_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('15', '2026_08_21_205743_create_categories_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('16', '2026_08_21_205744_create_brands_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('17', '2026_08_21_205745_create_units_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('18', '2026_08_21_205746_create_products_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('19', '2026_08_21_205747_create_customers_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('20', '2026_08_21_205748_create_suppliers_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('21', '2026_08_21_205749_create_sales_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('22', '2026_08_21_205750_create_tax_rules_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('23', '2026_08_21_205751_create_payment_transactions_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('24', '2026_08_21_205752_create_permissions_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('25', '2026_08_21_205753_create_ai_queries_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('26', '2026_08_21_205754_create_audit_logs_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('27', '2026_08_21_205755_create_tenant_notifications_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('28', '2026_08_21_205756_create_pending_registrations_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('29', '2026_08_21_205757_create_configurations_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('30', '2026_08_21_205758_create_of_kv_store_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('31', '2026_08_21_222129_create_published_catalogs_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('32', '2026_08_22_041000_add_branding_and_color_to_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('33', '2026_08_22_041001_create_payment_methods_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('34', '2026_08_22_042500_add_website_to_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('35', '2026_08_22_044500_create_restaurant_subsystem_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('36', '2026_08_22_050000_add_pos_mode_to_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('37', '2026_08_22_060000_create_subscription_invoices_and_tenant_registration_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('38', '2026_08_22_063145_add_theme_and_pos_layout_to_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('39', '2026_08_22_073046_add_smtp_from_fields_to_platform_branding_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('40', '2026_08_22_075652_add_custom_domain_to_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('41', '2026_08_22_085227_create_languages_and_company_translations_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('42', '2026_08_22_174116_create_order_payments_and_financial_ledger_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('43', '2026_08_22_224146_add_dynamic_branding_and_landing_customization_to_platform_branding', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('44', '2026_08_23_000001_add_currency_formatting_to_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('45', '2026_08_23_000002_create_cash_register_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('46', '2026_08_23_000003_create_pages_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('47', '2026_08_23_000004_add_landing_page_settings_to_platform_branding_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('48', '2026_08_23_000005_create_contact_inquiries_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('49', '2026_08_23_000006_add_store_type_to_contact_inquiries_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('50', '2026_08_23_000007_add_salesperson_commission_terms_receipt_to_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('51', '2026_08_23_200001_add_performance_and_query_indexes_to_tables', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('52', '2026_08_23_214500_create_tenant_translations_and_add_locale_to_users_and_companies_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('53', '2026_08_23_220500_add_terms_column_to_sales_table', '1');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('54', '2026_08_24_000001_enhance_cash_registers_tables', '2');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('55', '2026_08_24_000002_enhance_tax_rules_and_create_api_keys_table', '3');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('56', '2026_08_24_000003_add_tax_name_and_tax_rate_to_sales_table', '4');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('57', '2026_08_24_000004_add_is_active_to_plans_table', '5');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('58', '2026_08_24_180000_create_menu_items_table', '6');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('59', '2026_08_24_190000_create_pos_targets_consignments_and_merchant_fees_tables', '7');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('60', '2026_08_25_150000_create_service_orders_and_update_tenant_features', '8');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('61', '2026_08_27_120000_bind_tenant_api_keys_to_users', '9');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('62', '2026_08_27_121000_create_desktop_sync_receipts_table', '9');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('63', '2026_08_27_130000_add_external_id_to_desktop_sync_tables', '10');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('64', '2026_08_27_130100_add_synced_at_to_legacy_desktop_sync_tables', '10');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('65', '2026_08_27_140000_create_message_queue_table', '10');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('66', '2026_09_01_093404_create_customer_ledgers_table', '11');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('67', '2026_09_01_093405_add_due_balance_to_customers_table', '11');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('68', '2026_09_01_093406_add_metadata_to_payment_methods_table', '11');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('69', '2026_09_01_093407_create_custom_notification_channels_table', '11');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('70', '2026_09_01_093408_add_restaurant_mode_lock_to_companies_table', '11');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('71', '2026_09_01_100000_add_spice_levels_to_products_table', '12');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('72', '2026_09_02_081708_add_icon_to_custom_notification_channels_table', '13');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('73', '2026_09_02_090011_add_prep_time_fields_to_kitchen_tickets_table', '14');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('74', '2026_09_02_091618_create_password_reset_tokens_table', '15');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('75', '2026_09_02_000000_add_drawer_cover_to_companies_table', '16');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('76', '2026_09_02_120000_add_nav_config_to_companies_table', '17');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('77', '2026_09_03_000000_add_timezone_to_companies_table', '18');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('78', '2026_09_03_140000_create_system_push_notification_architecture', '19');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('79', '2026_09_03_233000_add_licensed_modules_to_companies_table', '20');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('80', '2026_09_04_020000_create_sdui_modules_and_screens_tables', '21');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('81', '2026_09_04_030000_add_branding_colors_to_companies_table', '22');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('82', '2026_09_04_050000_add_payload_format_to_custom_notification_channels_table', '23');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('83', '2026_09_04_060000_add_gradient_columns_to_companies_table', '24');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('84', '2026_09_04_070000_add_demo_and_mode_seeding_columns', '25');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('85', '2026_09_04_140000_add_nullable_cash_register_to_pos_sales', '26');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('86', '2026_09_04_120000_create_system_translations_table', '27');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('88', '2026_09_04_210000_create_pharmacy_and_repair_pos_tables', '28');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('89', '2026_09_04_220000_create_repair_device_categories_table', '29');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('90', '2026_09_05_000000_add_package_columns_to_sdui_modules_table', '30');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('93', '2026_09_05_010000_add_is_specialist_to_users_table', '31');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('94', '2026_09_05_020000_create_salon_appointments_table', '32');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('95', '2026_09_05_030000_add_advance_paid_to_salon_appointments_table', '33');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('96', '2026_09_05_160000_add_type_and_metadata_to_categories_table', '34');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('97', '2026_09_05_170000_add_navigation_menu_customization_to_companies_table', '35');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('98', '2026_09_05_180000_clean_rebuild_repair_module_tables', '36');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('99', '2026_09_05_184000_add_sort_order_and_code_to_categories_table', '37');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('100', '2026_09_06_120000_create_roles_table', '38');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('101', '2026_09_06_130000_add_vertical_context_to_core_platform', '39');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('102', '2026_09_06_150000_update_service_orders_customer_id_and_category_types', '40');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('103', '2026_09_06_160000_add_dynamic_schema_tax_and_reminders', '41');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('104', '2026_09_06_210000_add_form_field_customizations_and_service_catalog', '42');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('105', '2026_09_06_234000_create_email_verifications_table_and_add_verification_code_to_users', '43');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('106', '2026_09_07_120000_add_vertical_receipt_terms_to_companies_table', '44');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('107', '2026_09_07_130000_add_repair_checklist_schema_to_companies_table', '45');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('111', '2026_09_08_100000_add_license_columns_to_sdui_modules_table', '46');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('113', '2026_09_08_000001_create_pharmacy_module_tables', '47');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('114', '2026_09_09_000000_add_landing_downloads_and_section_meta_to_platform_branding', '48');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('115', '2026_09_08_000001_create_repair_module_tables', '49');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('116', '2026_09_08_000001_create_salon_module_tables', '50');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('117', '2026_09_09_120000_create_sync_tombstones_table', '51');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('118', '2026_09_09_130000_add_landing_features_and_testimonials_to_platform_branding', '52');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('119', '2026_09_10_100000_add_is_profile_completed_to_companies_table', '53');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('120', '2026_09_10_140000_add_auth_theme_to_platform_branding', '54');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('121', '2026_09_10_150000_clear_hardcoded_auth_marketing_copy', '55');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('122', '2026_09_10_160000_add_is_demo_to_companies_table', '56');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('123', '2026_09_10_170000_add_dock_position_to_users_table', '57');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('124', '2026_09_11_120000_create_tenant_notification_gateways_table', '58');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('125', '2026_09_12_100000_create_tenant_navigation_and_features_tables', '58');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('126', '2026_09_12_160000_create_dynamic_settings_table', '58');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('127', '2026_09_12_201241_change_module_tables_company_id_to_string', '59');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('128', '2026_09_12_213000_modify_leads_table_integrate_core_crm', '59');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('132', '2026_09_14_120000_create_automated_reminder_dispatches_table', '62');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('133', '2026_09_14_092629_add_deleted_at_to_lead_mod_leads_table', '63');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('134', '2026_09_14_094100_add_lead_id_to_sales_table', '64');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('147', '2026_09_12_000001_create_lead_module_tables', '65');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('148', '2026_09_12_000002_modify_leads_table_integrate_core_crm', '65');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('149', '2026_09_13_110000_enhance_reminders_table_fields', '65');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('150', '2026_09_14_160000_create_dismissed_notifications_table', '66');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('151', '2026_09_15_083000_create_tenant_settings_table', '67');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('152', '2026_09_15_091500_alter_tenant_id_to_string_in_settings_table', '68');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('153', '2026_09_15_220000_add_landing_content_to_platform_branding_table', '69');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('154', '2026_09_16_080000_create_system_settings_table', '70');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('155', '2026_09_16_120000_add_type_to_sdui_modules_table', '71');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('156', '2026_09_19_000001_clean_stale_navigation_cache_for_pharmacy_demo', '72');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('157', '2026_09_19_160000_add_custom_fields_to_contact_inquiries_table', '73');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('158', '2026_09_19_180000_add_description_to_products_table', '74');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('159', '2026_09_19_181000_add_meta_to_published_catalogs_table', '75');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('160', '2026_09_19_205004_add_features_and_extensions_to_plans_table', '76');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('161', '2026_09_20_060000_create_customer_storefront_tables', '77');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('162', '2026_09_20_132539_add_products_limit_to_plans_table', '78');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('163', '2026_09_20_160000_add_storefront_features_to_companies_table', '79');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('164', '2026_09_20_160001_create_coupons_and_usages_tables', '79');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('165', '2026_09_20_160002_add_tracking_code_to_sales_table', '79');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('166', '2026_09_20_190000_create_faqs_and_customer_enhancements_tables', '80');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('167', '2026_09_20_210000_create_product_reviews_and_settings_table', '81');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('168', '2026_09_20_202207_fix_company_id_type_in_coupons_and_reviews_tables', '82');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('169', '2026_09_20_220000_add_customer_verification_and_order_notifications', '83');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('170', '2026_09_21_040000_create_tenant_inquiries_table', '84');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('171', '2026_09_21_050000_cleanup_duplicate_storefront_navigation_items', '85');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('172', '2026_09_21_060000_create_tenant_custom_pages_and_menus_tables', '86');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('173', '2026_09_21_070000_add_contact_fields_to_platform_branding_table', '87');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('174', '2026_09_21_080000_create_tenant_document_templates_table', '88');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('175', '2026_09_21_000001_add_cash_register_alert_preference_to_companies', '89');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('176', '2026_09_21_000002_create_stores_and_store_stock', '89');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('177', '2026_09_22_193000_update_stores_table_isolation', '90');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('178', '2026_09_22_200000_add_total_amount_column_to_sales_table', '91');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('179', '2026_09_23_000001_create_licenses_table', '92');
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES ('180', '2026_09_23_130000_seed_core_builtin_modules_to_sdui_modules_table', '93');

-- -------------------------------------------------------------
-- 3. SaaS Subscription Packages
-- -------------------------------------------------------------
INSERT INTO `plans` (`name`, `display_name`, `billing_cycle`, `duration_days`, `price`, `currency`, `features`, `limits`, `invoice_limit`, `products_limit`, `device_limit`, `staff_limit`, `extensions`, `active`, `is_active`, `store_limit`, `created_at`, `updated_at`) VALUES ('professional', 'Professional', 'yearly', '365', '199.00', 'USD', '{"api_access": true, "quotations": true, "consignments": true, "customer_crm": true, "online_store": true, "cash_register": true, "multi_location": true, "restaurant_mode": true, "automatic_backup": true, "thermal_printing": true, "analytics_reports": true}', '{"filiais": 5, "invoices": -1, "products": -1, "usuarios": -1, "dispositivos": -1, "armazenamento_mb": 10240}', '-1', '-1', '-1', '-1', '["leadmanagement", "whatsapp_api", "custom_domain"]', '1', '1', '5', '2026-08-23 23:02:22', '2026-09-20 13:29:54');
INSERT INTO `plans` (`name`, `display_name`, `billing_cycle`, `duration_days`, `price`, `currency`, `features`, `limits`, `invoice_limit`, `products_limit`, `device_limit`, `staff_limit`, `extensions`, `active`, `is_active`, `store_limit`, `created_at`, `updated_at`) VALUES ('starter', 'Starter', 'monthly', '30', '19.00', 'USD', '{"api_access": true, "quotations": true, "consignments": true, "customer_crm": true, "online_store": true, "cash_register": true, "multi_location": false, "automatic_backup": false, "thermal_printing": true, "analytics_reports": true}', '{"filiais": 1, "invoices": 500, "products": 1000, "usuarios": 3, "dispositivos": 2, "armazenamento_mb": 2048}', '500', '1000', '2', '3', '["leadmanagement"]', '1', '1', '1', '2026-08-23 23:02:22', '2026-09-20 13:29:54');
INSERT INTO `plans` (`name`, `display_name`, `billing_cycle`, `duration_days`, `price`, `currency`, `features`, `limits`, `invoice_limit`, `products_limit`, `device_limit`, `staff_limit`, `extensions`, `active`, `is_active`, `store_limit`, `created_at`, `updated_at`) VALUES ('trial', 'Trial', 'trial', '14', '0.00', 'USD', '{"quotations": true, "online_store": true, "cash_register": true, "thermal_printing": true}', '{"filiais": 1, "invoices": 50, "products": 100, "usuarios": 2, "dispositivos": 1, "armazenamento_mb": 500}', '50', '100', '1', '2', '["leadmanagement"]', '1', '1', '1', '2026-08-23 23:02:22', '2026-09-20 13:29:54');

-- -------------------------------------------------------------
-- 4. Server-Driven UI (SDUI) Core & Extension Modules
-- -------------------------------------------------------------

INSERT INTO `sdui_modules` (`id`, `slug`, `name`, `type`, `description`, `icon`, `layout_type`, `is_active`, `source_type`, `version`, `author`, `sort_order`, `requires_license`, `license_status`, `created_at`, `updated_at`) VALUES
(1, 'retail', 'Retail POS', 'core', 'Core Retail POS vertical: barcode scanning, cart & billing, quotations, stock ledger.', 'storefront', 'standard_grid', 1, 'builtin', '1.0.4', 'ZoomNearby', 1, 0, 'active', NOW(), NOW()),
(2, 'restaurant', 'Cafe & Restaurant', 'core', 'Core Food & Restaurant vertical: dining table management, KOT printing, kitchen KDS display.', 'restaurant', 'table_floor_plan', 1, 'builtin', '1.0.4', 'ZoomNearby', 2, 0, 'active', NOW(), NOW()),
(3, 'leadmanagement', 'Lead Management System', 'extension', 'Standalone CRM lead management vertical: pipelines, follow-ups and conversions.', 'leaderboard', 'standard_grid', 0, 'package', '1.0.0', 'ZoomNearby', 3, 1, 'inactive', NOW(), NOW()),
(4, 'pharmacy', 'Pharmacy POS Module', 'core', 'Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake.', 'medication', 'standard_grid', 0, 'package', '1.0.0', 'ZoomNearby', 4, 1, 'inactive', NOW(), NOW()),
(5, 'repairtechnician', 'Repair Service Provider', 'core', 'Standalone repair vertical: device intake tickets, diagnosis, parts & labor.', 'build', 'standard_grid', 0, 'package', '1.0.0', 'ZoomNearby', 5, 1, 'inactive', NOW(), NOW()),
(6, 'salon', 'Salon & Bookings Module', 'core', 'Standalone salon vertical: service catalogue, stylists, appointment booking.', 'content_cut', 'standard_grid', 0, 'package', '1.0.0', 'ZoomNearby', 6, 1, 'inactive', NOW(), NOW());


-- -------------------------------------------------------------
-- 5. Platform Branding & Legal Pages
-- -------------------------------------------------------------

INSERT INTO `platform_branding` (`id`, `platform_name`, `platform_tagline`, `support_email`, `landing_page_enabled`, `landing_page_id`, `created_at`, `updated_at`) VALUES
(1, 'Zoom POS & Sales CRM', 'Complete Multi-Tenant POS & Business Engine', 'support@yourdomain.com', 1, 1, NOW(), NOW());

INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('terms-of-service', 'Terms of Service', '<p>Last updated: August 23, 2026</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>These Terms of Service ("Terms") govern access to and use of Smart Inventory &amp; Sales (the "Service"), operated by us. By creating an account or using the Service, you agree to these Terms.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>1. Using the Service</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>You must provide accurate registration information and keep your account credentials secure. You are responsible for all activity carried out under your account, including actions taken by staff accounts you create.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>2. Subscriptions &amp; Billing</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>Paid plans are billed in advance on a recurring basis (monthly or annually, as selected at checkout) until cancelled. Trial periods, where offered, convert to a paid subscription unless cancelled before the trial ends.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>3. Your Data</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>You retain ownership of the product, sales, customer, and financial data you enter into the Service. We process this data solely to provide and support the Service, as described in our Privacy Policy.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>4. Acceptable Use</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>You agree not to misuse the Service, attempt to disrupt its infrastructure, or use it to process unlawful transactions.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>5. Availability</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>We work to keep the Service available at all times and target the uptime commitment published on our pricing page, but the Service is provided "as is" without warranties of uninterrupted availability.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>6. Termination</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>You may cancel your subscription at any time from your account settings. We may suspend or terminate accounts that violate these Terms.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>7. Changes</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>We may update these Terms from time to time. Continued use of the Service after an update constitutes acceptance of the revised Terms.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>8. Contact</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>Questions about these Terms can be sent through our contact form.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>This page is a general-purpose starting template and does not constitute legal advice. Please have it reviewed by qualified counsel before relying on it for your business.</p>', 'Terms of Service for Zoom Sales CRM & Inventory.', '1', '1', '2026-08-23 23:02:22', '2026-09-23 14:15:05');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('privacy-policy', 'Privacy Policy', '<p>Last updated: August 23, 2026</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>This Privacy Policy explains how Smart Inventory &amp; Sales ("we", "us") collects, uses, and protects information when you use the Service.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>1. Information We Collect</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<ul>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<li>Account information you provide: name, business email, phone number, and store details.</li>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<li>Business data you enter: products, sales, customers, invoices, and related records.</li>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<li>Usage data: device, browser, and log information collected automatically to keep the Service secure and reliable.</li>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('</ul>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>2. How We Use Information</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>We use collected information to operate and improve the Service, process transactions, send service notifications, respond to support and contact requests, and maintain security.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>3. Sharing of Information</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>We do not sell your data. Information may be shared with service providers who help us operate the platform (such as email delivery and hosting providers), strictly to the extent necessary to provide the Service.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>4. Data Retention</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>Business data is retained for as long as your account is active. You may request export or deletion of your data by contacting us.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>5. Security</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>We use reasonable technical and organizational measures to protect data, including encrypted storage of sensitive credentials and access controls on staff accounts.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>6. Your Choices</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>You can review and update your account information at any time, and may request deletion of your account and associated data, subject to legal record-keeping requirements.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>7. Contact</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>For privacy questions or data requests, please reach out through our contact form.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>This page is a general-purpose starting template and does not constitute legal advice. Please have it reviewed by qualified counsel before relying on it for your business.</p>', 'Privacy Policy for Zoom Sales CRM & Inventory.', '1', '1', '2026-08-23 23:02:22', '2026-09-23 14:15:05');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('refund-policy', 'Refund & Cancellation Policy', '<p><em>Last updated: August 23, 2026</em></p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>This policy describes how cancellations and refunds are handled for Smart Inventory &amp; Sales subscriptions.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>1. Free Trial</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>New accounts start on a free trial. You will not be charged during the trial period, and you may cancel at any time before it ends at no cost.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>2. Cancelling a Subscription</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>You can cancel a paid subscription at any time from your account settings. Cancellation stops future billing; access continues until the end of the current billing period.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>3. Refunds</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>Subscription fees are generally non-refundable for the current billing period once charged. If you believe you were billed in error, contact us within 14 days of the charge and we will review the request in good faith.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>4. Downgrades &amp; Upgrades</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>Plan changes take effect according to the billing cycle in progress; any prorated adjustment will be reflected on your next invoice.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<h2>5. Contact</h2>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p>For billing questions, please reach out through our contact form and we\'ll be glad to help.</p>');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('<p><em>This page is a general-purpose starting template and does not constitute legal advice. Please have it reviewed by qualified counsel before relying on it for your business.</em></p>', 'Refund and cancellation policy for Zoom Sales CRM & Inventory.', '1', '1', '2026-08-23 23:02:22', '2026-09-23 14:15:05');
INSERT INTO `pages` (`slug`, `title`, `content`, `meta_description`, `is_active`, `show_in_footer`, `created_at`, `updated_at`) VALUES ('home', 'Home', '', NULL, '1', '0', '2026-08-24 18:13:31', '2026-08-24 18:13:52');

-- -------------------------------------------------------------
-- 6. System Defaults & Settings
-- -------------------------------------------------------------

INSERT INTO `platform_system` (`key`, `value`, `created_at`, `updated_at`) VALUES
('app_name', 'Zoom Sales CRM & Inventory', NOW(), NOW()),
('app_currency', 'USD', NOW(), NOW()),
('app_timezone', 'UTC', NOW(), NOW()),
('app_version', '1.0.4', NOW(), NOW()),
('platform_default_currency', 'USD', NOW(), NOW()),
('platform_default_timezone', 'UTC', NOW(), NOW()),
('platform_default_language', 'en', NOW(), NOW()),
('maintenance_mode', '0', NOW(), NOW()),
('maintenance_message', 'The platform is undergoing scheduled maintenance.', NOW(), NOW()),
('min_client_build_version', '0', NOW(), NOW()),
('otp_registration_enabled', '0', NOW(), NOW()),
('allowed_registration_modes', '["retail","restaurant"]', NOW(), NOW()),
('core_license_status', 'active', NOW(), NOW()),
('social_google_enabled', '0', NOW(), NOW()),
('social_facebook_enabled', '0', NOW(), NOW()),
('ai_image_enabled', '0', NOW(), NOW());


-- -------------------------------------------------------------
-- 7. Default Super Admin Account
-- Email: admin@zoompos.com
-- Password: admin1234
-- -------------------------------------------------------------

INSERT INTO `platform_admins` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
('padm_superadmin01', 'Super Admin', 'admin@zoompos.com', '$2y$12$VvNZ0f7F22mg2/P7ybNiU.eCz8SYqMCW8plrK.SHkgMt0s6MhhqBu', 'super_admin', 'active', NOW(), NOW()),
('padm_superadmin02', 'Demo SuperAdmin', 'superadmin@gmail.com', '$2y$12$rr0guja/gIvrgj3arWLyH.afw8n4vWbzHSvQTHfdskOSC8JFXufze', 'super_admin', 'active', NOW(), NOW());


-- -------------------------------------------------------------
-- 8. Sample Demo Store & Restaurant Data
-- Store Admin: store@demo.com / admin1234
-- Cashier:     cashier@demo.com / admin1234
-- -------------------------------------------------------------

-- Sample Company / Store
INSERT INTO `companies` (`id`, `name`, `slug`, `email`, `phone`, `operating_mode`, `status`, `currency`, `timezone`, `is_demo`, `created_at`, `updated_at`) VALUES
('emp_demo_retail_rest', 'Demo Retail & Cafe Store', 'demo-store', 'store@demo.com', '+1234567890', 'retail', 'active', 'USD', 'UTC', 1, NOW(), NOW());

INSERT INTO `stores` (`id`, `company_id`, `name`, `code`, `currency`, `timezone`, `is_main`, `status`, `created_at`, `updated_at`) VALUES
('str_demo_main', 'emp_demo_retail_rest', 'Main Branch & Counter', 'MB-01', 'USD', 'UTC', 1, 'active', NOW(), NOW());

-- Tenant Users (Password: admin1234)
INSERT INTO `users` (`id`, `company_id`, `name`, `login`, `email`, `password`, `role`, `status`, `is_demo`, `current_store_id`, `created_at`, `updated_at`) VALUES
('usr_demo_admin', 'emp_demo_retail_rest', 'Store Manager', 'store_admin', 'store@demo.com', '$2y$12$VvNZ0f7F22mg2/P7ybNiU.eCz8SYqMCW8plrK.SHkgMt0s6MhhqBu', 'administrator', 'active', 1, 'str_demo_main', NOW(), NOW()),
('usr_demo_cashier', 'emp_demo_retail_rest', 'Counter Cashier', 'cashier', 'cashier@demo.com', '$2y$12$VvNZ0f7F22mg2/P7ybNiU.eCz8SYqMCW8plrK.SHkgMt0s6MhhqBu', 'cashier', 'active', 1, 'str_demo_main', NOW(), NOW());

-- Sample Categories
INSERT INTO `categories` (`id`, `company_id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
('cat_demo_01', 'emp_demo_retail_rest', 'Retail & Groceries', 'retail-groceries', NOW(), NOW()),
('cat_demo_02', 'emp_demo_retail_rest', 'Hot Beverages & Coffee', 'hot-beverages', NOW(), NOW()),
('cat_demo_03', 'emp_demo_retail_rest', 'Bakery & Snacks', 'bakery-snacks', NOW(), NOW());

-- Sample Products
INSERT INTO `products` (`id`, `company_id`, `category_id`, `name`, `sku`, `barcode`, `price`, `cost_price`, `stock_quantity`, `is_active`, `created_at`, `updated_at`) VALUES
('prd_demo_01', 'emp_demo_retail_rest', 'cat_demo_01', 'Organic Olive Oil 500ml', 'OIL-500', '8901234567890', 12.50, 8.00, 45, 1, NOW(), NOW()),
('prd_demo_02', 'emp_demo_retail_rest', 'cat_demo_01', 'Whole Wheat Bread 400g', 'BREAD-400', '8901234567891', 3.20, 1.80, 20, 1, NOW(), NOW()),
('prd_demo_03', 'emp_demo_retail_rest', 'cat_demo_02', 'Espresso Single Shot', 'ESP-SGL', '8901234567892', 2.80, 0.60, 100, 1, NOW(), NOW()),
('prd_demo_04', 'emp_demo_retail_rest', 'cat_demo_02', 'Caffe Latte 12oz', 'LAT-12OZ', '8901234567893', 4.50, 1.10, 80, 1, NOW(), NOW()),
('prd_demo_05', 'emp_demo_retail_rest', 'cat_demo_03', 'Butter Croissant', 'CRST-BTR', '8901234567894', 3.50, 1.20, 30, 1, NOW(), NOW());

-- Sample Restaurant Tables
INSERT INTO `dining_floors` (`id`, `company_id`, `store_id`, `name`, `sort_order`, `created_at`, `updated_at`) VALUES
('flr_demo_01', 'emp_demo_retail_rest', 'str_demo_main', 'Main Dining Hall', 1, NOW(), NOW());

INSERT INTO `dining_tables` (`id`, `company_id`, `floor_id`, `table_number`, `capacity`, `status`, `created_at`, `updated_at`) VALUES
('tbl_demo_01', 'emp_demo_retail_rest', 'flr_demo_01', 'Table 1', 4, 'available', NOW(), NOW()),
('tbl_demo_02', 'emp_demo_retail_rest', 'flr_demo_01', 'Table 2', 2, 'available', NOW(), NOW()),
('tbl_demo_03', 'emp_demo_retail_rest', 'flr_demo_01', 'Table 3', 6, 'available', NOW(), NOW()),
('tbl_demo_04', 'emp_demo_retail_rest', 'flr_demo_01', 'Table 4', 4, 'available', NOW(), NOW());

-- Sample Payment Methods
INSERT INTO `payment_methods` (`id`, `company_id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES
('pm_demo_01', 'emp_demo_retail_rest', 'Cash', 'cash', 1, NOW(), NOW()),
('pm_demo_02', 'emp_demo_retail_rest', 'Credit / Debit Card', 'card', 1, NOW(), NOW()),
('pm_demo_03', 'emp_demo_retail_rest', 'QR / Digital Wallet', 'qr', 1, NOW(), NOW());


SET FOREIGN_KEY_CHECKS=1;

-- ==============================================================================
-- End of Database Schema
-- ==============================================================================
