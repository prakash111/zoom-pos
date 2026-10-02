CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(64) PRIMARY KEY,
  `v` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `custom_features` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `package_uploaded_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_lookup` (`license_key`, `product_slug`),
  INDEX `idx_product` (`product_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference` VARCHAR(191) NOT NULL UNIQUE,
  `gateway` VARCHAR(40) NOT NULL DEFAULT '',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD',
  `product_slug` VARCHAR(64) NOT NULL,
  `license_id` INT NULL,
  `email` VARCHAR(191) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'paid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_license` (`license_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bundles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `included_modules` JSON NOT NULL,
  `custom_features` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade path for columns
ALTER TABLE `products` ADD COLUMN `package_uploaded_at` DATETIME NULL;
ALTER TABLE `products` ADD COLUMN `custom_features` JSON NULL;
ALTER TABLE `products` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 3;
ALTER TABLE `bundles` ADD COLUMN `custom_features` JSON NULL;
ALTER TABLE `bundles` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 30;
ALTER TABLE `licenses` ADD COLUMN `registered_domain` VARCHAR(191) NULL;
ALTER TABLE `licenses` ADD COLUMN `allowed_domains` JSON NULL;
ALTER TABLE `licenses` ADD COLUMN `license_type` VARCHAR(32) NOT NULL DEFAULT 'regular';
ALTER TABLE `licenses` ADD COLUMN `branding_json` JSON NULL;
ALTER TABLE `licenses` ADD COLUMN `payment_reference` VARCHAR(191) NULL;
ALTER TABLE `licenses` ADD COLUMN `app_builder_monthly_limit` INT NULL DEFAULT NULL;
ALTER TABLE `licenses` ADD COLUMN `bundle_id` INT NULL DEFAULT NULL;
ALTER TABLE `payments` ADD COLUMN `bundle_slug` VARCHAR(64) NULL;
ALTER TABLE `payments` ADD COLUMN `items_json` JSON NULL;

INSERT INTO `settings` (`k`, `v`) VALUES
  ('validation_mode', 'native'),
  ('gateway', 'razorpay'),
  ('currency', 'USD'),
  ('mail_driver', 'mail'),
  ('mail_from_address', 'licenses@zoomnearby.com'),
  ('mail_from_name', 'ZoomNearby Licensing'),
  ('builder_default_monthly_limit', '3'),
  ('builder_plan_limits', '{"trial":1,"free":1,"starter":3,"core":3,"regular":3,"basic":3,"business":30,"pro":30,"professional":30,"all-in-one":30,"extended":30,"enterprise":30,"unlimited":-1}')
ON DUPLICATE KEY UPDATE `k` = `k`;

INSERT INTO `products` (`slug`, `name`, `description`, `price`, `currency`, `app_builder_limit`, `is_active`) VALUES
  ('core',             'Core SaaS Platform (Retail, Restaurant & Café)', 'Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café / Quick-Service modules.', 49.00, 'USD', 3, 1),
  ('leadmanagement',   'Lead Management System',    'Lead tracking, CRM pipeline, follow-ups, and sales conversion module.', 29.00, 'USD', 0, 1),
  ('pharmacy',         'Pharmacy POS for SaaS',     'Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake and dispensing.', 25.00, 'USD', 0, 1),
  ('salon',            'Salon Management System',   'Standalone salon vertical: service catalogue, stylists / specialists, appointment booking and lifecycle.', 25.00, 'USD', 0, 1),
  ('repairtechnician', 'Repair Service Provider',   'Standalone repair vertical: device intake tickets, diagnostic checklist, parts & labor, lifecycle status and pickup.', 25.00, 'USD', 0, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `price` = VALUES(`price`), `app_builder_limit` = VALUES(`app_builder_limit`);

INSERT INTO `bundles` (`slug`, `name`, `description`, `price`, `currency`, `included_modules`, `app_builder_limit`, `is_active`) VALUES
  ('core-lead', 'Main Script + Lead Manager Bundle', 'Main SaaS Core Script bundled with the Lead Management & CRM module.', 69.00, 'USD', '["core", "leadmanagement"]', 15, 0),
  ('all-in-one', 'All-in-One Enterprise Bundle', 'Includes Main Core SaaS script plus all business vertical modules: Lead Manager, Pharmacy POS, Salon Management, and Repair Technician.', 119.00, 'USD', '["core", "leadmanagement", "pharmacy", "salon", "repairtechnician"]', 30, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `price` = VALUES(`price`), `included_modules` = VALUES(`included_modules`), `app_builder_limit` = VALUES(`app_builder_limit`), `is_active` = VALUES(`is_active`);
ALTER TABLE `payments` ADD COLUMN `order_status` VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE `payments` ADD COLUMN `order_token` VARCHAR(48) NULL UNIQUE;
ALTER TABLE `payments` ADD COLUMN `target_domain` VARCHAR(191) NULL;
ALTER TABLE `payments` ADD COLUMN `checkout_json` JSON NULL;
ALTER TABLE `payments` ADD COLUMN `gateway_reference` VARCHAR(191) NULL UNIQUE;
ALTER TABLE `payments` ADD COLUMN `gateway_url` TEXT NULL;
ALTER TABLE `payments` ADD COLUMN `order_error` TEXT NULL;
ALTER TABLE `payments` ADD COLUMN `builder_email_sent` TINYINT(1) NOT NULL DEFAULT 0;
