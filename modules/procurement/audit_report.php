<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';

requireDepartment(['procurement', 'accounting']);
if (!isAdmin() && !isManager() && ($_SESSION['department'] ?? '') !== 'accounting') {
    $_SESSION['error'] = 'Audit Report is available to Procurement managers, Accounting, and Admin.';
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$page_title = 'Procurement Audit Report';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$userId = (int) ($_GET['user_id'] ?? 0);
$actionName = trim($_GET['action_name'] ?? '');
$reference = trim($_GET['reference'] ?? '');

$where = ["al.module = 'Procurement'"];
$params = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $where[] = 'DATE(al.created_at) >= ?';
    $params[] = $from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $where[] = 'DATE(al.created_at) <= ?';
    $params[] = $to;
}
if ($userId > 0) {
    $where[] = 'al.user_id = ?';
    $params[] = $userId;
}
if ($actionName !== '') {
    $where[] = 'al.action LIKE ?';
    $params[] = '%' . $actionName . '%';
}
if ($reference !== '') {
    $where[] = '(al.details LIKE ? OR al.action LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $reference . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "
    SELECT al.*, u.full_name, u.department
    FROM activity_log al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY al.created_at DESC
    LIMIT 300
";
$rows = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

if (isset($_GET['export'])) {
    $flat = [];
    foreach ($rows as $row) {
        $flat[] = [
            'when' => date('M d, Y h:i A', strtotime($row['created_at'])),
            'user' => $row['full_name'] ?: 'System',
            'module' => $row['module'],
            'action' => $row['action'],
            'details' => $row['details'] ?: '',
        ];
    }
    if (($_GET['export'] ?? '') === 'pdf') {
        renderReportPdf('Procurement audit report', ['when', 'user', 'module', 'action', 'details'], $flat);
    }
    renderReportExcel('Procurement audit report', $flat ?: [['when' => '', 'user' => '', 'module' => '', 'action' => '', 'details' => '']]);
}

$users = [];
try {
    $users = $pdo->query("
        SELECT DISTINCT u.id, u.full_name
        FROM activity_log al
        INNER JOIN users u ON u.id = al.user_id
        WHERE al.module = 'Procurement'
        ORDER BY u.full_name
    ")->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

include '../../includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-clipboard-list"></i> Procurement Audit Report</h1>
</div>
<p style="color:#64748b;margin-top:0;">Procurement process: quotations, suppliers, purchase requests, and purchase orders.</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="get" class="form-row" style="align-items:end;">
        <div class="form-group">
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div class="form-group">
            <label>User</label>
            <select name="user_id">
                <option value="">All users</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int) $user['id']; ?>" <?php echo $userId === (int) $user['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['full_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Action</label>
            <input type="text" name="action_name" placeholder="Action" value="<?php echo htmlspecialchars($actionName); ?>">
        </div>
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="reference" placeholder="Project, supplier, or reference" value="<?php echo htmlspecialchars($reference); ?>">
        </div>
        <div class="form-group">
            <button class="btn btn-primary">Generate</button>
        </div>
        <div class="form-group">
            <a class="btn btn-outline" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'pdf']))); ?>">PDF</a>
            <a class="btn btn-outline" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'excel']))); ?>">Excel</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5" class="table-empty">No procurement activity matches this filter.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="audit-when"><?php echo date('M d, Y', strtotime($row['created_at'])); ?><div><?php echo date('h:i A', strtotime($row['created_at'])); ?></div></td>
                    <td>
                        <?php echo htmlspecialchars($row['full_name'] ?: 'System'); ?>
                        <?php if (!empty($row['department'])): ?>
                            <div style="color:#64748b;font-size:0.75rem;"><?php echo htmlspecialchars(ucfirst($row['department'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['module']); ?></td>
                    <td><?php echo htmlspecialchars($row['action']); ?></td>
                    <td><?php echo htmlspecialchars($row['details'] ?: ''); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
