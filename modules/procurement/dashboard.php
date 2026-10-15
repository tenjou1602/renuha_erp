<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Procurement Dashboard';

$stats = [];
try {
    try {
        syncDepartmentWorkflow($pdo);
    } catch (Throwable $e) {
        error_log('[Runeha ERP] department sync: ' . $e->getMessage());
    }
    $stats['total_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn() ?? 0;
    $stats['pending_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
    $stats['for_comparison'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests pr WHERE pr.status = 'approved' AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.purchase_request_id = pr.id AND q.status = 'accepted')")->fetchColumn() ?? 0;
    $stats['ready_to_order'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests pr WHERE EXISTS (SELECT 1 FROM quotations q WHERE q.purchase_request_id = pr.id AND q.status = 'accepted') AND NOT EXISTS (SELECT 1 FROM purchase_orders po WHERE po.purchase_request_id = pr.id AND po.status <> 'cancelled')")->fetchColumn() ?? 0;
    $stats['total_value'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received', 'completed')")->fetchColumn() ?? 0;
    $stats['for_warehouse'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('sent', 'confirmed', 'ready_for_warehouse')")->fetchColumn() ?? 0;
    $stats['purchased_materials'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'delivered'")->fetchColumn() ?? 0;
    
    $recent_purchases = $pdo->query("
        SELECT pr.*, p.project_code, u.full_name as requestor_name
        FROM purchase_requests pr
        LEFT JOIN projects p ON pr.project_id = p.id
        LEFT JOIN users u ON pr.requestor_id = u.id
        ORDER BY pr.created_at DESC
        LIMIT 5
    ")->fetchAll();
    
    $low_stock_materials = $pdo->query("
        SELECT m.id, m.material_code, m.name, m.current_stock, m.min_stock, m.unit
        FROM materials m
        WHERE m.current_stock <= m.min_stock AND m.min_stock > 0 AND m.status = 'active'
        ORDER BY (m.min_stock - m.current_stock) DESC
        LIMIT 5
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="dept-dash">
    <div class="dept-head">
        <div>
            <p class="dept-kicker">Procurement Department</p>
            <h1><i class="fas fa-shopping-cart"></i> Procurement Dashboard</h1>
            <p class="dept-sub">1) Receive the material request. 2) Choose a supplier. 3) Create the purchase order. 4) Send it to the Warehouse.</p>
        </div>
        <?php if (canWriteDepartmentData('procurement')): ?>
        <div class="dept-actions">
            <a href="received_requests.php" class="btn btn-primary"><i class="fas fa-inbox"></i> Material Requests</a>
            <a href="suppliers.php" class="btn btn-outline"><i class="fas fa-truck"></i> Supplier Management</a>
            <a href="purchase_orders.php" class="btn btn-outline"><i class="fas fa-shopping-cart"></i> Purchase Management</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dept-kpis">
        <a class="dept-kpi orange" href="received_requests.php"><div class="ico"><i class="fas fa-inbox"></i></div><div><div class="num"><?php echo number_format($stats['pending_prs'] ?? 0); ?></div><div class="lbl">1. Accept requests</div></div></a>
        <a class="dept-kpi red" href="quotations.php"><div class="ico"><i class="fas fa-balance-scale"></i></div><div><div class="num"><?php echo number_format($stats['for_comparison'] ?? 0); ?></div><div class="lbl">2. Compare prices</div></div></a>
        <a class="dept-kpi blue" href="purchase_orders.php"><div class="ico"><i class="fas fa-shopping-cart"></i></div><div><div class="num"><?php echo number_format($stats['ready_to_order'] ?? 0); ?></div><div class="lbl">3. Create orders</div></div></a>
        <a class="dept-kpi teal" href="purchased_materials.php"><div class="ico"><i class="fas fa-truck-loading"></i></div><div><div class="num"><?php echo number_format($stats['for_warehouse'] ?? 0); ?></div><div class="lbl">4. For warehouse</div></div></a>
        <a class="dept-kpi purple" href="purchased_materials.php"><div class="ico"><i class="fas fa-boxes"></i></div><div><div class="num"><?php echo number_format($stats['purchased_materials'] ?? 0); ?></div><div class="lbl">Delivered orders</div></div></a>
        <a class="dept-kpi green" href="reports.php?report=costs"><div class="ico"><i class="fas fa-coins"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['total_value'] ?? 0), 0); ?></div><div class="lbl">Purchase Costs</div></div></a>
    </div>

    <div class="card">
        <h3><i class="fas fa-chart-bar"></i> Purchases</h3>
        <canvas id="procChart" height="80"></canvas>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('procChart'), {
        type: 'bar',
        data: {
            labels: ['Accept', 'Compare prices', 'Create orders', 'For warehouse'],
            datasets: [{
                data: [
                    <?php echo (int) ($stats['pending_prs'] ?? 0); ?>,
                    <?php echo (int) ($stats['for_comparison'] ?? 0); ?>,
                    <?php echo (int) ($stats['ready_to_order'] ?? 0); ?>,
                    <?php echo (int) ($stats['for_warehouse'] ?? 0); ?>
                ],
                backgroundColor: ['#f6c23e', '#e74a3b', '#4e73df', '#1cc88a']
            }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    </script>

    <div class="dept-grid">
        <div class="dept-panel">
            <h3><i class="fas fa-clock"></i> Recent Purchases</h3>
            <?php if (empty($recent_purchases)): ?>
                <div class="empty-state compact"><i class="fas fa-shopping-cart"></i><p>No recent purchases.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dept-table">
                    <thead><tr><th>PR #</th><th>Project</th><th>Requestor</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_purchases as $pr): ?>
                        <tr>
                            <td><a href="purchase_requests.php?action=view&id=<?php echo (int) $pr['id']; ?>"><strong><?php echo htmlspecialchars($pr['pr_number']); ?></strong></a></td>
                            <td><?php echo htmlspecialchars($pr['project_code'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($pr['requestor_name'] ?? 'N/A'); ?></td>
                            <td>₱<?php echo number_format((float) $pr['total_amount'], 2); ?></td>
                            <td><span class="dept-badge <?php echo htmlspecialchars($pr['status']); ?>"><?php echo ucfirst($pr['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <div class="dept-panel">
            <h3><i class="fas fa-exclamation-triangle"></i> Need to Purchase</h3>
            <?php if (empty($low_stock_materials)): ?>
                <div class="empty-state compact"><i class="fas fa-check-circle"></i><p>No materials need purchasing.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dept-table">
                    <thead><tr><th>Material</th><th>Stock</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($low_stock_materials as $material): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($material['name']); ?></strong><div style="color:#94a3b8;font-size:.72rem;"><?php echo htmlspecialchars($material['material_code']); ?></div></td>
                            <td class="text-danger"><?php echo number_format($material['current_stock']); ?> / <?php echo number_format($material['min_stock']); ?></td>
                            <td><a href="suppliers.php" class="btn btn-sm btn-outline">Suppliers</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
