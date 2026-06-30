-- =====================================================
-- TradeZenfy - Live Data Schema Update (v3)
-- Run ONE STATEMENT AT A TIME in phpMyAdmin
-- Ignore "Duplicate column" and "Duplicate key" errors
-- =====================================================

-- STEP 1: Add missing columns to stocks table
-- Run each of these ONE BY ONE (ignore errors if column already exists)

ALTER TABLE `stocks` ADD COLUMN `open_price` DECIMAL(12,2) DEFAULT 0.00 AFTER `previous_close`;

ALTER TABLE `stocks` ADD COLUMN `high_price` DECIMAL(12,2) DEFAULT 0.00 AFTER `open_price`;

ALTER TABLE `stocks` ADD COLUMN `low_price` DECIMAL(12,2) DEFAULT 0.00 AFTER `high_price`;

ALTER TABLE `stocks` ADD COLUMN `volume` BIGINT(20) DEFAULT 0 AFTER `low_price`;

-- STEP 2: Recreate stock_price_cache with correct schema

DROP TABLE IF EXISTS `stock_price_cache`;

CREATE TABLE `stock_price_cache` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `stock_id` INT(11) NOT NULL,
  `ltp` DECIMAL(12,2) DEFAULT 0.00,
  `open_price` DECIMAL(12,2) DEFAULT 0.00,
  `high_price` DECIMAL(12,2) DEFAULT 0.00,
  `low_price` DECIMAL(12,2) DEFAULT 0.00,
  `close_price` DECIMAL(12,2) DEFAULT 0.00,
  `volume` BIGINT(20) DEFAULT 0,
  `change_percent` DECIMAL(8,2) DEFAULT 0.00,
  `is_live` TINYINT(1) DEFAULT 0,
  `source` VARCHAR(50) DEFAULT 'database',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_stock_id` (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STEP 3: Ensure data_provider_preferences has correct values (skip if already done)

INSERT INTO `data_provider_preferences` (`asset_type`, `provider`, `is_enabled`, `priority`, `created_at`, `updated_at`) VALUES
('indices', 'angel_one', 1, 1, NOW(), NOW()),
('stocks', 'angel_one', 1, 1, NOW(), NOW()),
('commodities', 'angel_one', 1, 1, NOW(), NOW()),
('crypto', 'yahoo_finance', 1, 1, NOW(), NOW()),
('forex', 'yahoo_finance', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `provider` = VALUES(`provider`), `is_enabled` = VALUES(`is_enabled`), `updated_at` = NOW();

-- STEP 4: Clean up duplicate index entries
-- Deactivate any index entries that don't use ^ symbol format (duplicates)

UPDATE `stocks` SET `is_active` = 0
WHERE `sector` = 'Index'
AND `symbol` NOT LIKE '^%';

-- STEP 5: Verify everything looks correct

SELECT id, symbol, name, ltp, is_active FROM stocks WHERE sector = 'Index';

SELECT * FROM data_provider_preferences;

SELECT id, provider, api_key, client_id, LEFT(totp_secret,6) as totp_preview, is_active FROM api_settings WHERE provider = 'angel_one';
