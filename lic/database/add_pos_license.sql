-- ==============================================================================
-- Add POS Domain License for pos.zoomnearby.com
-- Database: u356050643_license_mngr (license.zoomnearby.com)
-- ==============================================================================

-- 1. Ensure required columns exist
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `registered_domain` VARCHAR(191) NULL;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `allowed_domains` JSON NULL;
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `license_type` VARCHAR(32) NOT NULL DEFAULT 'extended';
ALTER TABLE `licenses` ADD COLUMN IF NOT EXISTS `branding_json` JSON NULL;

-- 2. Insert or Activate Extended Core License for pos.zoomnearby.com
INSERT INTO `licenses` (
    `license_key`,
    `product_slug`,
    `client_email`,
    `bound_domain`,
    `registered_domain`,
    `plan`,
    `license_type`,
    `status`,
    `created_at`
) VALUES (
    'ZN-POS-7E8A9-4B3C2-EXTENDED',
    'core',
    'admin@zoomnearby.com',
    'pos.zoomnearby.com',
    'pos.zoomnearby.com',
    'extended',
    'extended',
    'active',
    NOW()
) ON DUPLICATE KEY UPDATE 
    `status` = 'active', 
    `plan` = 'extended',
    `license_type` = 'extended',
    `registered_domain` = 'pos.zoomnearby.com';
