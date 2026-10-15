<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_project_queue.php';

requireAdmin();

$page_title = 'Projects';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_project']) || isset($_POST['update_project']))) {
    $project_code = trim($_POST['project_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $client = trim($_POST['client'] ?? '');
    $start_date = nullableDate($_POST['start_date'] ?? '');
    $end_date = nullableDate($_POST['end_date'] ?? '');
    $status = $_POST['status'] ?? 'approved';
    $estimated_budget = (float) ($_POST['estimated_budget'] ?? 0);
    $assigned_to = (int) ($_POST['assigned_to'] ?? 0) ?: null;
    $allowed_status = ['draft', 'quotation', 'approved', 'accounting_setup', 'assigned', 'planning', 'material_request', 'procurement', 'material_available', 'implementation', 'ongoing', 'completed', 'on_hold'];
    if (!in_array($status, $allowed_status, true)) {
        $status = 'approved';
    }

    $errors = [];
    if ($name === '') {
        $errors[] = 'Project name is required.';
    }
    if ($estimated_budget <= 0) {
        $errors[] = 'Estimated budget must be greater than 0.';
    }
    if ($start_date !== null && $end_date !== null && $end_date < $start_date) {
        $errors[] = 'End date must be on or after the start date.';
    }

    if (empty($errors)) {
        try {
            if (isset($_POST['add_project'])) {
                if ($project_code === '') {
                    $project_code = generateProjectCode();
                }
                $stmt = $pdo->prepare("INSERT INTO projects (project_code, name, description, location, client, start_date, end_date, status, estimated_budget, created_by, assigned_to, in_charge_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$project_code, $name, $description, $location, $client, $start_date, $end_date, $status, $estimated_budget, $_SESSION['user_id'], $assigned_to, $assigned_to]);
                logActivity($_SESSION['user_id'], 'Enrolled project', 'Projects', "Project: $project_code");
                $_SESSION['success'] = 'Project enrolled successfully.';
            } else {
                $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, location = ?, client = ?, start_date = ?, end_date = ?, status = ?, estimated_budget = ?, assigned_to = ?, in_charge_id = ? WHERE id = ?");
                $stmt->execute([$name, $description, $location, $client, $start_date, $end_date, $status, $estimated_budget, $assigned_to, $assigned_to, $id]);
                logActivity($_SESSION['user_id'], 'Updated project', 'Projects', "Project: $project_code");
                $_SESSION['success'] = 'Project updated successfully.';
            }
            header('Location: project_management.php');
            exit();
        } catch (PDOException $e) {
            $error = userDatabaseError($e, 'Projects');
        }
    } else {
        $error = implode(' ', $errors);
    }
}

$projects = [];
try {
    $projects = adminProjectRows($pdo);
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

$project_details = null;
if ($action === 'edit' || $action === 'view') {
    foreach ($projects as $project) {
        if ((int) $project['id'] === $id) {
            $project_details = $project;
            break;
        }
    }
    if (!$project_details) {
        $_SESSION['error'] = 'Project not found.';
        header('Location: project_management.php');
        exit();
    }
}

$engineering_users = [];
try {
    $engineering_users = $pdo->query("SELECT id, full_name FROM users WHERE department = 'engineering' AND status = 'active' ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    $engineering_users = [];
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-tasks"></i> Projects</h1>
</div>

<?php adminRenderWorkQueue($projects, 'project_management.php?action=add', 'Create / enroll project'); ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $project_details)): ?>
<div class="card">
    <h3><?php echo $action === 'add' ? 'Enroll approved project' : 'Update project'; ?></h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_project" value="1">
            <input type="hidden" name="project_code" value="<?php echo htmlspecialchars($project_details['project_code']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_project" value="1">
            <input type="hidden" name="project_code" value="<?php echo htmlspecialchars(generateProjectCode()); ?>">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label>Project Code</label>
                <input type="text" value="<?php echo htmlspecialchars($action === 'edit' ? displayDocumentCode($project_details['project_code']) : generateProjectCode()); ?>" disabled style="background:#f1f5f9;">
            </div>
            <div class="form-group">
                <label class="required">Project Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($project_details['name'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($project_details['location'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Client</label>
                <input type="text" name="client" value="<?php echo htmlspecialchars($project_details['client'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Project In-Charge</label>
                <select name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php
                    $current_assigned = (int) ($project_details['assigned_to'] ?? $project_details['in_charge_id'] ?? 0);
                    foreach ($engineering_users as $eng):
                    ?>
                        <option value="<?php echo (int) $eng['id']; ?>" <?php echo $current_assigned === (int) $eng['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($eng['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars(adminFormatDate($project_details['start_date'] ?? '') ? ($project_details['start_date'] ?? '') : ''); ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars(adminFormatDate($project_details['end_date'] ?? '') ? ($project_details['end_date'] ?? '') : ''); ?>">
            </div>
            <div class="form-group">
                <label>Workflow Status</label>
                <?php $current_status = $project_details['status'] ?? 'approved'; ?>
                <select name="status">
                    <?php foreach (['approved' => 'Approved / Budget setup', 'planning' => 'Budget allocated / Planning', 'procurement' => 'Negotiate & Purchase', 'ongoing' => 'Implementation', 'completed' => 'Completed', 'on_hold' => 'On hold'] as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $current_status === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Estimated Budget (₱)</label>
            <input type="number" step="0.01" min="0" name="estimated_budget" value="<?php echo htmlspecialchars((string) ($project_details['estimated_budget'] ?? '0')); ?>">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"><?php echo htmlspecialchars($project_details['description'] ?? ''); ?></textarea>
        </div>
        <div style="margin-top:1.25rem;display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Enroll project' : 'Save project'; ?></button>
            <a href="project_management.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $project_details): ?>
<div class="card">
    <h3><?php echo htmlspecialchars(displayDocumentCode($project_details['project_code'])); ?> · <?php echo htmlspecialchars($project_details['name']); ?></h3>
    <div class="view-details">
        <div class="detail-row"><span>Workflow stage:</span> <span class="aq-stage <?php echo htmlspecialchars($project_details['stage']['tone']); ?>"><?php echo htmlspecialchars($project_details['stage']['label']); ?></span></div>
        <div class="detail-row"><span>Client:</span> <?php echo htmlspecialchars($project_details['client'] ?: 'N/A'); ?></div>
        <div class="detail-row"><span>Location:</span> <?php echo htmlspecialchars($project_details['location'] ?: 'N/A'); ?></div>
        <div class="detail-row"><span>Engineer:</span> <?php echo htmlspecialchars($project_details['engineer_name'] ?: 'Unassigned'); ?></div>
        <div class="detail-row"><span>Status:</span> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $project_details['status']))); ?></div>
        <div class="detail-row"><span>Estimated budget:</span> ₱<?php echo number_format((float) $project_details['estimated_budget'], 2); ?></div>
        <div class="detail-row"><span>Actual cost to date:</span> ₱<?php echo number_format((float) $project_details['cost_to_date'], 2); ?></div>
        <div class="detail-row"><span>Progress:</span> <?php echo (int) $project_details['progress_pct']; ?>%</div>
    </div>
    <div style="margin-top:1.25rem;display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="?action=edit&id=<?php echo (int) $project_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <a href="project_monitoring.php#project-<?php echo (int) $project_details['id']; ?>" class="btn btn-info"><i class="fas fa-chart-line"></i> Monitoring</a>
        <a href="../projects/project_details.php?id=<?php echo (int) $project_details['id']; ?>" class="btn btn-success"><i class="fas fa-folder-open"></i> Full details</a>
        <a href="project_management.php" class="btn btn-secondary">Back</a>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Projects (<?php echo count($projects); ?>)</h3>
        <input type="text" id="searchProject" placeholder="Search projects..." style="padding:.45rem .8rem;border:1px solid var(--border-color);border-radius:8px;font-size:.85rem;">
    </div>
    <div class="table-responsive">
        <table class="table" id="projectTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Budget</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="6"><div class="aq-empty"><i class="fas fa-building"></i>No projects found.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($project['name']); ?></td>
                        <td><?php echo htmlspecialchars($project['location'] ?: 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars(str_replace('_', '-', $project['status'])); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $project['status']))); ?></span></td>
                        <td>₱<?php echo number_format((float) $project['estimated_budget'], 2); ?></td>
                        <td>
                            <div style="display:flex;gap:.3rem;">
                                <a href="?action=view&id=<?php echo (int) $project['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="project_monitoring.php#project-<?php echo (int) $project['id']; ?>" class="btn btn-sm btn-success" title="Monitor"><i class="fas fa-chart-line"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.getElementById('searchProject')?.addEventListener('keyup', function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#projectTable tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
