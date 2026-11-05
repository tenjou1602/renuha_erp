-- Idempotent schema for 2026-10-26: completion date, fund requests, Excel procurement.
-- Does not update or delete existing rows.

ALTER TABLE projects ADD COLUMN IF NOT EXISTS completion_date DATE NULL AFTER end_date;

CREATE TABLE IF NOT EXISTS fund_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_number VARCHAR(50) NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    purpose VARCHAR(500) NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    requested_by BIGINT UNSIGNED NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fund_requests_number (request_number),
    KEY idx_fund_requests_project (project_id),
    KEY idx_fund_requests_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS procurement_boq (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_request_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    version_no INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_boq_request_version (purchase_request_id, version_no),
    KEY idx_boq_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS procurement_boq_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    boq_id BIGINT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL,
    material_code VARCHAR(50) NOT NULL,
    description VARCHAR(500) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    material_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_boq_lines_boq (boq_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_price_uploads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_request_id BIGINT UNSIGNED NOT NULL,
    boq_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    quotation_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_spu_request_supplier (purchase_request_id, supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_price_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    upload_id BIGINT UNSIGNED NOT NULL,
    boq_line_id BIGINT UNSIGNED NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_spl_upload (upload_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS procurement_supplier_choice (
    purchase_request_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    upload_id BIGINT UNSIGNED NOT NULL,
    chosen_by BIGINT UNSIGNED NULL,
    chosen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (purchase_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
