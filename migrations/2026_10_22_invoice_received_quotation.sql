-- Link an Accounting invoice to a Procurement quotation.
-- invoices.quotation_id already belongs to client_quotations, so this uses
-- invoices.procurement_quotation_id. Existing quotation rows are not updated.
-- quotations.status keeps requested, received, accepted, and rejected, and gains invoiced.

SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND COLUMN_NAME = 'procurement_quotation_id'
);
SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE invoices ADD COLUMN procurement_quotation_id BIGINT UNSIGNED NULL AFTER quotation_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND INDEX_NAME = 'uq_invoices_procurement_quotation'
);
SET @sql := IF(
    @has_idx = 0,
    'ALTER TABLE invoices ADD UNIQUE KEY uq_invoices_procurement_quotation (procurement_quotation_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND CONSTRAINT_NAME = 'fk_invoices_procurement_quotation'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
    @has_fk = 0,
    'ALTER TABLE invoices ADD CONSTRAINT fk_invoices_procurement_quotation FOREIGN KEY (procurement_quotation_id) REFERENCES quotations (id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_invoiced := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quotations'
      AND COLUMN_NAME = 'status'
      AND COLUMN_TYPE LIKE '%invoiced%'
);
SET @sql := IF(
    @has_invoiced = 0,
    'ALTER TABLE quotations MODIFY status ENUM(''requested'',''received'',''accepted'',''rejected'',''invoiced'') NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
