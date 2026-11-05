<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';

requireDepartment(['procurement']);

$page_title = 'Project Materials';

$groups = [];
try {
    $rows = $pdo->query("
        SELECT
            COALESCE(p.project_code, 'No project') AS project_code,
            COALESCE(p.name, 'Materials not linked to a project') AS project_name,
            COALESCE(m.material_code, '') AS material_code,
            COALESCE(m.name, 'Material') AS material_name,
            COALESCE(m.unit, '') AS unit,
            SUM(pri.quantity) AS quantity
        FROM purchase_request_items pri
        JOIN purchase_requests pr ON pr.id = pri.purchase_request_id
        LEFT JOIN projects p ON p.id = pr.project_id
        LEFT JOIN materials m ON m.id = pri.material_id
        GROUP BY p.id, p.project_code, p.name, m.id, m.material_code, m.name, m.unit
        ORDER BY p.project_code IS NULL, p.project_code, m.material_code, m.name
    ")->fetchAll();
    foreach ($rows as $row) {
        $key = $row['project_code'] . '|' . $row['project_name'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'project_code' => $row['project_code'],
                'project_name' => $row['project_name'],
                'items' => [],
            ];
        }
        $groups[$key]['items'][] = $row;
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Procurement');
}

$format = $_GET['format'] ?? '';
if ($format === 'pdf' || $format === 'excel') {
    if ($format === 'excel') {
        $flat = [];
        foreach ($groups as $group) {
            $label = $group['project_code'] === 'No project'
                ? $group['project_name']
                : $group['project_code'] . ' - ' . $group['project_name'];
            foreach ($group['items'] as $item) {
                $flat[] = [
                    'project' => $label,
                    'material_code' => $item['material_code'],
                    'material' => $item['material_name'],
                    'unit' => $item['unit'],
                    'quantity' => $item['quantity'],
                ];
            }
        }
        renderReportExcel('Project materials', $flat ?: [[
            'project' => '',
            'material_code' => '',
            'material' => '',
            'unit' => '',
            'quantity' => '',
        ]]);
    }
    $body = '';
    if (!$groups) {
        $body .= '<p class="notes">No project materials yet.</p>';
    }
    foreach ($groups as $group) {
        $label = $group['project_code'] === 'No project'
            ? $group['project_name']
            : $group['project_code'] . ' — ' . $group['project_name'];
        $body .= '<h2>' . htmlspecialchars($label) . '</h2>';
        $body .= '<table class="data"><tr><th>Material code</th><th>Material</th><th>Unit</th><th>Quantity</th></tr>';
        foreach ($group['items'] as $item) {
            $body .= '<tr><td>' . htmlspecialchars($item['material_code']) . '</td>'
                . '<td>' . htmlspecialchars($item['material_name']) . '</td>'
                . '<td>' . htmlspecialchars($item['unit']) . '</td>'
                . '<td>' . number_format((float) $item['quantity']) . '</td></tr>';
        }
        $body .= '</table>';
    }
    runehaPdfDocument('project-materials.pdf', 'Project Materials', $body, 'portrait');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-layer-group"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a class="btn btn-outline" href="project_materials.php?format=pdf">Download PDF</a>
        <a class="btn btn-outline" href="project_materials.php?format=excel">Download Excel</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<p style="color:#64748b;">One file for every project. Each project keeps its own materials.</p>

<?php if (!$groups): ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-boxes"></i>
        <p>No project materials yet.</p>
    </div>
</div>
<?php endif; ?>

<?php foreach ($groups as $group): ?>
<div class="card">
    <h3 style="margin-top:0;">
        <?php if ($group['project_code'] === 'No project'): ?>
            <?php echo htmlspecialchars($group['project_name']); ?>
        <?php else: ?>
            <?php echo htmlspecialchars($group['project_code']); ?>
            <span style="font-weight:500;color:#64748b;"> — <?php echo htmlspecialchars($group['project_name']); ?></span>
        <?php endif; ?>
    </h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material code</th>
                    <th>Material</th>
                    <th>Unit</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($group['items'] as $item): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($item['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($item['material_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                    <td><?php echo number_format((float) $item['quantity']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php include '../../includes/footer.php'; ?>
