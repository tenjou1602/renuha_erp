<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Received Projects';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_accounting_decision'])) {
    if (!canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
    } else {
        try {
            $_SESSION['success'] = setAccountingProjectDecision($pdo, (int) ($_POST['project_id'] ?? 0), (string) ($_POST['decision'] ?? ''));
        } catch (Throwable $e) {
            $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Accounting') : $e->getMessage();
        }
    }
    header('Location: received_projects.php');
    exit();
}

$projects = [];
try {
    $projects = $pdo->query("
        SELECT id, project_code, name, client, location, subject, estimated_budget, status, quotation_path, quotation_name, accounting_received_at, accounting_status, created_by, created_at
        FROM projects
        WHERE accounting_status IN ('pending', 'received', 'quoted', 'rejected')
        ORDER BY created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Accounting');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-inbox"></i> Received Projects</h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<p style="color:#64748b;">Projects encoded by Executive Admin appear here. Set the status to Pending, Approved, or Rejected, then create the quotation from the attached file.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Location</th>
                    <th>Subject</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$projects): ?>
                    <tr><td colspan="7">No projects yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($project['project_code']); ?></strong><br>
                        <?php echo htmlspecialchars($project['name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($project['client'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($project['location'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($project['subject'] ?: '—'); ?></td>
                    <td>₱<?php echo number_format((float) $project['estimated_budget'], 2); ?></td>
                    <td>
                        <?php
                        $decision = 'pending';
                        if (($project['accounting_status'] ?? '') === 'rejected') {
                            $decision = 'rejected';
                        } elseif (in_array($project['accounting_status'] ?? '', ['received', 'quoted'], true)) {
                            $decision = 'approved';
                        }
                        ?>
                        <?php if (canWriteDepartmentData('accounting')): ?>
                            <form method="POST" style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                                <input type="hidden" name="set_accounting_decision" value="1">
                                <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                <button type="submit" name="decision" value="pending" class="btn btn-sm <?php echo $decision === 'pending' ? 'btn-warning' : 'btn-outline'; ?>">Pending</button>
                                <button type="submit" name="decision" value="approved" class="btn btn-sm <?php echo $decision === 'approved' ? 'btn-success' : 'btn-outline'; ?>">Approved</button>
                                <button type="submit" name="decision" value="rejected" class="btn btn-sm <?php echo $decision === 'rejected' ? 'btn-danger' : 'btn-outline'; ?>" onclick="return confirm('Reject this project?');">Rejected</button>
                            </form>
                        <?php else: ?>
                            <?php echo accountingDecisionView($decision); ?>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if (!empty($project['quotation_path'])): ?>
                            <a class="btn btn-sm btn-outline" target="_blank" href="<?php echo APP_URL . htmlspecialchars($project['quotation_path']); ?>">View file</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-primary" href="quotations.php?action=add&project=<?php echo (int) $project['id']; ?>">Quotation</a>
                        <a class="btn btn-sm btn-outline" href="contracts.php?action=add&project=<?php echo (int) $project['id']; ?>">Contract</a>
                        <a class="btn btn-sm btn-outline" href="labor_budget.php?action=add&project=<?php echo (int) $project['id']; ?>">Labor</a>
                        <a class="btn btn-sm btn-outline" href="funds_budget.php?action=add&project=<?php echo (int) $project['id']; ?>">Funds</a>
                        <a class="btn btn-sm btn-outline" href="assign_in_charge.php?project=<?php echo (int) $project['id']; ?>">Assign</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
