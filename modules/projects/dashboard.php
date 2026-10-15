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
    try {
        syncDepartmentWorkflow($pdo);
    } catch (Throwable $e) {
        error_log('[Runeha ERP] department sync: ' . $e->getMessage());
    }
    $stats['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    $stats['upcoming_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'planning' AND start_date > CURDATE()")->fetchColumn() ?? 0;
    $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
    $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
    $stats['total_budget'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['actual_cost'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM v_project_costs")->fetchColumn() ?? 0;
    $stats['pending_cost'] = $pdo->query("SELECT COALESCE(SUM(pending_cost), 0) FROM v_project_costs")->fetchColumn() ?? 0;

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

    $stats['material_requirements'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status IN ('pending', 'approved', 'confirmed')")->fetchColumn() ?? 0;
    $stats['materials_received'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status IN ('received', 'completed')")->fetchColumn() ?? 0;
    $stats['assigned_projects'] = $stats['active_projects'];
    try {
        $stats['project_progress'] = $pdo->query("SELECT COALESCE(AVG(completion_percentage), 0) FROM accomplishments")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['project_progress'] = 0;
    }
    if (!isAdmin()) {
        $assignedStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE assigned_to = ? OR in_charge_id = ?");
        $assignedStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $stats['assigned_projects'] = $assignedStmt->fetchColumn() ?? 0;
        $requestStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requests WHERE requestor_id = ? AND status IN ('pending', 'approved', 'confirmed')");
        $requestStmt->execute([$_SESSION['user_id']]);
        $stats['material_requirements'] = $requestStmt->fetchColumn() ?? 0;
    }
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
            <p class="dept-sub">Assigned projects, progress, material requests, and completed work.</p>
        </div>
        <div class="dept-actions">
            <a href="received_projects.php" class="btn btn-primary"><i class="fas fa-inbox"></i> Received Projects</a>
            <a href="material_requirements.php" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> Project Requirements</a>
            <a href="accomplishments.php" class="btn btn-outline"><i class="fas fa-clipboard-check"></i> Progress Updates</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dept-kpis">
        <a class="dept-kpi blue" href="received_projects.php"><div class="ico"><i class="fas fa-inbox"></i></div><div><div class="num"><?php echo number_format($stats['assigned_projects']); ?></div><div class="lbl">Received Projects</div></div></a>
        <a class="dept-kpi sky" href="accomplishments.php"><div class="ico"><i class="fas fa-clipboard-check"></i></div><div><div class="num"><?php echo number_format((float) $stats['project_progress'], 0); ?>%</div><div class="lbl">Project Progress</div></div></a>
        <a class="dept-kpi orange" href="received_projects.php?view=requests"><div class="ico"><i class="fas fa-paper-plane"></i></div><div><div class="num"><?php echo number_format($stats['material_requirements']); ?></div><div class="lbl">Material Requests</div></div></a>
        <a class="dept-kpi green" href="received_projects.php?view=requests"><div class="ico"><i class="fas fa-boxes"></i></div><div><div class="num"><?php echo number_format($stats['materials_received']); ?></div><div class="lbl">Materials Received</div></div></a>
        <a class="dept-kpi purple" href="project_details.php"><div class="ico"><i class="fas fa-hard-hat"></i></div><div><div class="num"><?php echo number_format($stats['ongoing_projects']); ?> / <?php echo number_format($stats['completed_projects']); ?></div><div class="lbl">Ongoing & Completed Projects</div></div></a>
    </div>

    <div class="card">
        <h3><i class="fas fa-chart-bar"></i> Project monitoring</h3>
        <div class="chart-compact"><canvas id="engChart"></canvas></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('engChart'), {
        type: 'bar',
        data: {
            labels: ['Received', 'Material requests', 'Materials received', 'Ongoing', 'Completed'],
            datasets: [{
                data: [
                    <?php echo (int) $stats['assigned_projects']; ?>,
                    <?php echo (int) $stats['material_requirements']; ?>,
                    <?php echo (int) $stats['materials_received']; ?>,
                    <?php echo (int) $stats['ongoing_projects']; ?>,
                    <?php echo (int) $stats['completed_projects']; ?>
                ],
                backgroundColor: ['#4e73df', '#f6c23e', '#1cc88a', '#36b9cc', '#858796']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
    </script>

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
                            <td><?php echo formatDisplayDate($project['end_date'] ?? null); ?></td>
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
                <a class="t1" href="received_projects.php"><i class="fas fa-inbox"></i>Received Projects</a>
                <a class="t2" href="project_details.php"><i class="fas fa-drafting-compass"></i>Project Planning</a>
                <a class="t3" href="material_requirements.php"><i class="fas fa-clipboard-list"></i>Project Requirements</a>
                <a class="t4" href="project_reports.php"><i class="fas fa-chart-bar"></i>Accomplishment Reports</a>
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
