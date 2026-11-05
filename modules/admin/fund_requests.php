<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/fund_requests.php';

requireAdmin();
$page_title = 'Request Funds';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_funds'])) {
    try {
        assertMonthOpen('accounting', date('Y-m-d'));
        $projectId = (int) ($_POST['project_id'] ?? 0);
        [$project, $amount, $purpose] = assertFundRequestAllowed($pdo, $projectId, (string) ($_POST['amount'] ?? ''), (string) ($_POST['purpose'] ?? ''));
        $number = generateNumber('FR', 'fund_requests', 'request_number');
        $pdo->prepare("INSERT INTO fund_requests (request_number, project_id, amount, purpose, status, requested_by, requested_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())")
            ->execute([$number, $projectId, $amount, mb_substr($purpose, 0, 500), $_SESSION['user_id']]);
        $id = (int) $pdo->lastInsertId();
        logActivity($_SESSION['user_id'], 'Requested funds', 'Funds', $number);
        writeAudit('Request funds', 'Admin', 'fund_requests', $id, null, ['project' => $project['project_code'], 'amount' => $amount], $purpose);
        notify('accounting', 'fund_request', 'Fund request', $number . ' for ' . $project['project_code'] . ' is waiting for a decision.', 'modules/accounting/fund_requests.php?id=' . $id, $projectId, 'fund_request', $id);
        $_SESSION['success'] = $number . ' was sent to Accounting.';
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: fund_requests.php');
    exit();
}

$projects = $pdo->query("SELECT id, project_code, name, estimated_budget FROM projects WHERE status <> 'completed' AND archived_at IS NULL ORDER BY created_at DESC")->fetchAll();
$remaining = [];
foreach ($projects as $project) {
    $remaining[(int) $project['id']] = round((float) $project['estimated_budget'] - fundRequestCommitted($pdo, (int) $project['id']), 2);
}
$mine = $pdo->query("SELECT fr.*, p.project_code, p.name AS project_name FROM fund_requests fr INNER JOIN projects p ON p.id = fr.project_id ORDER BY fr.id DESC LIMIT 50")->fetchAll();

include '../../includes/header.php';
?>
<div class="page-header"><h1>Request Funds</h1></div>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<div class="card">
    <h3>New request</h3>
    <p>Choose a project that is not completed and enter only the additional amount. Pending and approved requests cannot exceed the estimated budget.</p>
    <form method="post">
        <input type="hidden" name="request_funds" value="1">
        <div class="form-group">
            <label class="required">Project</label>
            <select name="project_id" id="fundProject" required>
                <option value="">Select a project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int) $project['id']; ?>" data-remaining="<?php echo htmlspecialchars(number_format($remaining[(int) $project['id']], 2, '.', '')); ?>">
                        <?php echo htmlspecialchars($project['project_code'] . ' — ' . $project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small id="fundRemaining">Remaining requestable amount: select a project.</small>
        </div>
        <div class="form-group">
            <label class="required">Amount</label>
            <input type="number" name="amount" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
            <label class="required">Purpose</label>
            <textarea name="purpose" minlength="5" required placeholder="Why this project needs the amount"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit to Accounting</button>
    </form>
</div>
<div class="card">
    <h3>Request history</h3>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Request</th><th>Project</th><th>Amount</th><th>Purpose</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$mine): ?>
                <tr><td colspan="5">No fund requests yet.</td></tr>
            <?php else: foreach ($mine as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['request_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['project_code'] . ' — ' . $row['project_name']); ?></td>
                    <td>₱<?php echo number_format((float) $row['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                    <td><?php echo htmlspecialchars(fundRequestAge($row['requested_at'], $row['status'])); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.getElementById('fundProject')?.addEventListener('change', function () {
    const option = this.selectedOptions[0];
    const remaining = option?.dataset.remaining;
    document.getElementById('fundRemaining').textContent = remaining === undefined || this.value === ''
        ? 'Remaining requestable amount: select a project.'
        : 'Remaining requestable amount: ₱' + Number(remaining).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
});
</script>
<?php include '../../includes/footer.php'; ?>
