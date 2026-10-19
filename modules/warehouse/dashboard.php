<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Warehouse Dashboard';

$stats = [];
try {
    try {
        syncDepartmentWorkflow($pdo);
    } catch (Throwable $e) {
        error_log('[Runeha ERP] department sync: ' . $e->getMessage());
    }
    $stats['total_inventory'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active' AND current_stock > 0")->fetchColumn() ?? 0;
    $stats['inventory_value'] = $pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit), 0) FROM materials WHERE status = 'active' AND current_stock > 0")->fetchColumn() ?? 0;
    $stats['total_stock'] = $stats['total_inventory'];
    $stats['out_of_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active' AND current_stock <= 0")->fetchColumn() ?? 0;
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock > 0 AND current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    
    $stats['incoming'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'in' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $stats['outgoing'] = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE movement_type = 'out' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    
    $stats['recent_received'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'in' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $stats['recent_released'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'out' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    
    $stats['pending_releases'] = 0;
    $stats['released_today'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'out' AND DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;
    $stats['stock_in_today'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'in' AND DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;
    $stats['stock_out_today'] = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE movement_type = 'out' AND DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;
    $stats['incoming_materials'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'ready_for_warehouse' AND received_at IS NULL")->fetchColumn() ?? 0;
    try {
        $stats['pending_releases'] = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['pending_releases'] = 0;
    }
    
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
            <p class="dept-sub">When a material request is received and stock is available, release it to the project site. This is the stock-out.</p>
        </div>
        <div class="dept-actions">
            <a href="stock.php?action=in" class="btn btn-primary"><i class="fas fa-arrow-down"></i> Stock-In</a>
            <a href="stock.php?action=out" class="btn btn-outline"><i class="fas fa-arrow-up"></i> Stock-Out</a>
            <a href="receive_purchases.php" class="btn btn-outline"><i class="fas fa-truck-loading"></i> Receiving</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php require_once '../../includes/stock_alerts.php'; ?>
    <?php renderLowStockBanner(APP_URL . 'modules/warehouse/inventory.php'); ?>

    <div class="dept-kpis">
        <a class="dept-kpi green" href="inventory.php"><div class="ico"><i class="fas fa-boxes"></i></div><div><div class="num" title="Inventory value <?php echo formatCompactMoney($stats['inventory_value'] ?? 0); ?>. Count of items with stock, not a sum of mixed units."><?php echo number_format($stats['total_inventory'] ?? 0); ?></div><div class="lbl">Items In Stock</div></div></a>
        <a class="dept-kpi orange" href="inventory.php"><div class="ico"><i class="fas fa-exclamation-triangle"></i></div><div><div class="num"><?php echo number_format($stats['low_stock'] ?? 0); ?></div><div class="lbl">Low Stock Items</div></div></a>
        <a class="dept-kpi teal" href="receive_purchases.php"><div class="ico"><i class="fas fa-truck-loading"></i></div><div><div class="num"><?php echo number_format($stats['incoming_materials'] ?? 0); ?></div><div class="lbl">Incoming Materials</div></div></a>
        <a class="dept-kpi red" href="material_requests.php"><div class="ico"><i class="fas fa-dolly"></i></div><div><div class="num"><?php echo number_format($stats['pending_releases'] ?? 0); ?></div><div class="lbl">Pending Releases</div></div></a>
        <a class="dept-kpi purple" href="material_requests.php"><div class="ico"><i class="fas fa-box-open"></i></div><div><div class="num"><?php echo number_format($stats['released_today'] ?? 0); ?></div><div class="lbl">Released Today</div></div></a>
        <a class="dept-kpi blue" href="movements.php"><div class="ico"><i class="fas fa-exchange-alt"></i></div><div><div class="num"><?php echo number_format($stats['stock_in_today'] ?? 0); ?> / <?php echo number_format($stats['stock_out_today'] ?? 0); ?></div><div class="lbl">Stock-In / Stock-Out qty today</div></div></a>
    </div>

    <?php
    $movement_days = [];
    try {
        $movement_days = $pdo->query("
            SELECT DATE(created_at) AS day,
                   SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) AS qty_in,
                   SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) AS qty_out
            FROM stock_movements
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
            ORDER BY day
        ")->fetchAll();
    } catch (PDOException $e) {
        $movement_days = [];
    }
    $day_labels = [];
    $day_in = [];
    $day_out = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime('-' . $i . ' days'));
        $day_labels[] = date('M j', strtotime($day));
        $found = null;
        foreach ($movement_days as $row) {
            if ($row['day'] === $day) {
                $found = $row;
                break;
            }
        }
        $day_in[] = (int) ($found['qty_in'] ?? 0);
        $day_out[] = (int) ($found['qty_out'] ?? 0);
    }
    ?>
    <div class="dept-grid">
        <div class="card">
            <h3>Stock-in and stock-out, last 7 days</h3>
            <div class="chart-compact"><canvas id="whMoveChart"></canvas></div>
        </div>
        <div class="card">
            <h3>Items by status</h3>
            <div class="chart-compact"><canvas id="whStatusChart"></canvas></div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('whMoveChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($day_labels); ?>,
            datasets: [
                { label: 'Stock-in qty', data: <?php echo json_encode($day_in); ?>, backgroundColor: '#1cc88a' },
                { label: 'Stock-out qty', data: <?php echo json_encode($day_out); ?>, backgroundColor: '#4e73df' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    new Chart(document.getElementById('whStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['In stock', 'Low', 'Out of stock'],
            datasets: [{ data: [<?php echo (int) (($stats['total_inventory'] ?? 0) - ($stats['low_stock'] ?? 0)); ?>, <?php echo (int) ($stats['low_stock'] ?? 0); ?>, <?php echo (int) ($stats['out_of_stock'] ?? 0); ?>], backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b'] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
    </script>

    <div class="dept-grid">
        <div class="dept-panel">
            <h3><i class="fas fa-exchange-alt"></i> Recent Stock Movements</h3>
            <a href="movements.php" class="btn btn-sm btn-outline">All movements</a>
            <?php if (empty($recent_stock_movements)): ?>
                <div class="empty-state compact"><i class="fas fa-exchange-alt"></i><p>No recent stock movements.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dept-table">
                    <thead><tr><th>Material</th><th>Type</th><th>Qty</th><th>User</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_stock_movements as $movement): ?>
                        <tr>
                            <td><a href="inventory_history.php?material_id=<?php echo (int) $movement['material_id']; ?>"><?php echo htmlspecialchars($movement['material_name'] ?? 'N/A'); ?></a></td>
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
                <a class="t1" href="receive_purchases.php"><i class="fas fa-truck-loading"></i>Receiving</a>
                <a class="t2" href="<?php echo APP_URL; ?>modules/procurement/materials.php"><i class="fas fa-boxes"></i>Inventory Management</a>
                <a class="t3" href="material_requests.php"><i class="fas fa-dolly"></i>Material Release</a>
                <a class="t4" href="inventory.php"><i class="fas fa-chart-bar"></i>Inventory Reports</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
