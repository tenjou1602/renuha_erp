<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Project Financials';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_financials'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: project_financials.php');
        exit();
    }
    $project_id = (int)($_POST['project_id'] ?? 0);
    $contract_amount = (float)($_POST['contract_amount'] ?? 0);
    $additional_budget = (float)($_POST['additional_budget'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    if ($project_id <= 0) {
        $_SESSION['error'] = 'Select a project.';
        header('Location: project_financials.php?action=add');
        exit();
    }
    try {
        $stmt = $pdo->prepare("
            INSERT INTO project_financials (project_id, contract_amount, additional_budget, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                contract_amount = VALUES(contract_amount),
                additional_budget = VALUES(additional_budget),
                notes = VALUES(notes),
                recorded_by = VALUES(recorded_by)
        ");
        $stmt->execute([$project_id, $contract_amount, $additional_budget, $notes !== '' ? $notes : null, $_SESSION['user_id']]);
        logActivity($_SESSION['user_id'], 'Saved project financials', 'Projects', 'Project ID ' . $project_id);
        $_SESSION['success'] = 'Project financials saved.';
        header('Location: project_financials.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e, 'Projects');
    }
}

$rows = [];
$projects = [];
$edit_row = null;
try {
    $projects = $pdo->query("SELECT id, project_code, name FROM projects ORDER BY name")->fetchAll();
    $rows = $pdo->query("
        SELECT p.id, p.project_code, p.name, p.status, p.estimated_budget, p.actual_cost,
               pf.id AS financial_id, pf.contract_amount, pf.additional_budget, pf.notes, pf.updated_at,
               (p.estimated_budget + COALESCE(pf.additional_budget, 0) - p.actual_cost) AS variance
        FROM projects p
        LEFT JOIN project_financials pf ON pf.project_id = p.id
        ORDER BY p.created_at DESC
    ")->fetchAll();
    if (($action === 'edit' || $action === 'add') && $id > 0) {
        foreach ($rows as $row) {
            if ((int)$row['id'] === $id) {
                $edit_row = $row;
                break;
            }
        }
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('projects')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: project_financials.php');
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Project Financials</h1>
    <?php if ($action === 'list' && canWriteDepartmentData('projects')): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Encode Financials</a>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card">
    <h3><?php echo $action === 'edit' ? 'Update' : 'Encode'; ?> contract and budget</h3>
    <form method="POST">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int)$project['id']; ?>" <?php echo ((int)($edit_row['id'] ?? 0) === (int)$project['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($project['project_code'] . ' — ' . $project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Contract amount</label>
            <input type="number" name="contract_amount" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($edit_row['contract_amount'] ?? '0.00')); ?>" required>
        </div>
        <div class="form-group">
            <label>Additional budget</label>
            <input type="number" name="additional_budget" step="0.01" min="0" value="<?php echo htmlspecialchars((string)($edit_row['additional_budget'] ?? '0.00')); ?>">
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"><?php echo htmlspecialchars($edit_row['notes'] ?? ''); ?></textarea>
        </div>
        <button type="submit" name="save_financials" class="btn btn-primary">Save</button>
        <a href="project_financials.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Project</th>
                    <th>Contract</th>
                    <th>Budget</th>
                    <th>Additional</th>
                    <th>Actual cost</th>
                    <th>Variance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="table-empty">No projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($row['project_code'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td>₱<?php echo number_format((float)($row['contract_amount'] ?? 0), 2); ?></td>
                        <td>₱<?php echo number_format((float)$row['estimated_budget'], 2); ?></td>
                        <td>₱<?php echo number_format((float)($row['additional_budget'] ?? 0), 2); ?></td>
                        <td>₱<?php echo number_format((float)$row['actual_cost'], 2); ?></td>
                        <td>₱<?php echo number_format((float)$row['variance'], 2); ?></td>
                        <td>
                            <?php if (canWriteDepartmentData('projects')): ?>
                            <a href="?action=edit&id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
