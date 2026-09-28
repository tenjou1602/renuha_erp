<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Warehouse Dashboard';

$stats = [];
try {
    $stats['total_inventory'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['total_stock'] = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    
    $stats['incoming'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'in' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $stats['outgoing'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'out' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    
    $stats['recent_received'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'in' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $stats['recent_released'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'out' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    
    $stats['project_materials'] = $pdo->query("SELECT COUNT(DISTINCT pr.project_id) FROM purchase_request_items pri LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id WHERE pr.status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0;
    
    $recent_stock_movements = $pdo->query("
        SELECT sm.*, m.name as material_name, u.full_name as user_name
        FROM stock_movements sm
        LEFT JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.created_by = u.id
        ORDER BY sm.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="dept-dash">
    <div class="dept-head">
        <div>
            <p class="dept-kicker">Warehouse Department</p>
            <h1><i class="fas fa-warehouse"></i> Warehouse Dashboard</h1>
            <p class="dept-sub">Receive deliveries, release stock, and watch low-stock items.</p>
        </div>
        <div class="dept-actions">
            <a href="stock.php?action=in" class="btn btn-primary"><i class="fas fa-plus"></i> Stock-In</a>
            <a href="stock.php?action=out" class="btn btn-outline"><i class="fas fa-minus"></i> Stock-Out</a>
            <a href="receive_purchases.php" class="btn btn-outline"><i class="fas fa-truck-loading"></i> Receive Purchases</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php require_once '../../includes/stock_alerts.php'; ?>
    <?php renderLowStockBanner(APP_URL . 'modules/warehouse/inventory.php'); ?>

    <div class="dept-kpis">
        <div class="dept-kpi green"><div class="ico"><i class="fas fa-boxes"></i></div><div><div class="num"><?php echo number_format($stats['total_inventory'] ?? 0); ?></div><div class="lbl">Total Inventory</div></div></div>
        <div class="dept-kpi teal"><div class="ico"><i class="fas fa-arrow-down"></i></div><div><div class="num"><?php echo number_format($stats['incoming'] ?? 0); ?></div><div class="lbl">Incoming (30d)</div></div></div>
        <div class="dept-kpi red"><div class="ico"><i class="fas fa-arrow-up"></i></div><div><div class="num"><?php echo number_format($stats['outgoing'] ?? 0); ?></div><div class="lbl">Outgoing (30d)</div></div></div>
        <div class="dept-kpi orange"><div class="ico"><i class="fas fa-exclamation-triangle"></i></div><div><div class="num"><?php echo number_format($stats['low_stock'] ?? 0); ?></div><div class="lbl">Low Stock</div></div></div>
        <div class="dept-kpi blue"><div class="ico"><i class="fas fa-layer-group"></i></div><div><div class="num"><?php echo number_format($stats['total_stock'] ?? 0); ?></div><div class="lbl">Total Stock Qty</div></div></div>
        <div class="dept-kpi sky"><div class="ico"><i class="fas fa-truck-loading"></i></div><div><div class="num"><?php echo number_format($stats['recent_received'] ?? 0); ?></div><div class="lbl">Recently Received</div></div></div>
        <div class="dept-kpi slate"><div class="ico"><i class="fas fa-dolly"></i></div><div><div class="num"><?php echo number_format($stats['recent_released'] ?? 0); ?></div><div class="lbl">Recently Released</div></div></div>
        <div class="dept-kpi purple"><div class="ico"><i class="fas fa-hard-hat"></i></div><div><div class="num"><?php echo number_format($stats['project_materials'] ?? 0); ?></div><div class="lbl">Project Materials</div></div></div>
    </div>

    <div class="dept-grid">
        <div class="dept-panel">
            <h3><i class="fas fa-exchange-alt"></i> Recent Stock Movements</h3>
            <?php if (empty($recent_stock_movements)): ?>
                <div class="empty-state compact"><i class="fas fa-exchange-alt"></i><p>No recent stock movements.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dept-table">
                    <thead><tr><th>Material</th><th>Type</th><th>Qty</th><th>User</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_stock_movements as $movement): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></td>
                            <td><span class="dept-badge <?php echo $movement['movement_type'] === 'in' ? 'in' : 'out'; ?>"><?php echo ucfirst($movement['movement_type']); ?></span></td>
                            <td><?php echo number_format($movement['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($movement['user_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($movement['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="dept-panel">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="dept-qa">
                <a class="t1" href="receive_purchases.php"><i class="fas fa-truck-loading"></i>Receive Purchases</a>
                <a class="t2" href="inventory.php"><i class="fas fa-clipboard-list"></i>Inventory</a>
                <a class="t3" href="material_requests.php"><i class="fas fa-box-open"></i>Material Requests</a>
                <a class="t4" href="inventory_history.php"><i class="fas fa-history"></i>Inventory History</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
