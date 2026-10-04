-- Client quotations are proposals. Accounting entries start only when an approved
-- quotation is converted into an issued invoice. Payments are cash only. No VAT.

CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(20) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `account_type` ENUM('asset','income') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coa_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entry_date` DATE NOT NULL,
  `reference_type` ENUM('invoice','payment') NOT NULL,
  `reference_id` BIGINT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journal_ref` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_jl_entry` (`journal_entry_id`),
  KEY `idx_jl_account` (`account_id`),
  KEY `idx_jl_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_quotations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quotation_number` VARCHAR(50) NOT NULL,
  `client_name` VARCHAR(200) NOT NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `scope_of_work` TEXT NULL,
  `validity_date` DATE NULL,
  `status` ENUM('draft','sent','approved','rejected','converted') NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_quotation_number` (`quotation_number`),
  KEY `idx_cq_project` (`project_id`),
  KEY `idx_cq_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_quotation_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `quotation_id` BIGINT UNSIGNED NOT NULL,
  `line_type` ENUM('material','labor','other') NOT NULL DEFAULT 'other',
  `material_id` BIGINT UNSIGNED NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `unit` VARCHAR(50) NULL,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_cqi_quotation` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` BIGINT UNSIGNED NOT NULL,
  `line_type` ENUM('material','labor','other') NOT NULL DEFAULT 'other',
  `material_id` BIGINT UNSIGNED NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `unit` VARCHAR(50) NULL,
  `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ii_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chart_of_accounts` (`code`, `name`, `account_type`)
SELECT '1000', 'Cash', 'asset' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE `code` = '1000');

INSERT INTO `chart_of_accounts` (`code`, `name`, `account_type`)
SELECT '1200', 'Accounts Receivable', 'asset' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE `code` = '1200');

INSERT INTO `chart_of_accounts` (`code`, `name`, `account_type`)
SELECT '4000', 'Sales/Service Revenue', 'income' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `chart_of_accounts` WHERE `code` = '4000');
