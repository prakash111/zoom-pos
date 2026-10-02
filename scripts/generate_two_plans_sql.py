import json

core_features = [
  "✨ CLOUD APP BUILDER (WHITE-LABEL)",
  "Cloud App Builder: 3 Cloud Builds / Month included",
  "Android Mobile App: Ready-to-install branded .apk",
  "Flutter Web POS: Browser-based responsive POS terminal",
  "Windows Desktop App: Native 64-bit Windows .exe installer",
  "White-Label Customization: Your own app name, custom logo & brand colors",
  "Automated CI/CD Pipeline: GitHub Actions automated cloud compile",
  "✨ CORE POS & MULTI-STORE ENGINE",
  "Retail POS Module: Barcode scanning, product variants & stock management",
  "Restaurant & Café Mode: Dining floorplans, tables & KOT kitchen tickets",
  "Kitchen Display System (KDS): Live order status & cooking dispatch alerts",
  "Offline-First SQLite Architecture: Full POS operation without internet connection",
  "Automatic Background Sync: Zero data loss synchronization when reconnected",
  "ESC/POS Thermal Printing: 80mm & 58mm via USB, Bluetooth BLE, Ethernet LAN & Raw 9100",
  "Cash Register Float & Closing: Shift management, cash-in/out & automated day-end Z-Report",
  "Barcode & Label Designer: Code128, EAN13 & price tag barcode generation",
  "Multi-Store & Central Warehousing: Unlimited stores, registers, cashiers & stock transfers",
  "Customer Public Web Storefront: Online ordering catalog & table QR digital menu",
  "Dual Tax Engines: GST, VAT, compound tax & split tender payments",
  "Expense & Ledger Tracking: Petty cash expenses & customer account credit ledgers",
  "✨ OMNICHANNEL NOTIFICATIONS & APIS",
  "Multi-Channel Messaging: WhatsApp, SMS & Email notification integration",
  "Generic SMS HTTP Gateway: Integrated with ZoomNearby SMS Gateway (sms.zoomnearby.com)",
  "Automated Scheduled Cron: Invoices, payment receipts & order reminders via WhatsApp/SMS",
  "✨ MULTI-TENANT SAAS INFRASTRUCTURE",
  "Multi-Tenant Architecture: Complete tenant data isolation & business management",
  "SuperAdmin Control Panel: Full subscription packages, tenant management & revenue KPIs",
  "Custom Domain Mapping: Every tenant store runs on their own branded domain",
  "Payment Gateways: Stripe, Razorpay, PayPal & Manual Wire Transfer",
  "100% Unencrypted Full PHP Source Code & Flutter client code included",
  "Lifetime Perpetual License: One-time payment with zero monthly recurring SaaS fees",
  "Included Modules",
  "Retail POS Engine",
  "Restaurant & Dine-in Module",
  "Café & Quick Service POS",
  "🔒 Lead Management / CRM Module (Included in $119 Business Plan)",
  "🔒 Pharmacy POS with Drug Batch & Expiry (Included in $119 Business Plan)",
  "🔒 Salon & Spa System with Stylists (Included in $119 Business Plan)",
  "🔒 Repair Service Management (Included in $119 Business Plan)",
  "✨ ENTERPRISE SUPPORT",
  "Standard Documentation & Setup Installation Guide",
  "🔒 30 Builds / Month Quota (Upgrade to $119 Business Plan)",
  "🔒 VIP Deployment Support (Included in $119 Business Plan)",
  "🔒 Server Setup Assistance (Included in $119 Business Plan)",
  "🔒 Priority Troubleshooting (Included in $119 Business Plan)"
]

