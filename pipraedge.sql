-- PipraEdge extra schema — addons from bangla-qr & phone-verify forks.
-- Run AFTER the base piprapay pp-install/db.sql.
-- Only the columns/tables these two mods add on top of base.
-- Safe to re-run: CREATE TABLE is IF NOT EXISTS and the ALTER is guarded.

-- ---- phone-verify: brand-level auto verification type selector ----
SET @db = DATABASE();
SET @has_col = (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pp_brands' AND COLUMN_NAME = 'auto_verify_type');
SET @sql = IF(@has_col = 0,
  'ALTER TABLE `pp_brands` ADD COLUMN `auto_verify_type` enum(\'trxid\',\'phone\') NOT NULL DEFAULT \'trxid\' AFTER `payment_tolerance`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---- bangla-qr: per-gateway unique-amount slot table ----
CREATE TABLE IF NOT EXISTS `pp_gateways_data` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `gateway_id` VARCHAR(50) NOT NULL,
  `ref` VARCHAR(100) NOT NULL,
  `unique_amount` DECIMAL(10,2) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'pending',
  `created_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gateway_id` (`gateway_id`),
  KEY `idx_ref` (`ref`),
  KEY `idx_unique_amount` (`unique_amount`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `uniq_gw_amount` (`gateway_id`, `unique_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
