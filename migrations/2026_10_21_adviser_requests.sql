-- Adviser requests. New tables and nullable columns only.
-- Does not update or delete existing rows.
-- MariaDB 10.4: ADD COLUMN IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS email_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient VARCHAR(200) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  reference_key VARCHAR(190) NOT NULL,
  period_key VARCHAR(40) NOT NULL,
  status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  error_text VARCHAR(500) NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_once (reference_key, period_key, recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  role VARCHAR(40) NULL,
  acting_as_admin TINYINT(1) NOT NULL DEFAULT 0,
  action VARCHAR(80) NOT NULL,
  module VARCHAR(40) NOT NULL,
  table_name VARCHAR(80) NULL,
  record_id BIGINT UNSIGNED NULL,
  old_values LONGTEXT NULL,
  new_values LONGTEXT NULL,
  reason VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_module (module, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS status_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  table_name VARCHAR(80) NOT NULL,
  record_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_record (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS adjustments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department VARCHAR(40) NOT NULL,
  period CHAR(7) NOT NULL,
  type VARCHAR(80) NOT NULL,
  reference_type VARCHAR(80) NULL,
  reference_id BIGINT UNSIGNED NULL,
  project_id BIGINT UNSIGNED NULL,
  amount_before DECIMAL(15,2) NULL,
  amount_after DECIMAL(15,2) NULL,
  qty_before DECIMAL(15,2) NULL,
  qty_after DECIMAL(15,2) NULL,
  difference DECIMAL(15,2) NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_by BIGINT UNSIGNED NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_adj_dept (department, period, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS month_closures (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department VARCHAR(40) NOT NULL,
  period CHAR(7) NOT NULL,
  closed_by BIGINT UNSIGNED NULL,
  closed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_month_close (department, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS invoice_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit VARCHAR(50) NULL,
  unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_invoice_items (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotent column adds. A second run selects 1 and does not change rows.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'archived_at') = 0, 'ALTER TABLE projects ADD COLUMN archived_at DATETIME NULL', 'SELECT 1');
PREPARE runeha_stmt FROM @sql; EXECUTE runeha_stmt; DEALLOCATE PREPARE runeha_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'tax') = 0, 'ALTER TABLE invoices ADD COLUMN tax DECIMAL(15,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE runeha_stmt FROM @sql; EXECUTE runeha_stmt; DEALLOCATE PREPARE runeha_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'description') = 0, 'ALTER TABLE invoices ADD COLUMN description TEXT NULL', 'SELECT 1');
PREPARE runeha_stmt FROM @sql; EXECUTE runeha_stmt; DEALLOCATE PREPARE runeha_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'billing_date') = 0, 'ALTER TABLE invoices ADD COLUMN billing_date DATE NULL', 'SELECT 1');
PREPARE runeha_stmt FROM @sql; EXECUTE runeha_stmt; DEALLOCATE PREPARE runeha_stmt;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'adjustments' AND COLUMN_NAME = 'source_period') = 0, 'ALTER TABLE adjustments ADD COLUMN source_period CHAR(7) NULL', 'SELECT 1');
PREPARE runeha_stmt FROM @sql; EXECUTE runeha_stmt; DEALLOCATE PREPARE runeha_stmt;