bundle_features = [
  "✨ CLOUD APP BUILDER (WHITE-LABEL - 10X QUOTA)",
  "Cloud App Builder: 30 Cloud Builds / Month included (10x Quota)",
  "Google Play Ready AAB: Production Android App Bundle (.aab)",
  "Ready-to-Install Android APK: Branded release .apk for direct distribution",
  "Flutter Web POS: Optimized production Flutter Web build",
  "Windows Desktop EXE: Native 64-bit installer with silent auto-updates",
  "Full White-Label Branding: Custom app name, package ID, splash screen & logos",
  "Build History & Alert Engine: Dashboard build tracking & automated email alerts",
  "Automated CI/CD Pipeline: GitHub Actions automated cloud compile",
  "✨ EVERYTHING IN CORE PLATFORM INCLUDED",
  "Retail POS, Restaurant (Tables & KOT) & Café Quick-Service Modules",
  "Kitchen Display System (KDS): Live kitchen tablet status alerts",
  "Offline-First SQLite Architecture: Full POS operation without internet & auto sync",
  "ESC/POS Direct Thermal Printing: 80mm/58mm via USB, Bluetooth, LAN & Network 9100",
  "Cash Register Float & Closing: Shift management, cash-in/out & automated Z-Report",
  "Barcode & Label Designer: Code128, EAN13 & price tag barcode generation",
  "Multi-Store Outlets & Central Warehouses with Inter-Branch Transfers",
  "Customer Public Web Storefront & Table QR Digital Menu Ordering System",
  "Dual Tax Engines (GST / VAT / Sales Tax) & Multi-Currency Settlement",
  "Expense & Ledger Tracking: Petty cash expenses & customer account credit ledgers",
  "✨ OMNICHANNEL WHATSAPP & SMS ENGINE",
  "WhatsApp Cloud API & Twilio Integration: Instant receipts & booking alerts",
  "Generic SMS HTTP Gateway: Integrated with ZoomNearby SMS Gateway (sms.zoomnearby.com)",
  "Automated Scheduled Cron: Invoices, payment receipts & order reminders via WhatsApp/SMS",
  "✨ MULTI-TENANT SAAS INFRASTRUCTURE",
  "Multi-Tenant Architecture: Complete tenant data isolation & business management",
  "SuperAdmin Control Panel: Full subscription packages, tenant management & revenue KPIs",
  "Custom Domain Mapping: Every tenant store runs on their own branded domain",
  "Payment Gateways: Stripe, Razorpay, PayPal & Manual Wire Transfer",
  "100% Unencrypted Full PHP Source Code & Flutter client code included",
  "Lifetime Perpetual License: One-time payment with zero monthly recurring SaaS fees",
  "Included Modules",
  "Retail POS Engine",
  "Restaurant & Dine-in Module",
  "Café & Quick Service POS",
  "Lead Management CRM: Visual Kanban pipeline, deal stages, follow-ups & quotations",
  "Pharmacy POS Module: Drug batch / lot tracking, prescription intake & expiry alarms",
  "Salon & Spa System: Service catalog, stylist commissions & online appointment slots",
  "Repair Service Workbench: Device intake tickets, diagnosis, parts & labor billing",
  "✨ ENTERPRISE SUPPORT",
  "VIP Deployment Support: Assisted server installation & configuration",
  "Server Setup Assistance: Database tuning, SSL setup & queue configuration",
  "Priority Developer Troubleshooting & Direct Email / WhatsApp Support",
  "White-Label Deployment Assistance for Play Store & Server Hosting"
]

core_json = json.dumps(core_features, ensure_ascii=False).replace("'", "''")
bundle_json = json.dumps(bundle_features, ensure_ascii=False).replace("'", "''")

