<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/accounting_ledger.php';

requireDepartment(['accounting']);

$page_title = 'Accounting Dashboard';

$stats = [];
try {
    try {
        syncDepartmentWorkflow($pdo);
    } catch (Throwable $e) {
        error_log('[Runeha ERP] purchase expense sync: ' . $e->getMessage());
    }
    $stats['total_project_amount'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_project_expenses'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM v_project_costs")->fetchColumn() ?? 0;
    $stats['pending_cost'] = $pdo->query("SELECT COALESCE(SUM(pending_cost), 0) FROM v_project_costs")->fetchColumn() ?? 0;
    $stats['total_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0;
    $stats['total_expenses'] = $stats['total_project_expenses'];
    
    try {
        $fundPool = (float) ($pdo->query("SELECT COALESCE(SUM(amount), 0) FROM funds WHERE status = 'active'")->fetchColumn() ?? 0);
        $allocatedCosts = (float) ($pdo->query("SELECT COALESCE(SUM(vpc.actual_cost), 0) FROM v_project_costs vpc INNER JOIN funds f ON f.project_id = vpc.project_id AND f.status = 'active'")->fetchColumn() ?? 0);
        $stats['available_funds'] = $fundPool - $allocatedCosts;
    } catch (PDOException $e) {
        $stats['available_funds'] = 0;
    }
    
    try {
        $stats['contracts'] = $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['contracts'] = 0;
    }
    
    $stats['purchase_costs'] = $pdo->query("SELECT COALESCE(SUM(purchase_total), 0) FROM v_project_costs")->fetchColumn() ?? 0;
    $stats['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('assigned', 'planning', 'ongoing')")->fetchColumn() ?? 0;
    $stats['pending_expenses'] = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status = 'pending'")->fetchColumn() ?? 0;
    $stats['project_financials'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
    $stats['labor_budget'] = 0;
    try {
        $stats['labor_budget'] = $pdo->query("SELECT COALESCE(SUM(allocated_amount), 0) FROM labor_budgets WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['labor_budget'] = 0;
    }
    $balances = ledgerAccountBalances();
    $stats['ledger_ar'] = 0;
    $stats['ledger_cash'] = 0;
    foreach ($balances as $balance) {
        if ($balance['code'] === RUNEHA_ACCT_AR) {
            $stats['ledger_ar'] = $balance['balance'];
        }
        if ($balance['code'] === RUNEHA_ACCT_CASH) {
            $stats['ledger_cash'] = $balance['balance'];
        }
    }
    
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
            <p class="dept-sub">Project finances, budget against expenses, funds, and purchase costs.</p>
        </div>
        <div class="dept-actions">
            <a href="received_projects.php" class="btn btn-primary"><i class="fas fa-inbox"></i> Active Projects</a>
            <a href="project_financials.php" class="btn btn-outline"><i class="fas fa-project-diagram"></i> Project Financial Setup</a>
            <a href="financial_reports.php" class="btn btn-outline"><i class="fas fa-chart-bar"></i> Financial Reports</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dept-kpis acct-kpis">
        <a class="dept-kpi blue" href="project_financials.php"><div class="ico"><i class="fas fa-folder"></i></div><div><div class="num"><?php echo number_format($stats['project_financials'] ?? 0); ?></div><div class="lbl">Project Financial Summary</div></div></a>
        <a class="dept-kpi red" href="project_financials.php"><div class="ico"><i class="fas fa-receipt"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['total_project_amount'] ?? 0), 2); ?></div><div class="lbl">Total Budget</div></div></a>
        <a class="dept-kpi red" href="expenses.php"><div class="ico"><i class="fas fa-coins"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['total_project_expenses'] ?? 0), 2); ?></div><div class="lbl">Actual Cost</div></div></a>
        <a class="dept-kpi orange" href="expenses.php"><div class="ico"><i class="fas fa-hourglass-half"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['pending_cost'] ?? 0), 2); ?></div><div class="lbl">Pending/Committed</div></div></a>
        <a class="dept-kpi green" href="funds_budget.php"><div class="ico"><i class="fas fa-wallet"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['available_funds'] ?? 0), 2); ?></div><div class="lbl">Available Funds</div></div></a>
        <a class="dept-kpi teal" href="received_projects.php"><div class="ico"><i class="fas fa-building"></i></div><div><div class="num"><?php echo number_format($stats['active_projects'] ?? 0); ?></div><div class="lbl">Active Projects</div></div></a>
        <a class="dept-kpi orange" href="expenses.php"><div class="ico"><i class="fas fa-clock"></i></div><div><div class="num"><?php echo number_format($stats['pending_expenses'] ?? 0); ?></div><div class="lbl">Pending Expense Records</div></div></a>
        <a class="dept-kpi purple" href="purchase_costs.php"><div class="ico"><i class="fas fa-shopping-cart"></i></div><div><div class="num">₱<?php echo number_format((float) ($stats['purchase_costs'] ?? 0), 2); ?></div><div class="lbl">Purchase Costs</div></div></a>
    </div>

    <div class="card">
        <h3><i class="fas fa-chart-bar"></i> Budget, expenses, and labor</h3>
        <div class="chart-compact"><canvas id="acctChart"></canvas></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('acctChart'), {
        type: 'bar',
        data: {
            labels: ['Project budget', 'Expenses', 'Available funds', 'Labor'],
            datasets: [{
                data: [
                    <?php echo json_encode((float) ($stats['total_project_amount'] ?? 0)); ?>,
                    <?php echo json_encode((float) ($stats['total_project_expenses'] ?? 0)); ?>,
                    <?php echo json_encode((float) ($stats['available_funds'] ?? 0)); ?>,
                    <?php echo json_encode((float) ($stats['labor_budget'] ?? 0)); ?>
                ],
                backgroundColor: ['#4e73df', '#e74a3b', '#1cc88a', '#f6c23e']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    </script>

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
                <a class="t1" href="project_financials.php"><i class="fas fa-project-diagram"></i>Project Financial Setup</a>
                <a class="t2" href="contracts.php"><i class="fas fa-file-contract"></i>Contract / Project Amount</a>
                <a class="t3" href="labor_budget.php"><i class="fas fa-users"></i>Labor / Salary Budget</a>
                <a class="t2" href="expenses.php"><i class="fas fa-coins"></i>Expense Management</a>
                <a class="t4" href="funds_budget.php"><i class="fas fa-wallet"></i>Funds Monitoring</a>
                <a class="t3" href="assign_in_charge.php"><i class="fas fa-user-tie"></i>Project In-Charge Assignment</a>
                <a class="t4" href="financial_reports.php"><i class="fas fa-chart-bar"></i>Financial Reports</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
