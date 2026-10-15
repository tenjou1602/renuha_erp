<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Received Projects';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_project'])) {
    if (!canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
    } else {
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE projects SET accounting_received_at = NOW(), accounting_status = 'received' WHERE id = ? AND accounting_status = 'pending'");
        $stmt->execute([$projectId]);
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("UPDATE material_requests SET flow_status = 'warehouse' WHERE project_id = ? AND flow_status = 'accounting'")->execute([$projectId]);
            $pdo->prepare("UPDATE purchase_orders po INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id SET po.status = 'ready_for_warehouse' WHERE pr.project_id = ? AND po.status IN ('sent', 'confirmed')")->execute([$projectId]);
            notifyAccountingReceived($projectId);
            $_SESSION['success'] = 'Project received. You can record the quotation, budget, contract, and expenses.';
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
        WHERE accounting_status IN ('pending', 'received')
        ORDER BY (accounting_status = 'pending') DESC, created_at DESC
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

<p style="color:#64748b;">Projects encoded by Executive Admin appear here. Receive the project, then create the quotation from the attached file.</p>

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
                        <?php if (($project['accounting_status'] ?? '') === 'pending'): ?>
                            <span class="badge badge-pending">New</span>
                        <?php else: ?>
                            <span class="badge badge-approved">Received</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($project['client'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($project['location'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($project['subject'] ?: '—'); ?></td>
                    <td>₱<?php echo number_format((float) $project['estimated_budget'], 2); ?></td>
                    <td><span class="badge badge-<?php echo htmlspecialchars($project['status']); ?>"><?php echo ucfirst($project['status']); ?></span></td>
                    <td style="white-space:nowrap;">
                        <?php if (!empty($project['quotation_path'])): ?>
                            <a class="btn btn-sm btn-outline" target="_blank" href="<?php echo APP_URL . htmlspecialchars($project['quotation_path']); ?>">View file</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-primary" href="quotations.php?action=add&project=<?php echo (int) $project['id']; ?>">Quotation</a>
                        <a class="btn btn-sm btn-outline" href="contracts.php?action=add&project=<?php echo (int) $project['id']; ?>">Contract</a>
                        <a class="btn btn-sm btn-outline" href="labor_budget.php?action=add&project=<?php echo (int) $project['id']; ?>">Labor</a>
                        <a class="btn btn-sm btn-outline" href="funds_budget.php?action=add&project=<?php echo (int) $project['id']; ?>">Funds</a>
                        <a class="btn btn-sm btn-outline" href="assign_in_charge.php?project=<?php echo (int) $project['id']; ?>">Assign</a>
                        <?php if (($project['accounting_status'] ?? '') === 'pending' && canWriteDepartmentData('accounting')): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="receive_project" value="1">
                                <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">Receive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