sql_content = f"""-- ==============================================================================
-- ZoomNearby License Manager — 2 Plans Pricing & App Builder Limits Migration
-- Run this SQL in phpMyAdmin on your License Manager database (u356050643_license_mngr)
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Ensure required columns exist in products, bundles, and licenses
-- ------------------------------------------------------------------------------
SET @dbname = DATABASE();

SET @tablename = "products";
SET @columnname = "custom_features";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` JSON NULL;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "app_builder_limit";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` INT NOT NULL DEFAULT 3;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @tablename = "bundles";
SET @columnname = "custom_features";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` JSON NULL;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "app_builder_limit";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` INT NOT NULL DEFAULT 30;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @tablename = "licenses";
SET @columnname = "app_builder_monthly_limit";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` INT NULL DEFAULT NULL;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ------------------------------------------------------------------------------
-- 2. Configure Plan 1: Core SaaS Platform (Starter) — $49 (Build Limit: 3 / Month)
-- ------------------------------------------------------------------------------
INSERT INTO `products` (`slug`, `name`, `description`, `price`, `currency`, `app_builder_limit`, `is_active`, `custom_features`)
VALUES (
  'core',
  'Core SaaS Platform (Retail, Restaurant & Café)',
  'Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café / Quick-Service modules.',
  49.00,
  'USD',
  3,
  1,
  '{core_json}'
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `price` = 49.00,
  `currency` = 'USD',
  `app_builder_limit` = 3,
  `is_active` = 1,
  `custom_features` = VALUES(`custom_features`);

-- ------------------------------------------------------------------------------
-- 3. Configure Plan 2: All-in-One Enterprise Bundle (Business) — $119 (Build Limit: 30 / Month)
-- ------------------------------------------------------------------------------
INSERT INTO `bundles` (`slug`, `name`, `description`, `price`, `currency`, `included_modules`, `app_builder_limit`, `is_active`, `custom_features`)
VALUES (
  'all-in-one',
  'All-in-One Enterprise Bundle',
  'Includes Main Core SaaS script plus all business vertical modules: Lead Manager, Pharmacy POS, Salon Management, and Repair Technician.',
  119.00,
  'USD',
  '["core", "leadmanagement", "pharmacy", "salon", "repairtechnician"]',
  30,
  1,
  '{bundle_json}'
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `price` = 119.00,
  `currency` = 'USD',
  `included_modules` = VALUES(`included_modules`),
  `app_builder_limit` = 30,
  `is_active` = 1,
  `custom_features` = VALUES(`custom_features`);

-- ------------------------------------------------------------------------------
-- 4. Deactivate All Other Bundles (So ONLY 2 Plans Are Shown on pos.zoomnearby.com/marketing/#pricing)
-- ------------------------------------------------------------------------------
UPDATE `bundles` 
SET `is_active` = 0 
WHERE `slug` != 'all-in-one';

-- ------------------------------------------------------------------------------
-- 5. Update App Builder Quotas & Plan Limits in settings Table
-- ------------------------------------------------------------------------------
INSERT INTO `settings` (`k`, `v`) VALUES
  ('builder_default_monthly_limit', '3'),
  ('builder_plan_limits', '{{"trial":1,"free":1,"starter":3,"core":3,"regular":3,"basic":3,"business":30,"pro":30,"professional":30,"all-in-one":30,"extended":30,"enterprise":30,"unlimited":-1}}')
ON DUPLICATE KEY UPDATE `v` = VALUES(`v`);

-- ------------------------------------------------------------------------------
-- 6. Synchronize Existing Active Licenses with the New Build Limits
-- ------------------------------------------------------------------------------
-- Core standalone licenses -> 3 builds / month
UPDATE `licenses`
SET `app_builder_monthly_limit` = 3
WHERE `product_slug` = 'core' AND (`bundle_id` IS NULL OR `bundle_id` = 0);

-- All-in-One bundle licenses -> 30 builds / month
UPDATE `licenses`
SET `app_builder_monthly_limit` = 30
WHERE `bundle_id` IN (SELECT `id` FROM `bundles` WHERE `slug` = 'all-in-one');

-- Remove limits from non-core module add-on licenses
UPDATE `licenses`
SET `app_builder_monthly_limit` = NULL
WHERE `product_slug` NOT IN ('core', 'main', 'pos', 'zoom-pos') AND `app_builder_monthly_limit` IS NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
"""

with open("lic/database/update_two_plans_pricing.sql", "w", encoding="utf-8") as f:
    f.write(sql_content)

with open("public/update_two_plans_pricing.sql", "w", encoding="utf-8") as f:
    f.write(sql_content)

print("Generated update_two_plans_pricing.sql successfully!")
