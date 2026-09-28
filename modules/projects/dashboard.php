<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Engineering Dashboard';

$stats = [
    'active_projects' => 0,
    'upcoming_projects' => 0,
    'ongoing_projects' => 0,
    'completed_projects' => 0,
    'total_budget' => 0,
    'actual_cost' => 0,
    'accomplishments' => 0,
    'attachments' => 0,
    'material_requirements' => 0,
];
$recent_projects = [];
$recent_accomplishments = [];

try {
    $stats['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    $stats['upcoming_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'planning' AND start_date > CURDATE()")->fetchColumn() ?? 0;
    $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
    $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
    $stats['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['actual_cost'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;

    try {
        $stats['accomplishments'] = $pdo->query("SELECT COUNT(*) FROM accomplishments")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        logActivity($_SESSION['user_id'] ?? null, 'Database error', 'Projects', $e->getMessage());
        $stats['accomplishments'] = 0;
    }

    try {
        $stats['attachments'] = $pdo->query("SELECT COUNT(*) FROM attachments")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        logActivity($_SESSION['user_id'] ?? null, 'Database error', 'Projects', $e->getMessage());
        $stats['attachments'] = 0;
    }

    $stats['material_requirements'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    if (isAdmin()) {
        $recent_projects = $pdo->query("SELECT id, project_code, name, status, location, start_date, end_date, in_charge_id FROM projects ORDER BY created_at DESC LIMIT 8")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, project_code, name, status, location, start_date, end_date, in_charge_id FROM projects WHERE in_charge_id = ? OR in_charge_id IS NULL ORDER BY (in_charge_id = ?) DESC, created_at DESC LIMIT 8");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $recent_projects = $stmt->fetchAll();
    }

    try {
        $recent_accomplishments = $pdo->query("
            SELECT a.*, p.project_code, p.name as project_name
            FROM accomplishments a
            LEFT JOIN projects p ON a.project_id = p.id
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (PDOException $e) {
        logActivity($_SESSION['user_id'] ?? null, 'Database error', 'Projects', $e->getMessage());
        $recent_accomplishments = [];
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

include '../../includes/header.php';
?>

<div class="dept-dash">
    <div class="dept-head">
        <div>
            <p class="dept-kicker">Engineering Department</p>
            <h1><i class="fas fa-hard-hat"></i> Engineering Dashboard</h1>
            <p class="dept-sub">Plan projects, track progress, and prepare material requirements.</p>
        </div>
        <div class="dept-actions">
            <a href="material_requirements.php" class="btn btn-primary"><i class="fas fa-clipboard-list"></i> Material Requirements</a>
            <a href="<?php echo APP_URL; ?>modules/procurement/purchase_requests.php?action=add" class="btn btn-outline"><i class="fas fa-file-invoice"></i> Submit to Procurement</a>
            <a href="accomplishments.php" class="btn btn-outline"><i class="fas fa-check-circle"></i> Accomplishments</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dept-kpis">
        <div class="dept-kpi green"><div class="ico"><i class="fas fa-play-circle"></i></div><div><div class="num"><?php echo number_format($stats['ongoing_projects']); ?></div><div class="lbl">Ongoing</div></div></div>
        <div class="dept-kpi blue"><div class="ico"><i class="fas fa-building"></i></div><div><div class="num"><?php echo number_format($stats['active_projects']); ?></div><div class="lbl">Active Projects</div></div></div>
        <div class="dept-kpi orange"><div class="ico"><i class="fas fa-clock"></i></div><div><div class="num"><?php echo number_format($stats['upcoming_projects']); ?></div><div class="lbl">Upcoming</div></div></div>
        <div class="dept-kpi purple"><div class="ico"><i class="fas fa-check-circle"></i></div><div><div class="num"><?php echo number_format($stats['completed_projects']); ?></div><div class="lbl">Completed</div></div></div>
        <div class="dept-kpi teal"><div class="ico"><i class="fas fa-coins"></i></div><div><div class="num">₱<?php echo number_format((float) $stats['total_budget'], 2); ?></div><div class="lbl">Total Budget</div></div></div>
        <div class="dept-kpi red"><div class="ico"><i class="fas fa-receipt"></i></div><div><div class="num">₱<?php echo number_format((float) $stats['actual_cost'], 2); ?></div><div class="lbl">Actual Cost</div></div></div>
        <div class="dept-kpi sky"><div class="ico"><i class="fas fa-clipboard-check"></i></div><div><div class="num"><?php echo number_format($stats['accomplishments']); ?></div><div class="lbl">Accomplishments</div></div></div>
        <div class="dept-kpi slate"><div class="ico"><i class="fas fa-paperclip"></i></div><div><div class="num"><?php echo number_format($stats['attachments']); ?></div><div class="lbl">Attachments</div></div></div>
        <div class="dept-kpi orange"><div class="ico"><i class="fas fa-boxes"></i></div><div><div class="num"><?php echo number_format($stats['material_requirements']); ?></div><div class="lbl">Low Stock Materials</div></div></div>
    </div>

    <div class="dept-grid">
        <div class="dept-panel">
            <h3><i class="fas fa-building"></i> Recent Projects</h3>
            <?php if (empty($recent_projects)): ?>
                <div class="empty-state compact"><i class="fas fa-building"></i><p>No projects yet.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="dept-table">
                    <thead><tr><th>Code</th><th>Name</th><th>Location</th><th>Status</th><th>End Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_projects as $project): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($project['name']); ?></td>
                            <td><?php echo htmlspecialchars($project['location'] ?? 'N/A'); ?></td>
                            <td><span class="dept-badge <?php echo htmlspecialchars($project['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></span></td>
                            <td><?php echo $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : 'N/A'; ?></td>
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
                <a class="t1" href="projects.php"><i class="fas fa-tasks"></i>Manage Projects</a>
                <a class="t2" href="material_requirements.php"><i class="fas fa-boxes"></i>Material Requirements</a>
                <a class="t3" href="accomplishments.php"><i class="fas fa-clipboard-check"></i>Accomplishments</a>
                <a class="t4" href="project_reports.php"><i class="fas fa-chart-bar"></i>Project Reports</a>
            </div>
            <?php if (!empty($recent_accomplishments)): ?>
            <h3 style="margin-top:1.1rem;"><i class="fas fa-clipboard-check"></i> Recent Accomplishments</h3>
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.65rem;">
                <?php foreach (array_slice($recent_accomplishments, 0, 4) as $accomplishment): ?>
                <li style="font-size:.8rem;color:#475569;">
                    <strong><?php echo htmlspecialchars(displayDocumentCode($accomplishment['project_code'] ?? 'N/A')); ?></strong>
                    — <?php echo htmlspecialchars(substr($accomplishment['description'] ?? '', 0, 48)); ?>
                    <div style="color:#94a3b8;font-size:.72rem;"><?php echo number_format((float) $accomplishment['completion_percentage'], 1); ?>% · <?php echo date('M d, Y', strtotime($accomplishment['created_at'])); ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
