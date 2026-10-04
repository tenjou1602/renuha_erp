<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_project_queue.php';

requireAdmin();

$page_title = 'Project Monitoring';

$projects = [];
try {
    $projects = adminProjectRows($pdo);
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

$active = array_values(array_filter($projects, function ($project) {
    return ($project['status'] ?? '') !== 'completed';
}));
$budget = 0.0;
$actual = 0.0;
$stage_groups = [];
foreach ($projects as $project) {
    $budget += (float) $project['estimated_budget'];
    $actual += (float) $project['cost_to_date'];
    $label = $project['stage']['label'];
    if (!isset($stage_groups[$label])) {
        $stage_groups[$label] = ['count' => 0, 'owner' => $project['stage']['owner'], 'tone' => $project['stage']['tone']];
    }
    $stage_groups[$label]['count']++;
}
$utilization = $budget > 0 ? ($actual / $budget) * 100 : 0;

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> Project Monitoring</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="project_management.php" class="btn btn-secondary"><i class="fas fa-stream"></i> Project Workflow</a>
        <a href="consolidated_reports.php" class="btn btn-secondary"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="executive_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>
</div>

<?php adminRenderWorkQueue($projects, 'project_management.php?action=add', 'Create / enroll project'); ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="aq-kpis">
    <div class="aq-kpi blue">
        <div class="num"><?php echo number_format(count($active)); ?></div>
        <div class="lbl">ACTIVE PROJECTS</div>
    </div>
    <div class="aq-kpi orange">
        <div class="num">₱<?php echo number_format($budget, 2); ?></div>
        <div class="lbl">APPROVED / ESTIMATED BUDGET</div>
    </div>
    <div class="aq-kpi orange">
        <div class="num">₱<?php echo number_format($actual, 2); ?></div>
        <div class="lbl">ACTUAL COST TO DATE</div>
    </div>
    <div class="aq-kpi green">
        <div class="num"><?php echo number_format($utilization, 1); ?>%</div>
        <div class="lbl">BUDGET UTILIZATION</div>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-layer-group"></i> Projects by Workflow Stage</h3>
    <?php if (empty($stage_groups)): ?>
        <div class="aq-empty"><i class="fas fa-folder-open"></i>No projects to group yet.</div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;">
            <?php foreach ($stage_groups as $label => $group): ?>
                <div style="border:1px solid #e8eef5;border-radius:14px;padding:1rem;">
                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.03em;color:#64748b;"><?php echo htmlspecialchars(strtoupper($label)); ?></div>
                    <div style="font-size:1.6rem;font-weight:800;margin:.2rem 0;"><?php echo (int) $group['count']; ?></div>
                    <div style="color:#64748b;font-size:.85rem;"><?php echo htmlspecialchars($group['owner']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3><i class="fas fa-list"></i> Active Project Monitoring</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Client / Location</th>
                    <th>Stage</th>
                    <th>Progress</th>
                    <th>Budget / Actual</th>
                    <th>Engineer</th>
                    <th>Dates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($active)): ?>
                    <tr><td colspan="8"><div class="aq-empty"><i class="fas fa-hard-hat"></i>No active projects found.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($active as $project): ?>
                    <?php
                        $start = adminFormatDate($project['start_date'] ?? '');
                        $end = adminFormatDate($project['end_date'] ?? '');
                        $dates = trim($start . ($start && $end ? ' – ' : '') . $end);
                    ?>
                    <tr id="project-<?php echo (int) $project['id']; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong>
                            <div style="color:#64748b;font-size:.8rem;"><?php echo htmlspecialchars($project['name']); ?></div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($project['client'] ?: 'No client'); ?>
                            <div style="color:#64748b;font-size:.8rem;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($project['location'] ?: 'No location'); ?></div>
                        </td>
                        <td><span class="aq-stage <?php echo htmlspecialchars($project['stage']['tone']); ?>"><?php echo htmlspecialchars($project['stage']['label']); ?></span></td>
                        <td style="min-width:120px;">
                            <div style="display:flex;align-items:center;gap:.4rem;">
                                <div style="flex:1;height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
                                    <span style="display:block;height:100%;width:<?php echo (int) $project['progress_pct']; ?>%;background:#3b82f6;"></span>
                                </div>
                                <span><?php echo (int) $project['progress_pct']; ?>%</span>
                            </div>
                        </td>
                        <td>
                            ₱<?php echo number_format((float) $project['estimated_budget'], 2); ?>
                            <div style="color:#64748b;font-size:.8rem;">Actual: ₱<?php echo number_format((float) $project['cost_to_date'], 2); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($project['engineer_name'] ?: 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($dates !== '' ? $dates : '—'); ?></td>
                        <td>
                            <div style="display:flex;gap:.3rem;">
                                <a href="project_management.php?action=view&id=<?php echo (int) $project['id']; ?>" class="btn btn-sm btn-secondary" title="Project"><i class="fas fa-eye"></i></a>
                                <a href="../projects/project_details.php?id=<?php echo (int) $project['id']; ?>" class="btn btn-sm btn-info" title="Details"><i class="fas fa-external-link-alt"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
