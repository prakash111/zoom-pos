ALTER TABLE `payments` ADD COLUMN `order_status` VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE `payments` ADD COLUMN `order_token` VARCHAR(48) NULL UNIQUE;
ALTER TABLE `payments` ADD COLUMN `target_domain` VARCHAR(191) NULL;
ALTER TABLE `payments` ADD COLUMN `checkout_json` JSON NULL;
ALTER TABLE `payments` ADD COLUMN `gateway_reference` VARCHAR(191) NULL UNIQUE;
ALTER TABLE `payments` ADD COLUMN `gateway_url` TEXT NULL;
ALTER TABLE `payments` ADD COLUMN `order_error` TEXT NULL;
ALTER TABLE `payments` ADD COLUMN `builder_email_sent` TINYINT(1) NOT NULL DEFAULT 0;

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
