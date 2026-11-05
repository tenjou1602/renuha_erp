<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';
requireDepartment(['procurement']);

$from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01'));
$to = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d'));
$end = $to . ' 23:59:59';
$rows = [];
$requests = $pdo->prepare("SELECT pr.pr_number, pr.status, pr.total_amount, p.project_code FROM purchase_requests pr LEFT JOIN projects p ON p.id = pr.project_id WHERE pr.created_at BETWEEN ? AND ?");
$requests->execute([$from, $end]);
foreach ($requests as $row) {
    $rows[] = ['section' => 'Material request', 'reference' => $row['pr_number'], 'project' => $row['project_code'], 'status' => $row['status'], 'amount' => $row['total_amount']];
}
$orders = $pdo->prepare("SELECT po.po_number, po.supplier, po.status, po.total_amount, p.project_code, e.status AS payment_status FROM purchase_orders po LEFT JOIN projects p ON p.id = po.project_id LEFT JOIN expenses e ON e.purchase_order_id = po.id WHERE po.created_at BETWEEN ? AND ?");
$orders->execute([$from, $end]);
foreach ($orders as $row) {
    $rows[] = ['section' => 'Purchase order', 'reference' => $row['po_number'] . ' / ' . $row['supplier'], 'project' => $row['project_code'], 'status' => $row['status'] . ' / ' . ($row['payment_status'] ?: 'no expense'), 'amount' => $row['total_amount']];
}
$quotes = $pdo->prepare("SELECT quotation_number, status, total_amount FROM quotations WHERE created_at BETWEEN ? AND ?");
$quotes->execute([$from, $end]);
foreach ($quotes as $row) {
    $rows[] = ['section' => 'Supplier price', 'reference' => $row['quotation_number'], 'project' => '', 'status' => $row['status'], 'amount' => $row['total_amount']];
}
$sum = 0.0;
foreach ($rows as $row) {
    if ($row['section'] === 'Purchase order') {
        $sum += (float) $row['amount'];
    }
}
$basePurchase = (float) $pdo->query("SELECT COALESCE(SUM(purchase_total), 0) FROM v_project_costs")->fetchColumn();
$dashboardPurchase = reportedDepartmentMoney($basePurchase, 'procurement');
$rows[] = ['section' => 'Total purchase orders in this period', 'reference' => '', 'project' => '', 'status' => '', 'amount' => $sum];
$rows[] = ['section' => 'Approved adjustments', 'reference' => '', 'project' => '', 'status' => '', 'amount' => $dashboardPurchase - $basePurchase];
$rows[] = ['section' => 'Dashboard recorded purchase costs', 'reference' => '', 'project' => '', 'status' => '', 'amount' => $dashboardPurchase];
$title = 'Procurement report ' . $from . ' to ' . $to;
if (($_GET['format'] ?? '') === 'pdf') {
    renderReportPdf($title, ['section', 'reference', 'project', 'status', 'amount'], $rows);
}
renderReportExcel($title, $rows);
