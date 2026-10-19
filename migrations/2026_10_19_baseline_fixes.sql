-- Baseline fixes. Does not drop projects.actual_cost (already removed).
-- Does not delete duplicate projects. Does not make project dates NOT NULL.
-- Backup: backups/runeha_erp_before_2026_10_19_baseline.sql

ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS paid_by BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER paid_by,
  ADD COLUMN IF NOT EXISTS status_note VARCHAR(500) NULL AFTER paid_at;

-- NULL dates still pass. A real end date before the start date is rejected.
ALTER TABLE projects
  ADD CONSTRAINT chk_projects_dates CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date);
