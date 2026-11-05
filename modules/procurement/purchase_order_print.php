<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';

requireDepartment(['procurement']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT po.*, pr.pr_number, u.full_name AS created_by_name,
           p.project_code, p.name AS project_name
    FROM purchase_orders po
    LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
    LEFT JOIN users u ON u.id = po.created_by
    LEFT JOIN projects p ON p.id = po.project_id
    WHERE po.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    exit('Purchase order not found.');
}

runehaStreamPdf(
    'purchase-order-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $order['po_number']) . '.pdf',
    buildPurchaseOrderReportHtml($pdo, $order)
);
