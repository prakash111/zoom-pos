CREATE TABLE IF NOT EXISTS `app_builds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `build_uid` VARCHAR(64) NOT NULL UNIQUE,
  `batch_id` VARCHAR(64) NULL,
  `license_key` VARCHAR(64) NOT NULL,
  `client_email` VARCHAR(191) NOT NULL,
  `platform` ENUM('android', 'web', 'windows', 'ios') NOT NULL,
  `source_type` ENUM('latest_github', 'uploaded_zip') NOT NULL DEFAULT 'latest_github',
  `app_name` VARCHAR(191) NOT NULL DEFAULT 'Zoom Sales POS',
  `package_id` VARCHAR(191) NOT NULL DEFAULT 'com.zoomnearby.zoompos',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `products` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 10;
ALTER TABLE `bundles` ADD COLUMN `app_builder_limit` INT NOT NULL DEFAULT 20;
ALTER TABLE `licenses` ADD COLUMN `app_builder_monthly_limit` INT NULL DEFAULT NULL;
ALTER TABLE `app_builds` ADD COLUMN `license_id` INT NULL AFTER `license_key`;
ALTER TABLE `app_builds` ADD COLUMN `order_id` INT NULL AFTER `license_id`;
ALTER TABLE `app_builds` ADD COLUMN `order_reference` VARCHAR(191) NULL AFTER `order_id`;
ALTER TABLE `app_builds` ADD COLUMN `build_version` VARCHAR(64) NOT NULL DEFAULT '1.0.0' AFTER `package_id`;
ALTER TABLE `app_builds` ADD COLUMN `branding_json` JSON NULL;
ALTER TABLE `app_builds` ADD COLUMN `batch_id` VARCHAR(64) NULL;
ALTER TABLE `payments` ADD COLUMN `builder_email_sent` TINYINT(1) NOT NULL DEFAULT 0;

-- Default Settings for App Builder
INSERT INTO `settings` (`k`, `v`) VALUES
  ('github_repo', 'prakash111/zoom-pos'),
  ('github_branch', 'feat/windows-offline-sync'),
  ('github_token', 'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN'),
  ('builder_default_monthly_limit', '10'),
  ('builder_plan_limits', '{"trial":2,"free":2,"basic":10,"starter":10,"regular":10,"pro":30,"professional":30,"extended":-1,"enterprise":-1,"unlimited":-1}')
ON DUPLICATE KEY UPDATE `k` = `k`;

-- Build limit applies exclusively to Core script (clear from modules/extensions)
UPDATE `licenses` SET `app_builder_monthly_limit` = NULL WHERE `product_slug` NOT IN ('core', 'main', 'pos', 'zoom-pos');

