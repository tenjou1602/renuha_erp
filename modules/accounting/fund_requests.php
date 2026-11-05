<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/fund_requests.php';

requireDepartment(['accounting']);
$page_title = 'Fund Requests';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decide_fund_request'])) {
    try {
        if (!canWriteDepartmentData('accounting')) {
            throw new RuntimeException('You cannot decide fund requests.');
        }
        $id = (int) ($_POST['request_id'] ?? 0);
        $decision = ($_POST['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
        $reason = trim((string) ($_POST['decision_reason'] ?? ''));
        if ($decision === 'rejected' && mb_strlen($reason) < 5) {
            throw new RuntimeException('A rejection reason of at least 5 characters is required.');
        }
        $stmt = $pdo->prepare("SELECT fr.*, p.project_code FROM fund_requests fr INNER JOIN projects p ON p.id = fr.project_id WHERE fr.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'pending') {
            throw new RuntimeException('That request is not pending. Approved and rejected requests cannot be decided again.');
        }
        assertNotOwnApproval($row['requested_by']);
        if ($decision === 'approved') {
            assertFundRequestAllowed($pdo, (int) $row['project_id'], number_format((float) $row['amount'], 2, '.', ''), (string) $row['purpose'], (int) $row['id']);
        }
        $update = $pdo->prepare("UPDATE fund_requests SET status = ?, decided_by = ?, decided_at = NOW(), decision_reason = ? WHERE id = ? AND status = 'pending'");
        $update->execute([$decision, $_SESSION['user_id'], $reason !== '' ? mb_substr($reason, 0, 500) : null, $id]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('That request was already decided.');
        }
        writeAudit(ucfirst($decision) . ' fund request', 'Accounting', 'fund_requests', $id, ['status' => 'pending'], ['status' => $decision], $reason !== '' ? $reason : null);
        $message = $row['request_number'] . ' for ' . $row['project_code'] . ' was ' . $decision . '.';
        notify('admin', 'fund_request_' . $decision, 'Fund request ' . $decision, $message, 'modules/admin/fund_requests.php', (int) $row['project_id'], 'fund_request', $id);
        notify((int) $row['requested_by'], 'fund_request_' . $decision, 'Fund request ' . $decision, $message, 'modules/admin/fund_requests.php', (int) $row['project_id'], 'fund_request', $id);
        $_SESSION['success'] = $row['request_number'] . ' was ' . $decision . '.';
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: fund_requests.php');
    exit();
}

$focus = (int) ($_GET['id'] ?? 0);
$rows = $pdo->query("
    SELECT fr.*, p.project_code, p.name AS project_name, u.full_name AS requester_name
    FROM fund_requests fr
    INNER JOIN projects p ON p.id = fr.project_id
    LEFT JOIN users u ON u.id = fr.requested_by
    ORDER BY FIELD(fr.status, 'pending', 'approved', 'rejected', 'cancelled'), fr.id DESC
")->fetchAll();
$detail = null;
if ($focus > 0) {
    foreach ($rows as $row) {
        if ((int) $row['id'] === $focus) {
            $detail = $row;
        }
    }
}

include '../../includes/header.php';
?>
<div class="page-header"><h1>Fund Requests</h1></div>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<?php if ($detail): ?>
<div class="card">
    <h3><?php echo htmlspecialchars($detail['request_number']); ?></h3>
    <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($detail['project_code'] . ' — ' . $detail['project_name']); ?></div>
    <div class="detail-row"><span>Amount:</span> ₱<?php echo number_format((float) $detail['amount'], 2); ?></div>
    <div class="detail-row"><span>Purpose:</span> <?php echo htmlspecialchars($detail['purpose']); ?></div>
    <div class="detail-row"><span>Requested by:</span> <?php echo htmlspecialchars($detail['requester_name'] ?? 'Unknown'); ?> on <?php echo date('M d, Y h:i A', strtotime($detail['requested_at'])); ?></div>
    <div class="detail-row"><span>Status:</span> <?php echo htmlspecialchars(fundRequestAge($detail['requested_at'], $detail['status'])); ?></div>
    <?php if ($detail['decision_reason']): ?><div class="detail-row"><span>Decision:</span> <?php echo htmlspecialchars($detail['decision_reason']); ?></div><?php endif; ?>
</div>
<?php endif; ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Request</th><th>Project</th><th>Amount</th><th>Purpose</th><th>Requested by</th><th>Age</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7">No fund requests yet.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['request_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['project_code'] . ' — ' . $row['project_name']); ?></td>
                    <td>₱<?php echo number_format((float) $row['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                    <td><?php echo htmlspecialchars($row['requester_name'] ?? 'Unknown'); ?></td>
                    <td><?php echo htmlspecialchars(fundRequestAge($row['requested_at'], $row['status'])); ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="?id=<?php echo (int) $row['id']; ?>">Details</a>
                        <?php if ($row['status'] === 'pending' && canWriteDepartmentData('accounting') && (int) $row['requested_by'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="decide_fund_request" value="1">
                            <input type="hidden" name="request_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="decision" value="approved">
                            <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                        </form>
                        <form method="post" style="display:inline-flex; gap:0.3rem; margin-top:0.3rem;">
                            <input type="hidden" name="decide_fund_request" value="1">
                            <input type="hidden" name="request_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="decision" value="rejected">
                            <input type="text" name="decision_reason" minlength="5" required placeholder="Reason, at least 5 characters">
                            <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
