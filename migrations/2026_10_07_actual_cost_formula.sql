-- Actual cost is calculated, not stored.
-- Backup: backups/runeha_erp_before_actual_cost_formula.sql
--
-- purchase_orders.status is draft, sent, confirmed, ready_for_warehouse, delivered, cancelled.
-- There is no approved/received value. This view treats:
--   confirmed, ready_for_warehouse = approved
--   delivered = received
-- draft, sent, and cancelled are excluded.
-- A purchase order is omitted when any expenses row points at it
-- (expenses.purchase_order_id), so the paid or pending expense is the only count.

CREATE OR REPLACE VIEW v_project_costs AS
SELECT
  p.id AS project_id,
  p.project_code,
  p.name,
  p.estimated_budget,
  p.status,
  COALESCE(paid.paid_total, 0) AS expense_total,
  COALESCE(pend.pending_total, 0) AS pending_cost,
  COALESCE(po.purchase_total, 0) AS purchase_total,
  COALESCE(paid.paid_total, 0) + COALESCE(po.purchase_total, 0) AS actual_cost,
  p.estimated_budget - (COALESCE(paid.paid_total, 0) + COALESCE(po.purchase_total, 0)) AS budget_variance
FROM projects p
LEFT JOIN (
  SELECT project_id, SUM(amount) AS paid_total
  FROM expenses
  WHERE status = 'paid'
  GROUP BY project_id
) paid ON paid.project_id = p.id
LEFT JOIN (
  SELECT project_id, SUM(amount) AS pending_total
  FROM expenses
  WHERE status = 'pending'
  GROUP BY project_id
) pend ON pend.project_id = p.id
LEFT JOIN (
  SELECT po.project_id, SUM(po.total_amount) AS purchase_total
  FROM purchase_orders po
  WHERE po.project_id IS NOT NULL
    AND po.status IN ('confirmed', 'ready_for_warehouse', 'delivered')
    AND NOT EXISTS (
      SELECT 1 FROM expenses e WHERE e.purchase_order_id = po.id
    )
  GROUP BY po.project_id
) po ON po.project_id = p.id;

ALTER TABLE projects DROP COLUMN actual_cost;
