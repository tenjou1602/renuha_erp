-- invoices.quotation_id already has UNIQUE KEY uq_invoices_quotation.
-- That column stores a client_quotations id, so Procurement uses
-- invoices.procurement_quotation_id. Create that unique key if it is missing.

SET @has_proc_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND INDEX_NAME = 'uq_invoices_procurement_quotation'
);
SET @sql := IF(
    @has_proc_idx = 0,
    'ALTER TABLE invoices ADD UNIQUE KEY uq_invoices_procurement_quotation (procurement_quotation_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Draft invoices for Procurement quotations already marked received.
-- invoices.quotation_id is already UNIQUE (uq_invoices_quotation) and stores
-- a client_quotations id. Procurement quotations use procurement_quotation_id,
-- which already has UNIQUE key uq_invoices_procurement_quotation.
-- This statement does not change existing quotation or invoice rows.
-- It inserts a draft only when status is received and no invoice is linked.

INSERT INTO invoices (
    invoice_number,
    project_id,
    quotation_id,
    procurement_quotation_id,
    client_id,
    client,
    client_name,
    invoice_date,
    amount,
    subtotal,
    paid_amount,
    status,
    created_by
)
SELECT
    CONCAT(
        'INV-',
        DATE_FORMAT(CURDATE(), '%Y%m'),
        '-',
        LPAD(base.last_num + ROW_NUMBER() OVER (ORDER BY q.id), 4, '0')
    ),
    q.project_id,
    NULL,
    q.id,
    (
        SELECT c.id
        FROM clients c
        WHERE c.status = 'active'
          AND LOWER(c.name COLLATE utf8mb4_general_ci) = LOWER(TRIM(p.client) COLLATE utf8mb4_general_ci)
        ORDER BY c.id
        LIMIT 1
    ),
    COALESCE(NULLIF(TRIM(p.client), ''), ''),
    COALESCE(NULLIF(TRIM(p.client), ''), ''),
    CURDATE(),
    COALESCE(q.total_amount, 0),
    COALESCE(q.total_amount, 0),
    0,
    'draft',
    q.created_by
FROM quotations q
LEFT JOIN projects p ON p.id = q.project_id
CROSS JOIN (
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)), 0) AS last_num
    FROM invoices
    WHERE invoice_number LIKE CONCAT('INV-', DATE_FORMAT(CURDATE(), '%Y%m'), '-%')
      AND SUBSTRING_INDEX(invoice_number, '-', -1) REGEXP '^[0-9]+$'
) base
WHERE (q.status = 'received' OR q.submitted_to_accounting_at IS NOT NULL)
  AND NOT EXISTS (
      SELECT 1 FROM invoices i WHERE i.procurement_quotation_id = q.id
  );
