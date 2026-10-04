<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/accounting_ledger.php';

requireDepartment(['accounting']);

$page_title = 'Ledger';
$account = $_GET['account'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$balances = [];
$lines = [];
$error = null;
try {
    $balances = ledgerAccountBalances();
    $sql = "
        SELECT je.entry_date, je.description, je.reference_type, a.code, a.name AS account_name,
               jl.debit, jl.credit, p.project_code
        FROM journal_lines jl
        JOIN journal_entries je ON je.id = jl.journal_entry_id
        JOIN chart_of_accounts a ON a.id = jl.account_id
        LEFT JOIN projects p ON p.id = jl.project_id
        WHERE je.entry_date BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    if ($account !== '') {
        $sql .= " AND a.code = ?";
        $params[] = $account;
    }
    $sql .= " ORDER BY je.entry_date, je.id, jl.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lines = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-book"></i> <?php echo $page_title; ?></h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="stat-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <?php foreach ($balances as $account_row): ?>
    <div class="card" style="margin:0;">
        <div style="font-size:0.8rem;color:#64748b;"><?php echo htmlspecialchars($account_row['name']); ?></div>
        <div style="font-size:1.4rem;font-weight:700;">₱<?php echo number_format($account_row['balance'], 2); ?></div>
        <div style="font-size:0.75rem;color:#94a3b8;">Debit ₱<?php echo number_format($account_row['total_debit'], 2); ?> · Credit ₱<?php echo number_format($account_row['total_credit'], 2); ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <form method="GET" class="report-filters" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
        <div class="form-group">
            <label>Account</label>
            <select name="account">
                <option value="">All accounts</option>
                <?php foreach ($balances as $account_row): ?>
                    <option value="<?php echo htmlspecialchars($account_row['code']); ?>" <?php echo $account === $account_row['code'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($account_row['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Account</th>
                    <th>Project</th>
                    <th>Debit</th>
                    <th>Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lines)): ?>
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No ledger lines for this filter. Quotations do not appear here until they are invoiced.</td></tr>
                <?php else: ?>
                    <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($line['entry_date'])); ?></td>
                        <td><?php echo htmlspecialchars($line['description']); ?></td>
                        <td><?php echo htmlspecialchars($line['account_name']); ?></td>
                        <td><?php echo htmlspecialchars($line['project_code'] ?? '—'); ?></td>
                        <td><?php echo (float) $line['debit'] > 0 ? '₱' . number_format($line['debit'], 2) : ''; ?></td>
                        <td><?php echo (float) $line['credit'] > 0 ? '₱' . number_format($line['credit'], 2) : ''; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
