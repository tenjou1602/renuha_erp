<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['engineering']);

$page_title = 'Material Requirements';
$action = $_GET['action'] ?? 'list';
$project_id = (int)($_GET['project_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_procurement'])) {
    if (!canSubmitMaterialRequirement()) {
        $_SESSION['error'] = 'You cannot send a material request.';
        header('Location: material_requirements.php');
        exit();
    }
    $req_project = (int) ($_POST['project_id'] ?? 0);
    $selected = array_map('intval', (array) ($_POST['materials'] ?? []));
    $quantities = (array) ($_POST['qty'] ?? []);
    if ($req_project <= 0 || !$selected) {
        $_SESSION['error'] = 'Choose a project and at least one material.';
    } else {
        try {
            $pdo->beginTransaction();
            $prNumber = generateNumber('PR', 'purchase_requests', 'pr_number');
            $pdo->prepare("INSERT INTO purchase_requests (pr_number, project_id, requestor_id, purpose, priority, status, created_by) VALUES (?, ?, ?, ?, 'medium', 'pending', ?)")
                ->execute([$prNumber, $req_project, $_SESSION['user_id'], 'Material request from Engineering', $_SESSION['user_id']]);
            $prId = (int) $pdo->lastInsertId();
            $itemStmt = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, material_id, quantity, unit, estimated_cost) VALUES (?, ?, ?, ?, ?)");
            $reqStmt = $pdo->prepare("INSERT INTO material_requests (project_id, material_id, quantity, request_date, status, requested_by, notes, purchase_request_id, flow_status) VALUES (?, ?, ?, CURDATE(), 'pending', ?, ?, ?, 'procurement')");
            $saved = 0;
            foreach ($selected as $materialId) {
                $qty = (int) ($quantities[$materialId] ?? 0);
                if ($materialId <= 0 || $qty <= 0) {
                    continue;
                }
                $unitStmt = $pdo->prepare("SELECT unit, cost_per_unit FROM materials WHERE id = ?");
                $unitStmt->execute([$materialId]);
                $material = $unitStmt->fetch();
                if (!$material) {
                    continue;
                }
                $itemStmt->execute([$prId, $materialId, $qty, $material['unit'] ?: 'pcs', round($qty * (float) $material['cost_per_unit'], 2)]);
                $reqStmt->execute([$req_project, $materialId, $qty, $_SESSION['user_id'], 'Sent to Procurement', $prId]);
                $saved++;
            }
            if ($saved === 0) {
                throw new RuntimeException('Enter a quantity for each selected material.');
            }
            $pdo->prepare("UPDATE purchase_requests SET total_amount = (SELECT COALESCE(SUM(estimated_cost), 0) FROM purchase_request_items WHERE purchase_request_id = ?) WHERE id = ?")->execute([$prId, $prId]);
            $pdo->commit();
            notify('procurement', 'material_request', 'Material request', $prNumber . ' has been sent to Procurement. They will buy the items, create the order and send it to the Warehouse.', 'modules/procurement/purchase_requests.php', $req_project, 'purchase_request', $prId);
            $_SESSION['success'] = $prNumber . ' has been sent to Procurement. They will buy the items, create the order and send it to the Warehouse.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Projects') : $e->getMessage();
        }
    }
    header('Location: material_requirements.php' . ($req_project ? '?project_id=' . $req_project : ''));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['engineer_receive'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'You cannot receive materials.';
    } else {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE material_requests SET flow_status = 'complete' WHERE id = ? AND requested_by = ? AND flow_status = 'released'");
        $stmt->execute([$requestId, $_SESSION['user_id']]);
        if ($stmt->rowCount() === 1) {
            $pdo->prepare("UPDATE warehouse_releases SET status = 'received', received_at = NOW() WHERE material_request_id = ? AND status = 'released'")->execute([$requestId]);
            $meta = $pdo->prepare("SELECT p.project_code, p.id AS project_id, pr.pr_number FROM material_requests mr INNER JOIN projects p ON p.id = mr.project_id LEFT JOIN purchase_requests pr ON pr.id = mr.purchase_request_id WHERE mr.id = ?");
            $meta->execute([$requestId]);
            $info = $meta->fetch() ?: [];
            $ref = $info['pr_number'] ?? 'the request';
            $pid = (int) ($info['project_id'] ?? 0);
            $done = $ref . ' has been received by Engineering and is Complete.';
            notify('warehouse', 'request_complete', 'Request complete', $done, 'modules/warehouse/material_requests.php', $pid, 'material_request', $requestId);
            notify('procurement', 'request_complete', 'Request complete', $done, 'modules/procurement/purchase_requests.php', $pid, 'material_request', $requestId);
            notify('admin', 'request_complete', 'Request complete', $done, 'modules/admin/project_monitoring.php', $pid, 'material_request', $requestId);
            $_SESSION['success'] = 'Materials received. The request is complete.';
        } else {
            $_SESSION['error'] = 'That request is not waiting for you to receive it.';
        }
    }
    header('Location: material_requirements.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_warehouse_release'])) {
    if (!canRequestWarehouseRelease()) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: material_requirements.php');
        exit();
    }
    $req_project = (int)($_POST['project_id'] ?? 0);
    $req_material = (int)($_POST['material_id'] ?? 0);
    $req_qty = (int)($_POST['quantity'] ?? 0);
    if ($req_project <= 0 || $req_material <= 0 || $req_qty <= 0) {
        $_SESSION['error'] = 'Project, material, and quantity are required.';
    } else {
        try {
            $exists = $pdo->query("SHOW TABLES LIKE 'material_requests'")->fetch();
            if (!$exists) {
                throw new RuntimeException('Warehouse material_requests table is not ready.');
            }
            $stmt = $pdo->prepare("INSERT INTO material_requests (project_id, material_id, quantity, request_date, status, requested_by, notes) VALUES (?, ?, ?, CURDATE(), 'pending', ?, ?)");
            $stmt->execute([$req_project, $req_material, $req_qty, $_SESSION['user_id'], trim($_POST['notes'] ?? '')]);
            logActivity($_SESSION['user_id'], 'Requested warehouse release', 'Projects', "Project $req_project material $req_material qty $req_qty");
            $_SESSION['success'] = 'Material request sent to Warehouse for release.';
        } catch (Throwable $e) {
            $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Projects') : $e->getMessage();
        }
    }
    header('Location: material_requirements.php' . ($req_project ? '?project_id=' . $req_project : ''));
    exit();
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name, status FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();

