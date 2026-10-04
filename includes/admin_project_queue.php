<?php

function adminFormatDate($value): string
{
    $value = trim((string) $value);
    if ($value === '' || strncmp($value, '0000', 4) === 0) {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('M d, Y', $ts) : '';
}

function adminProjectStage(array $project): array
{
    $status = (string) ($project['status'] ?? 'planning');
    $budget = (float) ($project['estimated_budget'] ?? 0);

    if (in_array($status, ['draft', 'approved', 'accounting_setup'], true) || ($status === 'planning' && $budget <= 0)) {
        return ['label' => 'Approved / Budget setup', 'owner' => 'Accounting', 'tone' => 'green'];
    }
    if (in_array($status, ['planning', 'assigned'], true)) {
        return ['label' => 'Budget allocated / Planning', 'owner' => 'Engineering', 'tone' => 'purple'];
    }
    if (in_array($status, ['quotation', 'material_request', 'procurement'], true)) {
        return ['label' => 'Negotiate & Purchase', 'owner' => 'Procurement', 'tone' => 'orange'];
    }
    if ($status === 'material_available') {
        return ['label' => 'Materials available', 'owner' => 'Warehouse', 'tone' => 'teal'];
    }
    if (in_array($status, ['implementation', 'ongoing'], true)) {
        return ['label' => 'Implementation', 'owner' => 'Engineering', 'tone' => 'blue'];
    }
    if ($status === 'completed') {
        return ['label' => 'Completed', 'owner' => 'Engineering', 'tone' => 'green'];
    }
    if ($status === 'on_hold') {
        return ['label' => 'On hold', 'owner' => 'Admin', 'tone' => 'slate'];
    }

    return ['label' => ucwords(str_replace('_', ' ', $status)), 'owner' => 'Engineering', 'tone' => 'slate'];
}

function adminProjectProgress(array $project): int
{
    if (isset($project['progress']) && is_numeric($project['progress'])) {
        return max(0, min(100, (int) $project['progress']));
    }
    $status = (string) ($project['status'] ?? '');
    if ($status === 'completed') {
        return 100;
    }
    if (!in_array($status, ['ongoing', 'implementation'], true)) {
        return 0;
    }
    $start = adminFormatDate($project['start_date'] ?? '');
    $end = adminFormatDate($project['end_date'] ?? '');
    if ($start === '' || $end === '') {
        return 0;
    }
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if (!$startTs || !$endTs || $endTs <= $startTs) {
        return 0;
    }
    $now = time();
    if ($now <= $startTs) {
        return 0;
    }
    if ($now >= $endTs) {
        return 100;
    }
    return (int) round((($now - $startTs) / ($endTs - $startTs)) * 100);
}

function adminProjectRows(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT p.*,
               COALESCE(ua.full_name, ui.full_name) AS engineer_name,
               COALESCE((
                   SELECT SUM(e.amount)
                   FROM expenses e
                   WHERE e.project_id = p.id AND e.status <> 'cancelled'
               ), 0) AS expense_total
        FROM projects p
        LEFT JOIN users ua ON p.assigned_to = ua.id
        LEFT JOIN users ui ON p.in_charge_id = ui.id
        ORDER BY p.created_at DESC
    ")->fetchAll();

    foreach ($rows as &$row) {
        $stored = (float) ($row['actual_cost'] ?? 0);
        $expenses = (float) ($row['expense_total'] ?? 0);
        $row['cost_to_date'] = $stored > 0 ? $stored : $expenses;
        $row['stage'] = adminProjectStage($row);
        $row['progress_pct'] = adminProjectProgress($row);
    }
    unset($row);

    return $rows;
}

function adminRecentActivity(PDO $pdo, ?string $module = null, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    if ($module === null || $module === '') {
        $stmt = $pdo->query("
            SELECT a.*, u.full_name, u.department
            FROM activity_log a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.action <> 'Database error'
            ORDER BY a.created_at DESC
            LIMIT {$limit}
        ");
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.department
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.module = ? AND a.action <> 'Database error'
        ORDER BY a.created_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$module]);
    return $stmt->fetchAll();
}

function adminRenderWorkQueue(array $projects, string $actionHref, string $actionLabel): void
{
    ?>
    <style>
    .aq-queue { background:#fff; border:1px solid #e8eef5; border-left:4px solid #14b8a6; border-radius:16px; padding:1rem 1.1rem; margin-bottom:1.1rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; box-shadow:0 8px 24px rgba(15,23,42,.04); }
    .aq-queue h2 { margin:0 .4rem 0 0; font-size:.95rem; font-weight:700; color:#0f172a; white-space:nowrap; }
    .aq-chips { display:flex; gap:.55rem; flex-wrap:wrap; flex:1; }
    .aq-chip { display:inline-flex; align-items:center; gap:.35rem; background:#eef2f6; color:#334155; border-radius:999px; padding:.45rem .8rem; font-size:.78rem; font-weight:600; text-decoration:none; }
    .aq-chip:hover { background:#e2e8f0; color:#0f172a; }
    .aq-chip strong { font-weight:700; }
    .aq-action { margin-left:auto; background:#111827; color:#fff !important; border-radius:12px; padding:.65rem 1rem; font-size:.85rem; font-weight:700; text-decoration:none; white-space:nowrap; }
    .aq-action:hover { background:#1f2937; }
    .aq-stage { display:inline-flex; align-items:center; border-radius:999px; padding:.2rem .6rem; font-size:.72rem; font-weight:700; }
    .aq-stage.green { background:#d1fae5; color:#047857; }
    .aq-stage.purple { background:#ede9fe; color:#6d28d9; }
    .aq-stage.orange { background:#ffedd5; color:#c2410c; }
    .aq-stage.teal { background:#ccfbf1; color:#0f766e; }
    .aq-stage.blue { background:#dbeafe; color:#1d4ed8; }
    .aq-stage.slate { background:#f1f5f9; color:#475569; }
    .aq-pill { display:inline-flex; align-items:center; gap:.4rem; background:#f59e0b; color:#1a1a2e; border-radius:999px; padding:.4rem .85rem; font-size:.8rem; font-weight:700; margin-bottom:1rem; }
    .aq-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.9rem; margin-bottom:1rem; }
    .aq-kpi { background:#fff; border-radius:16px; padding:1rem 1.1rem; border:1px solid #eef2f7; box-shadow:0 8px 24px rgba(15,23,42,.04); }
    .aq-kpi .num { font-size:1.35rem; font-weight:800; color:#0f172a; overflow-wrap:anywhere; }
    .aq-kpi .lbl { margin-top:.25rem; font-size:.72rem; font-weight:700; letter-spacing:.04em; color:#64748b; }
    .aq-kpi.blue { box-shadow:inset 0 0 0 1px #dbeafe; }
    .aq-kpi.orange { box-shadow:inset 0 0 0 1px #ffedd5; }
    .aq-kpi.green { box-shadow:inset 0 0 0 1px #d1fae5; }
    .aq-empty { text-align:center; color:#94a3b8; padding:2.2rem 1rem; }
    .aq-empty i { font-size:1.6rem; display:block; margin-bottom:.45rem; }
    @media (max-width: 900px) { .aq-kpis { grid-template-columns:1fr 1fr; } .aq-action { margin-left:0; } }
    </style>
    <div class="aq-queue">
        <h2>Project work queue</h2>
        <div class="aq-chips">
            <?php if (empty($projects)): ?>
                <span class="aq-chip">No projects enrolled yet</span>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <a class="aq-chip" href="<?php echo APP_URL; ?>modules/admin/project_monitoring.php#project-<?php echo (int) $project['id']; ?>">
                        <strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong>
                        · <?php echo htmlspecialchars($project['name']); ?>
                        — <?php echo htmlspecialchars($project['stage']['label']); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($actionHref !== ''): ?>
            <a class="aq-action" href="<?php echo htmlspecialchars($actionHref); ?>"><?php echo htmlspecialchars($actionLabel); ?></a>
        <?php endif; ?>
    </div>
    <?php
}
