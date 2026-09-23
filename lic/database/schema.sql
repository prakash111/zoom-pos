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

-- Upgrade path for installs created before packages existed. A "duplicate
-- column" error here on a fresh DB is expected and ignored by the runners.
ALTER TABLE `products` ADD COLUMN `package_uploaded_at` DATETIME NULL;
ALTER TABLE `licenses` ADD COLUMN `registered_domain` VARCHAR(191) NULL;
ALTER TABLE `licenses` ADD COLUMN `allowed_domains` JSON NULL;
ALTER TABLE `licenses` ADD COLUMN `license_type` VARCHAR(32) NOT NULL DEFAULT 'regular';
ALTER TABLE `licenses` ADD COLUMN `branding_json` JSON NULL;

INSERT INTO `settings` (`k`, `v`) VALUES
  ('validation_mode', 'native'),
  ('gateway', 'razorpay'),
  ('currency', 'USD')
ON DUPLICATE KEY UPDATE `k` = `k`;

INSERT INTO `products` (`slug`, `name`, `description`, `price`, `currency`) VALUES
  ('core',             'Core SaaS Platform',        'Multi-tenant POS & Business SaaS platform core platform engine.', 0.00, 'USD'),
  ('pharmacy',         'Pharmacy POS for SaaS',     'Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake and dispensing.', 10.00, 'USD'),
  ('salon',            'Salon Management System',   'Standalone salon vertical: service catalogue, stylists / specialists, appointment booking and lifecycle.', 10.00, 'USD'),
  ('repairtechnician', 'Repair Service Provider',   'Standalone repair vertical: device intake tickets, diagnostic checklist, parts & labor, lifecycle status and pickup.', 10.00, 'USD')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);