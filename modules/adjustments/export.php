<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';

$department = $_GET['department'] ?? 'admin';
if (!in_array($department, ['accounting', 'engineering', 'procurement', 'warehouse', 'admin'], true)) {
    $department = 'admin';
}
if ($department === 'admin') {
    requireAdmin();
} elseif ($department === 'engineering') {
    requireDepartment(['engineering']);
} else {
    requireDepartment([$department]);
}
$stmt = $pdo->prepare("SELECT period, type, amount_before, amount_after, difference, reason, status FROM adjustments WHERE department = ? ORDER BY id");
$stmt->execute([$department]);
$rows = $stmt->fetchAll();
$sum = 0.0;
foreach ($rows as $row) {
    if ($row['status'] === 'approved') {
        $sum += (float) $row['difference'];
    }
}
$rows[] = ['period' => '', 'type' => 'Approved net', 'amount_before' => '', 'amount_after' => '', 'difference' => $sum, 'reason' => '', 'status' => ''];
$title = 'Monthly adjustments — ' . $department;
if (($_GET['format'] ?? '') === 'pdf') {
    renderReportPdf($title, array_keys($rows[0] ?? ['period' => '']), $rows);
}
renderReportExcel($title, $rows);
