-- Cost view, purchase order project link, and notifications.
-- Backup: backups/runeha_erp_before_2026_10_07_costs.sql
--
-- purchase_orders has no total_amount and no project_id.
-- Its status values are draft, sent, confirmed, ready_for_warehouse, delivered, cancelled.
-- The view treats confirmed as approved, ready_for_warehouse as partial, and delivered as received.
--
-- Rows that still have no project and cannot be set NOT NULL yet:
--   PO-202610-0001 (draft) and PO-202610-0002 (delivered), both from purchase request 1, which has project_id NULL.
--   No expense row has a missing project_id.

ALTER TABLE `purchase_orders`
  ADD COLUMN `project_id` BIGINT UNSIGNED NULL AFTER `purchase_request_id`,
  ADD COLUMN `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `payment_terms`,
  ADD KEY `idx_po_project` (`project_id`),
  ADD CONSTRAINT `fk_po_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE `purchase_orders` po
INNER JOIN `purchase_requests` pr ON pr.id = po.purchase_request_id
SET po.project_id = pr.project_id
WHERE pr.project_id IS NOT NULL;

UPDATE `purchase_orders` po
SET po.total_amount = (
  SELECT COALESCE(SUM(i.quantity * i.unit_price), 0)
  FROM `purchase_order_items` i
  WHERE i.purchase_order_id = po.id
);

-- fk_expenses_project is ON DELETE SET NULL, which forbids a NOT NULL column.
ALTER TABLE `expenses` DROP FOREIGN KEY `fk_expenses_project`;
ALTER TABLE `expenses` MODIFY `project_id` BIGINT UNSIGNED NOT NULL;
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_expenses_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

CREATE OR REPLACE VIEW `v_project_costs` AS
SELECT
  p.id AS project_id,
  p.project_code,
  p.name,
  p.estimated_budget,
  p.status,
  COALESCE(ex.expense_total, 0) AS expense_total,
  COALESCE(po.purchase_total, 0) AS purchase_total,
  COALESCE(ex.expense_total, 0) + COALESCE(po.purchase_total, 0) AS actual_cost,
  p.estimated_budget - (COALESCE(ex.expense_total, 0) + COALESCE(po.purchase_total, 0)) AS budget_variance
FROM `projects` p
LEFT JOIN (
  SELECT project_id, SUM(amount) AS expense_total
  FROM `expenses`
  WHERE status <> 'cancelled'
  GROUP BY project_id
) ex ON ex.project_id = p.id
LEFT JOIN (
  SELECT project_id, SUM(total_amount) AS purchase_total
  FROM `purchase_orders`
  WHERE project_id IS NOT NULL
    AND status IN ('confirmed', 'ready_for_warehouse', 'delivered')
  GROUP BY project_id
) po ON po.project_id = p.id;

CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `role` VARCHAR(50) NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `project_id` BIGINT UNSIGNED NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `link_url` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`),
  KEY `idx_notifications_role_read` (`role`, `is_read`),
  KEY `idx_notifications_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
