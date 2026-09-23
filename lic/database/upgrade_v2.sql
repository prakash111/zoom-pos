-- One-click upgrade script for license.zoomnearby.com database:
-- Run this in phpMyAdmin or MySQL CLI if your licenses table already exists:

ALTER TABLE `licenses` ADD COLUMN `registered_domain` VARCHAR(191) NULL;
ALTER TABLE `licenses` ADD COLUMN `allowed_domains` JSON NULL;
ALTER TABLE `licenses` ADD COLUMN `license_type` VARCHAR(32) NOT NULL DEFAULT 'regular';
ALTER TABLE `licenses` ADD COLUMN `branding_json` JSON NULL;
