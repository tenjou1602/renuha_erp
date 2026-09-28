-- Assign a Project In-Charge on projects.assigned_to
-- Safe to re-run (checks information_schema).

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'projects'
    AND COLUMN_NAME = 'assigned_to'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `projects` ADD COLUMN `assigned_to` BIGINT UNSIGNED NULL AFTER `created_by`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'projects'
    AND INDEX_NAME = 'idx_projects_assigned_to'
);

SET @sql := IF(
  @idx_exists = 0,
  'ALTER TABLE `projects` ADD KEY `idx_projects_assigned_to` (`assigned_to`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
