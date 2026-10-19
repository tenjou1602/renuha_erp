-- Marks a procurement supplier quotation as sent to Accounting.
-- Existing rows stay NULL until Procurement clicks Submit to Accounting.

ALTER TABLE quotations
    ADD COLUMN IF NOT EXISTS submitted_to_accounting_at DATETIME NULL AFTER notes;