// Get material requirements data
$material_requirements = [];
$project_materials = [];
$low_stock_materials = [];
$all_materials = [];
try {
    $all_materials = $pdo->query("SELECT id, material_code, name, unit, current_stock FROM materials WHERE status = 'active' ORDER BY name")->fetchAll();
    // Low stock materials that need procurement
    $low_stock_materials = $pdo->query("
        SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
               COALESCE(NULLIF(s.name, ''), NULLIF(m.supplier, '')) as supplier_name, s.phone as supplier_phone, s.email as supplier_email,
               COUNT(DISTINCT pr.id) as pending_pr_count
        FROM materials m
        LEFT JOIN suppliers s ON m.supplier_id = s.id
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.status IN ('pending', 'approved', 'confirmed')
        WHERE m.status = 'active' AND m.current_stock <= m.min_stock AND m.min_stock > 0
        GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, s.name, s.phone, s.email, m.supplier
        ORDER BY m.current_stock ASC
    ")->fetchAll();
    
    // Material requirements by project (materials used in projects)
    if ($project_id > 0) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, m.category,
                   COALESCE(NULLIF(s.name, ''), NULLIF(m.supplier, '')) as supplier_name,
                   COALESCE(SUM(pri.quantity), 0) as required_quantity,
                   COUNT(DISTINCT pr.id) as pr_count
            FROM materials m
            LEFT JOIN suppliers s ON m.supplier_id = s.id
            LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
            LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id AND pr.project_id = ?
            WHERE m.status = 'active'
            GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, m.category, s.name, m.supplier
            HAVING COALESCE(SUM(pri.quantity), 0) > 0
            ORDER BY COALESCE(SUM(pri.quantity), 0) DESC
        ");
        $stmt->execute([$project_id]);
        $project_materials = $stmt->fetchAll();
    }

    // Overall material requirements across all projects
    $material_requirements = $pdo->query("
        SELECT m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit,
               m.category,
               COALESCE(NULLIF(s.name, ''), NULLIF(m.supplier, '')) as supplier_name,
               COUNT(DISTINCT pr.project_id) as project_count,
               COALESCE(SUM(pri.quantity), 0) as total_required,
               COALESCE(SUM(CASE WHEN pr.status IN ('pending', 'approved', 'confirmed') THEN pri.quantity ELSE 0 END), 0) as pending_quantity
        FROM materials m
        LEFT JOIN suppliers s ON m.supplier_id = s.id
        LEFT JOIN purchase_request_items pri ON m.id = pri.material_id
        LEFT JOIN purchase_requests pr ON pri.purchase_request_id = pr.id
        WHERE m.status = 'active'
        GROUP BY m.id, m.material_code, m.name, m.unit, m.current_stock, m.min_stock, m.max_stock, m.cost_per_unit, m.category, s.name, m.supplier
        HAVING COALESCE(SUM(pri.quantity), 0) > 0 OR m.current_stock <= m.min_stock
        ORDER BY (COALESCE(SUM(pri.quantity), 0) - m.current_stock) DESC
        LIMIT 20
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
    $material_requirements = [];
    $project_materials = [];
    $low_stock_materials = [];
}

include '../../includes/header.php';
?>

<style>
.material-req-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.material-req-stats .card {
    border-left: 4px solid #4e73df;
}
.stock-critical { color: #e74a3b; font-weight: 700; }
.stock-warning { color: #f6c23e; font-weight: 700; }
.stock-ok { color: #1cc88a; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-boxes"></i> <?php echo $page_title; ?></h1>
    <div class="button-row">
        <?php if (canSubmitMaterialRequirement()): ?>
        <a href="received_projects.php?requests=1<?php echo $project_id > 0 ? '&id=' . $project_id : ''; ?>" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send to Procurement</a>
        <?php endif; ?>
    </div>
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

<?php if (canRequestWarehouseRelease()): ?>
<div class="card">
    <h3>Request warehouse release</h3>
    <p class="muted-note">1. Project in-charge requests materials here. 2. Warehouse releases stock if it is on hand. 3. If stock is short, Warehouse sends a purchase request and Procurement buys it.</p>
    <form method="POST">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int)$project['id']; ?>" <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Material</label>
            <select name="material_id" required>
                <option value="">Select material</option>
                <?php foreach ($all_materials as $material): ?>
                    <option value="<?php echo (int)$material['id']; ?>"><?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name'] . ' (stock ' . $material['current_stock'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1" required>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="2"></textarea>
        </div>
        <button type="submit" name="request_warehouse_release" class="btn btn-primary">Send to Warehouse</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3 class="section-title"><i class="fas fa-paper-plane"></i> Send Request to Procurement</h3>
    <form method="POST">
        <div class="form-group">
            <label class="required">Project</label>
            <select name="project_id" required>
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int) $project['id']; ?>" <?php echo $project_id === (int) $project['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><label><input type="checkbox" id="selectAllMaterials"> Select all</label></th>
                        <th>Material</th>
                        <th>Stock</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_materials as $material): ?>
                    <tr>
                        <td><input type="checkbox" class="material-check" name="materials[]" value="<?php echo (int) $material['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?></td>
                        <td><?php echo number_format((float) $material['current_stock']); ?> <?php echo htmlspecialchars($material['unit']); ?></td>
                        <td><input type="number" min="1" name="qty[<?php echo (int) $material['id']; ?>]" value="1" style="width:90px;"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (canSubmitMaterialRequirement()): ?>
        <button type="submit" name="send_to_procurement" class="btn btn-primary">Send Request to Procurement</button>
        <?php endif; ?>
    </form>
</div>
<script>
document.getElementById('selectAllMaterials')?.addEventListener('change', function () {
    document.querySelectorAll('.material-check').forEach((box) => { box.checked = this.checked; });
});
</script>

<?php
$myReleases = [];
try {
    $rel = $pdo->prepare("SELECT mr.id, mr.quantity, mr.flow_status, m.name, p.project_code FROM material_requests mr INNER JOIN materials m ON m.id = mr.material_id INNER JOIN projects p ON p.id = mr.project_id WHERE mr.requested_by = ? AND mr.flow_status = 'released' ORDER BY mr.id DESC");
    $rel->execute([$_SESSION['user_id']]);
    $myReleases = $rel->fetchAll();
} catch (PDOException $e) {
    $myReleases = [];
}
?>
<?php if ($myReleases): ?>
<div class="card">
    <h3>Materials released to you</h3>
    <?php foreach ($myReleases as $release): ?>
        <form method="POST" style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.4rem;">
            <span><?php echo htmlspecialchars($release['project_code'] . ' — ' . $release['name'] . ' x ' . $release['quantity']); ?></span>
            <input type="hidden" name="request_id" value="<?php echo (int) $release['id']; ?>">
            <button type="submit" name="engineer_receive" class="btn btn-sm btn-success">Receive</button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Project Filter -->
<div class="card">
    <form method="GET" class="report-filters">
        <div class="form-group">
            <label>Filter by Project:</label>
            <select name="project_id">
                <option value="">All Projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo $project['id']; ?>" <?php echo $project_id == $project['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
        <a href="material_requirements.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Reset</a>
    </form>
</div>

<!-- Quick Stats -->
<div class="material-req-stats">
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Critical Low Stock</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($low_stock_materials); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Materials Required</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($material_requirements); ?></div>
    </div>
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Required Quantity</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format(array_sum(array_column($material_requirements, 'total_required'))); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Pending Quantity</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo number_format(array_sum(array_column($material_requirements, 'pending_quantity'))); ?></div>
    </div>
</div>

<?php if ($project_id > 0): ?>
<!-- Project-Specific Materials -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-project-diagram"></i> Materials for Selected Project</h3>
    <?php if (empty($project_materials)): ?>
        <div class="empty-state">
            <i class="fas fa-boxes"></i>
            <p>No material requirements found for this project.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Required</th>
                        <th>Shortage</th>
                        <th>Unit</th>
                        <th>Supplier</th>
                        <th>PR Count</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($project_materials as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $material['current_stock'] <= 0 ? 'stock-critical' : ($material['current_stock'] <= $material['min_stock'] ? 'stock-warning' : 'stock-ok'); ?>"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['required_quantity']); ?></td>
                        <td class="<?php echo ($material['required_quantity'] - $material['current_stock']) > 0 ? 'stock-critical' : 'stock-ok'; ?>"><?php echo number_format(max(0, $material['required_quantity'] - $material['current_stock'])); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $material['pr_count']; ?></td>
                        <td>
                            <?php if (canViewModule('procurement')): ?>
                            <a href="received_projects.php<?php echo $project_id > 0 ? '?id=' . $project_id : ''; ?>" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i> Request</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Critical Low Stock Materials -->
<?php if (!empty($low_stock_materials)): ?>
<div class="card">
    <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Critical Low Stock (Immediate Action Required)</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Material Code</th>
                    <th>Name</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Unit</th>
                    <th>Cost per Unit</th>
                    <th>Supplier</th>
                    <th>Supplier Contact</th>
                    <th>Pending PRs</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_materials as $material): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($material['name']); ?></td>
                    <td class="stock-critical"><?php echo number_format($material['current_stock']); ?></td>
                    <td><?php echo number_format($material['min_stock']); ?></td>
                    <td><?php echo htmlspecialchars($material['unit']); ?></td>
                    <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($material['supplier_phone'] ?? 'N/A'); ?></td>
                    <td><?php echo $material['pending_pr_count']; ?></td>
                    <td>
                        <?php if (canViewModule('procurement')): ?>
                        <a href="received_projects.php<?php echo $project_id > 0 ? '?id=' . $project_id : ''; ?>" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i> Request</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Overall Material Requirements -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Overall Material Requirements</h3>
    <?php if (empty($material_requirements)): ?>
        <div class="empty-state">
            <i class="fas fa-boxes"></i>
            <p>No material requirements found.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Total Required</th>
                        <th>Pending</th>
                        <th>Shortage</th>
                        <th>Unit</th>
                        <th>Cost per Unit</th>
                        <th>Supplier</th>
                        <th>Projects</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($material_requirements as $material): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($material['material_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                        <td class="<?php echo $material['current_stock'] <= 0 ? 'stock-critical' : ($material['current_stock'] <= $material['min_stock'] ? 'stock-warning' : 'stock-ok'); ?>"><?php echo number_format($material['current_stock']); ?></td>
                        <td><?php echo number_format($material['total_required']); ?></td>
                        <td><?php echo number_format($material['pending_quantity']); ?></td>
                        <td class="<?php echo ($material['total_required'] - $material['current_stock']) > 0 ? 'stock-warning' : 'stock-ok'; ?>"><?php echo number_format(max(0, $material['total_required'] - $material['current_stock'])); ?></td>
                        <td><?php echo htmlspecialchars($material['unit']); ?></td>
                        <td>₱<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $material['project_count']; ?></td>
                        <td>
                            <?php if (canViewModule('procurement')): ?>
                            <a href="received_projects.php<?php echo $project_id > 0 ? '?id=' . $project_id : ''; ?>" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane"></i> Request</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
