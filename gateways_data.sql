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
