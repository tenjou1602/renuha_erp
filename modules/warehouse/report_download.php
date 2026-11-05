<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';
requireDepartment(['warehouse']);

$from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01'));
$to = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d'));
$end = $to . ' 23:59:59';
$rows = [];
$value = 0.0;
$stock = $pdo->query("SELECT material_code, name, category, unit, current_stock, min_stock, cost_per_unit FROM materials WHERE status = 'active'");
foreach ($stock as $row) {
    $line = (float) $row['current_stock'] * (float) $row['cost_per_unit'];
    if ((float) $row['current_stock'] > 0) {
        $value += $line;
    }
    $flag = (float) $row['current_stock'] <= 0 ? 'Out of stock' : (((float) $row['min_stock'] > 0 && (float) $row['current_stock'] <= (float) $row['min_stock']) ? 'Low stock' : 'In stock');
    $rows[] = ['section' => 'Inventory', 'reference' => $row['material_code'] . ' ' . $row['name'], 'detail' => $row['unit'] . ' / ' . $flag, 'qty' => $row['current_stock'], 'amount' => $line];
}
$moves = $pdo->prepare("SELECT m.material_code, sm.movement_type, sm.quantity, sm.batch_number, sm.created_at FROM stock_movements sm INNER JOIN materials m ON m.id = sm.material_id WHERE sm.created_at BETWEEN ? AND ?");
$moves->execute([$from, $end]);
foreach ($moves as $row) {
    $rows[] = ['section' => 'Movement', 'reference' => $row['material_code'], 'detail' => $row['movement_type'] . ' ' . $row['batch_number'], 'qty' => $row['quantity'], 'amount' => ''];
}
$batches = $pdo->query("SELECT m.material_code, ws.batch_number, ws.quantity FROM warehouse_stock ws INNER JOIN materials m ON m.id = ws.material_id WHERE ws.quantity > 0");
foreach ($batches as $row) {
    $rows[] = ['section' => 'FIFO batch', 'reference' => $row['material_code'], 'detail' => $row['batch_number'], 'qty' => $row['quantity'], 'amount' => ''];
}
$reportedValue = reportedDepartmentMoney($value, 'warehouse');
$rows[] = ['section' => 'Inventory value of stocked items', 'reference' => '', 'detail' => '', 'qty' => '', 'amount' => $value];
$rows[] = ['section' => 'Approved adjustments', 'reference' => '', 'detail' => '', 'qty' => '', 'amount' => $reportedValue - $value];
$rows[] = ['section' => 'Dashboard inventory value', 'reference' => '', 'detail' => '', 'qty' => '', 'amount' => $reportedValue];
$title = 'Warehouse report ' . $from . ' to ' . $to;
if (($_GET['format'] ?? '') === 'pdf') {
    renderReportPdf($title, ['section', 'reference', 'detail', 'qty', 'amount'], $rows);
}
renderReportExcel($title, $rows);
