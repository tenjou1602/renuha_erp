<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireAdmin();

$page_title = 'Audit Trail';
$module = trim($_GET['module'] ?? '');
$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = [];
$params = [];
if ($module !== '') {
    $where[] = 'al.module = ?';
    $params[] = $module;
}
if ($q !== '') {
    $where[] = '(al.action LIKE ? OR al.details LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($from !== '') {
    $where[] = 'DATE(al.created_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $where[] = 'DATE(al.created_at) <= ?';
    $params[] = $to;
}

$sql = "
    SELECT al.*, u.full_name, u.department
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY al.created_at DESC LIMIT 300';

$logs = [];
$modules = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    $modules = $pdo->query("SELECT DISTINCT module FROM activity_log ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> Audit Trail</h1>
    <a href="department_monitoring.php" class="btn btn-secondary">System Activity</a>
</div>

<p style="color:#64748b;margin-top:0;">Record of sign-ins and critical changes: approvals, quotation amounts, deletions, stock releases, and purchase requests.</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="GET" class="form-row" style="align-items:end;">
        <div class="form-group">
            <label>Module</label>
            <select name="module">
                <option value="">All</option>
                <?php foreach ($modules as $name): ?>
                    <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $module === $name ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Search</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="User, action, or details">
        </div>
        <div class="form-group">
            <label>From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Filter</button>
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
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="table-empty">No activity matches this filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($log['full_name'] ?: 'System'); ?>
                            <?php if (!empty($log['department'])): ?>
                                <div style="color:#64748b;font-size:0.75rem;"><?php echo htmlspecialchars(ucfirst($log['department'])); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['module']); ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars($log['details'] ?: ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
