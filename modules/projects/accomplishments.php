<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Accomplishments';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_accomplishment'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: accomplishments.php');
        exit();
    }
    $project_id = (int)($_POST['project_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $stage = $_POST['progress_status'] ?? '';
    $allowedStages = ['planning', 'ongoing', 'on_hold', 'completed'];
    if ($stage === 'planning') {
        $pct = 0.0;
    } elseif ($stage === 'completed') {
        $pct = 100.0;
    } elseif ($stage === 'on_hold') {
        $latest = $pdo->prepare("SELECT completion_percentage FROM accomplishments WHERE project_id = ? ORDER BY id DESC LIMIT 1");
        $latest->execute([$project_id]);
        $pct = (float) $latest->fetchColumn();
    } else {
        $pct = (float)($_POST['completion_percentage'] ?? 0);
    }
    if ($project_id <= 0 || $description === '') {
        $_SESSION['error'] = 'Project and description are required.';
    } elseif (!in_array($stage, $allowedStages, true)) {
        $_SESSION['error'] = 'Choose Planning, Ongoing, On Hold, or Completed.';
    } elseif ($pct < 0 || $pct > 100) {
        $_SESSION['error'] = 'Completion must be between 0 and 100.';
    } elseif ($stage === 'completed' && ($completionBlock = projectCompletionBlocker($pdo, $project_id))) {
        $_SESSION['error'] = $completionBlock;
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO accomplishments (project_id, description, completion_percentage, status, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_id, $description, $pct, $stage, $_SESSION['user_id']]);
            $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?")->execute([$stage, $project_id]);
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'Added accomplishment', 'Projects', "Project ID: $project_id");
            $_SESSION['success'] = 'Accomplishment recorded. Project status is now ' . str_replace('_', ' ', $stage) . ' at ' . number_format($pct, 1) . '%.';
            header('Location: accomplishments.php');
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = userDatabaseError($e, 'Projects');
        }
    }
}

if (isset($_GET['delete_id'])) {
    if (!canDelete('projects')) {
        $_SESSION['error'] = 'You cannot delete accomplishments.';
        header('Location: accomplishments.php');
        exit();
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM accomplishments WHERE id = ?");
        $stmt->execute([(int)$_GET['delete_id']]);
        logActivity($_SESSION['user_id'], 'Deleted accomplishment', 'Projects', 'ID: ' . (int)$_GET['delete_id']);
        $_SESSION['success'] = 'Accomplishment deleted.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e, 'Projects');
    }
    header('Location: accomplishments.php');
    exit();
}

$projects = [];
$records = [];
try {
    $projects = $pdo->query("
        SELECT p.id, p.project_code, p.name, p.status,
               (SELECT a.completion_percentage FROM accomplishments a WHERE a.project_id = p.id ORDER BY a.id DESC LIMIT 1) AS progress_percent
        FROM projects p
        ORDER BY p.created_at DESC
    ")->fetchAll();
    $records = $pdo->query("
        SELECT a.*, p.project_code, p.name AS project_name, u.full_name AS created_by_name
        FROM accomplishments a
        LEFT JOIN projects p ON a.project_id = p.id
        LEFT JOIN users u ON a.created_by = u.id
        ORDER BY a.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-check"></i> Accomplishments</h1>
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

<?php if (canWriteDepartmentData('projects')): ?>
<div class="card">
    <h3><i class="fas fa-plus"></i> Record accomplishment</h3>
    <form method="POST">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int) $project['id']; ?>" data-progress="<?php echo $project['progress_percent'] === null ? '' : htmlspecialchars((string) $project['progress_percent']); ?>"><?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="progress_status" id="progressStatus" required>
                <option value="planning">Planning</option>
                <option value="ongoing">Ongoing</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
            </select>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" required></textarea>
        </div>
        <div class="form-group">
            <label>Completion %</label>
            <input type="number" name="completion_percentage" id="completionPercent" min="0" max="100" step="0.1" value="0" required>
            <small style="color:#64748b;">Planning is 0%. Completed is 100%. On Hold keeps the current percent. Ongoing uses the percent you enter.</small>
        </div>
        <button type="submit" name="add_accomplishment" class="btn btn-primary">Save</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>Project accomplishments</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Project</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Completion</th>
                    <th>Recorded by</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="table-empty">No accomplishments yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($row['project_code'] ?? 'N/A')); ?></strong><br><?php echo htmlspecialchars($row['project_name'] ?? ''); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
                        <td>
                            <?php
                            $rowStage = $row['status'] ?? '';
                            if (!in_array($rowStage, ['planning', 'ongoing', 'on_hold', 'completed'], true)) {
                                $rowPct = (float) $row['completion_percentage'];
                                $rowStage = $rowPct >= 100 ? 'completed' : ($rowPct <= 0 ? 'planning' : 'ongoing');
                            }
                            $rowLabel = ['planning' => 'Planning', 'ongoing' => 'Ongoing', 'on_hold' => 'On Hold', 'completed' => 'Completed'][$rowStage];
                            ?>
                            <span class="badge badge-<?php echo htmlspecialchars($rowStage); ?>"><?php echo $rowLabel; ?></span>
                        </td>
                        <td><?php echo number_format((float)$row['completion_percentage'], 1); ?>%</td>
                        <td><?php echo htmlspecialchars($row['created_by_name'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="project_details.php?id=<?php echo (int)$row['project_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (canDelete('projects')): ?>
                            <a href="accomplishments.php?delete_id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this accomplishment?');"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const status = document.getElementById('progressStatus');
    const percent = document.getElementById('completionPercent');
    const project = document.querySelector('select[name="project_id"]');
    if (!status || !percent || !project) return;
    function currentProgress() {
        const option = project.selectedOptions[0];
        const value = option ? option.dataset.progress : '';
        return value === undefined || value === '' ? '0' : value;
    }
    function applyStatus() {
        if (status.value === 'planning') {
            percent.value = '0';
            percent.readOnly = true;
        } else if (status.value === 'completed') {
            percent.value = '100';
            percent.readOnly = true;
        } else if (status.value === 'on_hold') {
            percent.value = currentProgress();
            percent.readOnly = true;
        } else {
            percent.readOnly = false;
            if (percent.value === '0' || percent.value === '100') {
                const current = currentProgress();
                percent.value = current === '0' ? '' : current;
            }
        }
    }
    status.addEventListener('change', applyStatus);
    project.addEventListener('change', applyStatus);
    applyStatus();
})();
</script>
<?php include '../../includes/footer.php'; ?>
