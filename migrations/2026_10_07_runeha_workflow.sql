-- Runeha ERP workflow migration
-- Backup: backups/runeha_erp_before_2026_10_07.sql
-- Apply once against database runeha_erp.

-- STEP 1: Estimated Budget replaces material_cost and labor_cost.
-- Already applied on this database. Safe to skip if the columns are gone.
-- ALTER TABLE `projects` DROP COLUMN `material_cost`, DROP COLUMN `labor_cost`;

-- STEP 2: Blank dates are NULL, and the end date cannot be before the start date.
UPDATE `projects` SET `start_date` = NULL WHERE `start_date` = '0000-00-00';
UPDATE `projects` SET `end_date` = NULL WHERE `end_date` = '0000-00-00';
UPDATE `projects` SET `end_date` = NULL WHERE `start_date` IS NOT NULL AND `end_date` IS NOT NULL AND `end_date` < `start_date`;
ALTER TABLE `projects`
  ADD CONSTRAINT `chk_projects_dates` CHECK (`end_date` IS NULL OR `start_date` IS NULL OR `end_date` >= `start_date`);

-- STEP 3: Accounting reads the same projects row. New projects start as pending.
ALTER TABLE `projects`
  ADD COLUMN `accounting_status` ENUM('pending','received','quoted','rejected') NOT NULL DEFAULT 'pending' AFTER `accounting_received_at`;
UPDATE `projects` SET `accounting_status` = 'received' WHERE `accounting_received_at` IS NOT NULL;

-- STEP 6: Warehouse release record for a material request.
CREATE TABLE IF NOT EXISTS `warehouse_releases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `material_request_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `engineer_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL,
  `status` ENUM('released','received') NOT NULL DEFAULT 'released',
  `released_by` BIGINT UNSIGNED NULL,
  `received_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wr_request` (`material_request_id`),
  KEY `idx_wr_project` (`project_id`),
  KEY `idx_wr_engineer` (`engineer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `material_requests`
  ADD COLUMN `flow_status` ENUM('procurement','accounting','warehouse','released','complete') NOT NULL DEFAULT 'procurement' AFTER `status`;
UPDATE `material_requests` SET `flow_status` = 'released' WHERE `status` IN ('approved', 'partial');
UPDATE `material_requests` SET `flow_status` = 'complete' WHERE `status` = 'rejected';
