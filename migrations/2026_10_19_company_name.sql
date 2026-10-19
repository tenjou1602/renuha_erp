-- Company name is nullable until the existing suppliers are filled in by hand.
-- Do not copy name or contact_person into company_name.
-- UNIQUE and NOT NULL are intentionally not added in this migration.

ALTER TABLE suppliers
    ADD COLUMN IF NOT EXISTS company_name VARCHAR(150) NULL AFTER name;
