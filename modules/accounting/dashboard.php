<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Accounting Dashboard';

$stats = [];
try {
    $stats['total_project_amount'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_project_expenses'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0;
    $stats['total_expenses'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn() ?? 0;
    
    try {
        $stats['available_funds'] = $pdo->query("SELECT COALESCE(SUM(amount - allocated_amount), 0) FROM funds WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['available_funds'] = 0;
    }
    
    try {
        $stats['contracts'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['contracts'] = 0;
    }
    
    try {
        $stats['labor_budgets'] = $pdo->query("SELECT COUNT(*) FROM labor_budgets WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['labor_budgets'] = 0;
    }
    
    $stats['purchase_costs'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0;
    
    $recent_financial_records = $pdo->query("
        (SELECT 'Invoice' as type, invoice_number as reference, amount, client as entity, status, created_at FROM invoices ORDER BY created_at DESC LIMIT 3)
        UNION ALL
        (SELECT 'Expense' as type, CONCAT('EXP-', id) as reference, amount, description as entity, status, created_at FROM expenses ORDER BY created_at DESC LIMIT 3)
        ORDER BY created_at DESC
        LIMIT 6
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="dept-dash">
    <div class="dept-head">
        <div>
            <p class="dept-kicker">Accounting Department</p>
            <h1><i class="fas fa-chart-line"></i> Accounting Dashboard</h1>
            <p class="dept-sub">Monitor project amounts, funds, invoices, and expenses.</p>
        </div>
        <div class="dept-actions">
            <a href="invoices.php?action=add" class="btn btn-primary"><i class="fas fa-file-invoice-dollar"></i> New Invoice</a>
            <a href="expenses.php?action=add" class="btn btn-outline"><i class="fas fa-receipt"></i> New Expense</a>
            <a href="project_financials.php" class="btn btn-outline"><i class="fas fa-project-diagram"></i> Project Financials</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dept-kpis">
        <div class="dept-kpi blue"><div class="ico"><i class="fas fa-folder"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['total_project_amount'] ?? 0), 2); ?></div><div class="lbl">Total Project Amount</div></div></div>
        <div class="dept-kpi red"><div class="ico"><i class="fas fa-receipt"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['total_project_expenses'] ?? 0), 2); ?></div><div class="lbl">Project Expenses</div></div></div>
        <div class="dept-kpi green"><div class="ico"><i class="fas fa-wallet"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['available_funds'] ?? 0), 2); ?></div><div class="lbl">Available Funds</div></div></div>
        <div class="dept-kpi teal"><div class="ico"><i class="fas fa-file-contract"></i></div><div><div class="num"><?php echo number_format($stats['contracts'] ?? 0); ?></div><div class="lbl">Active Contracts</div></div></div>
        <div class="dept-kpi orange"><div class="ico"><i class="fas fa-users"></i></div><div><div class="num"><?php echo number_format($stats['labor_budgets'] ?? 0); ?></div><div class="lbl">Labor Budgets</div></div></div>
        <div class="dept-kpi purple"><div class="ico"><i class="fas fa-shopping-cart"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['purchase_costs'] ?? 0), 2); ?></div><div class="lbl">Purchase Costs</div></div></div>
    </div>

    <div class="dept-grid">
        <div class="dept-panel">
            <h3><i class="fas fa-clock"></i> Recent Financial Records</h3>
            <?php if (empty($recent_financial_records)): ?>
                <div class="empty-state compact"><i class="fas fa-chart-line"></i><p>No recent financial records.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dept-table">
                    <thead><tr><th>Type</th><th>Reference</th><th>Entity</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_financial_records as $record): ?>
                        <tr>
                            <td><span class="dept-badge"><?php echo htmlspecialchars($record['type']); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($record['reference']); ?></strong></td>
                            <td><?php echo htmlspecialchars($record['entity']); ?></td>
                            <td>₱<?php echo number_format((float) $record['amount'], 2); ?></td>
                            <td><span class="dept-badge <?php echo htmlspecialchars($record['status']); ?>"><?php echo ucfirst($record['status']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($record['created_at'])); ?></td>
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
                <a class="t1" href="invoices.php"><i class="fas fa-file-invoice-dollar"></i>Invoices</a>
                <a class="t2" href="expenses.php"><i class="fas fa-coins"></i>Expenses</a>
                <a class="t3" href="assign_in_charge.php"><i class="fas fa-user-tie"></i>Assign In-Charge</a>
                <a class="t4" href="financial_reports.php"><i class="fas fa-chart-bar"></i>Financial Reports</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
