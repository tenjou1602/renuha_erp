<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/quotation_file.php';

requireDepartment(['engineering']);

$requestView = isset($_GET['requests']) || isset($_POST['requests']);
$page_title = $requestView ? 'Material Requests' : 'Received Projects';
$projectId = (int) ($_GET['id'] ?? 0);
$requestBack = 'received_projects.php' . ($requestView ? '?requests=1' . ($projectId ? '&id=' . $projectId : '') : ($projectId ? '?id=' . $projectId : ''));

function engineeringMaterialId(string $name, string $unit, float $cost): int
{
    global $pdo;
    $name = trim($name);
    $stmt = $pdo->prepare("SELECT id FROM materials WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }
    throw new RuntimeException('"' . $name . '" is not in inventory. Warehouse adds a material when a purchase is received, or from Inventory.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_project'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
    } else {
        $id = (int) ($_POST['project_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE projects SET engineering_received_at = NOW() WHERE id = ? AND engineering_received_at IS NULL");
            $stmt->execute([$id]);
            $check = $pdo->prepare("SELECT engineering_received_at FROM projects WHERE id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn()) {
                $_SESSION['success'] = 'Project received. The Receive button is closed. You can send the material request to Procurement.';
            } else {
                $_SESSION['error'] = 'The project could not be received. Refresh the page and try again.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = userDatabaseError($e, 'Projects');
        }
    }
    header('Location: received_projects.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_site_materials'])) {
    if (!canWriteDepartmentData('projects')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . $requestBack);
        exit();
    }
    $requestId = (int) ($_POST['purchase_request_id'] ?? 0);
    try {
        $check = $pdo->prepare("
            SELECT pr.id, pr.pr_number, pr.status,
                   COUNT(mr.id) AS release_lines,
                   SUM(mr.status = 'approved') AS released_lines
            FROM purchase_requests pr
            LEFT JOIN material_requests mr ON mr.purchase_request_id = pr.id
            WHERE pr.id = ?
            GROUP BY pr.id, pr.pr_number, pr.status
        ");
        $check->execute([$requestId]);
        $request = $check->fetch();
        if (!$request) {
            throw new RuntimeException('The request could not be found.');
        }
        $linesReady = (int) $request['release_lines'] > 0 && (int) $request['released_lines'] === (int) $request['release_lines'];
        if ($request['status'] === 'completed' && $linesReady) {
            throw new RuntimeException('This request is already completed.');
        }
        if (!$linesReady) {
            throw new RuntimeException('The Warehouse has not released every material to the site yet.');
        }
        $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ? AND status <> 'completed'")->execute([$requestId]);
        logActivity($_SESSION['user_id'], 'Engineer received site materials', 'Projects', $request['pr_number']);
        notify('warehouse', 'request_complete', 'Request complete', $request['pr_number'] . ' has been received by Engineering and is Complete.', 'modules/warehouse/material_requests.php', null, 'purchase_request', $requestId);
        notify('procurement', 'request_complete', 'Request complete', $request['pr_number'] . ' has been received by Engineering and is Complete.', 'modules/procurement/purchase_requests.php', null, 'purchase_request', $requestId);
        notify('admin', 'request_complete', 'Request complete', $request['pr_number'] . ' has been received by Engineering and is Complete.', 'modules/admin/project_monitoring.php', null, 'purchase_request', $requestId);
        $_SESSION['success'] = 'You have received the materials on site. ' . $request['pr_number'] . ' is complete.';
    } catch (Throwable $e) {
        $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Projects') : $e->getMessage();
    }
    header('Location: ' . $requestBack);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_materials'])) {
    $projectId = (int) ($_POST['project_id'] ?? 0);
    try {
        $project = $pdo->prepare("SELECT id, project_code, name FROM projects WHERE id = ?");
        $project->execute([$projectId]);
        $project = $project->fetch();
        if (!$project) {
            throw new RuntimeException('Select a received project.');
        }
        if (function_exists('assertMonthOpen')) {
            assertMonthOpen('procurement', date('Y-m-d'));
        }
        $packed = projectExcelLinesForRequest(projectQuotationMaterials($pdo, $projectId));
        $ready = $packed['lines'];
        $pdo->beginTransaction();
        $prNumber = generateNumber('PR', 'purchase_requests', 'pr_number');
        $purpose = 'Material request for ' . $project['project_code'] . ' - ' . $project['name'];
        $stmt = $pdo->prepare("INSERT INTO purchase_requests (pr_number, project_id, requestor_id, purpose, priority, status, created_by) VALUES (?, ?, ?, ?, 'medium', 'pending', ?)");
        $stmt->execute([$prNumber, $projectId, $_SESSION['user_id'], $purpose, $_SESSION['user_id']]);
        $requestId = (int) $pdo->lastInsertId();
        $total = saveProjectExcelRequestItems($pdo, $requestId, $ready);
        $pdo->prepare("UPDATE purchase_requests SET total_amount = ? WHERE id = ?")->execute([$total, $requestId]);
        $pdo->prepare("UPDATE projects SET engineering_received_at = COALESCE(engineering_received_at, NOW()) WHERE id = ?")->execute([$projectId]);
        $pdo->commit();
        logActivity($_SESSION['user_id'], 'Requested materials', 'Projects', $prNumber . ' for ' . $project['project_code']);
        notify('procurement', 'material_request', 'Material request', $prNumber . ' has been sent to Procurement. They will buy the items, create the order and send it to the Warehouse.', 'modules/procurement/purchase_requests.php', $projectId, 'purchase_request', $requestId);
        $skipped = $packed['missing'] ? ' Not sent yet because they are not in inventory: ' . implode(', ', $packed['missing']) . '.' : '';
        $_SESSION['success'] = $prNumber . ' has been sent to Procurement. They will buy the items, create the order and send it to the Warehouse.' . $skipped;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Projects') : $e->getMessage();
    }
    header('Location: received_projects.php?requests=1&id=' . $projectId);
    exit();
}

$projects = [];
$materials = [];
$selected = null;
$engineer_requests = [];
try {
    $projects = $pdo->query("
        SELECT id, project_code, name, client, location, subject, status, engineering_received_at, created_at, quotation_path, quotation_name
        FROM projects
        ORDER BY (engineering_received_at IS NULL) DESC, created_at DESC
    ")->fetchAll();
    if ($projectId > 0) {
        foreach ($projects as $project) {
            if ((int) $project['id'] === $projectId) {
                $selected = $project;
                break;
            }
        }
        $materials = projectQuotationMaterials($pdo, $projectId);
    }
    $requestSql = "
        SELECT pr.id, pr.pr_number, pr.status, pr.project_id,
               p.project_code, p.name AS project_name, p.location,
               u.full_name AS engineer_name,
               COUNT(mr.id) AS release_lines,
               COALESCE(SUM(mr.status = 'approved'), 0) AS released_lines
        FROM purchase_requests pr
        LEFT JOIN projects p ON p.id = pr.project_id
        LEFT JOIN users u ON u.id = pr.requestor_id
        LEFT JOIN material_requests mr ON mr.purchase_request_id = pr.id
        WHERE pr.project_id IS NOT NULL
    ";
    if ($projectId > 0) {
        $requestStmt = $pdo->prepare($requestSql . " AND pr.project_id = ? GROUP BY pr.id, pr.pr_number, pr.status, pr.project_id, p.project_code, p.name, p.location, u.full_name ORDER BY pr.id DESC");
        $requestStmt->execute([$projectId]);
        $engineer_requests = $requestStmt->fetchAll();
    } else {
        $engineer_requests = $pdo->query($requestSql . " GROUP BY pr.id, pr.pr_number, pr.status, pr.project_id, p.project_code, p.name, p.location, u.full_name ORDER BY pr.id DESC")->fetchAll();
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Projects');
}

include '../../includes/header.php';
?>

<?php
$awaitingComplete = 0;
foreach ($engineer_requests as $request) {
    $readyRow = (int) $request['release_lines'] > 0 && (int) $request['released_lines'] === (int) $request['release_lines'];
    if ($readyRow && !($request['status'] === 'completed' && $readyRow)) {
        $awaitingComplete++;
    }
}
?>
<div class="page-header">
    <h1><i class="fas <?php echo $requestView ? 'fa-paper-plane' : 'fa-inbox'; ?>"></i> <?php echo htmlspecialchars($page_title); ?></h1>
</div>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($requestView): ?>
<p style="color:#64748b;">The materials, quantity, and price come from the project Excel. They cannot be edited. Download the Excel to view the file. Once the items are ordered, received by the Warehouse and released to the site, the update will appear here. Click Completed to confirm.</p>
<?php if ($awaitingComplete > 0): ?>
    <div class="alert alert-success">The Warehouse has released the materials to the site. Click Completed on the request.</div>
<?php endif; ?>
<div class="card">
    <h3>Send to Procurement</h3>
    <form method="GET" class="report-filters">
        <input type="hidden" name="requests" value="1">
        <div class="form-group">
            <label>Project</label>
            <select name="id" onchange="this.form.submit()">
                <option value="">Select project</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int) $project['id']; ?>" <?php echo $projectId === (int) $project['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <?php if (!$selected): ?>
        <p class="help-text">Choose a project. Its materials and the Send to Procurement button appear here.</p>
    <?php else: ?>
        <?php $excelFile = projectQuotationAttachment($pdo, (int) $selected['id']); ?>
        <p class="help-text">These lines are the project Excel. Material, quantity, and price cannot be edited.</p>
        <p style="margin:0.5rem 0 1rem;">
            <?php if ($excelFile): ?>
                <a class="btn btn-outline" href="<?php echo htmlspecialchars(projectQuotationDownloadUrl((int) $selected['id'])); ?>"><i class="fas fa-file-excel"></i> Download Excel</a>
            <?php else: ?>
                <span class="text-muted">No Excel file is attached to this project.</span>
            <?php endif; ?>
        </p>
        <form method="POST" id="sendProcurementForm">
            <input type="hidden" name="requests" value="1">
            <input type="hidden" name="request_materials" value="1">
            <input type="hidden" name="project_id" value="<?php echo (int) $selected['id']; ?>">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Material</th><th>Section</th><th>Qty</th><th>Unit</th><th>Price</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$materials): ?>
                            <tr><td colspan="5">This project has no Excel materials.</td></tr>
                        <?php endif; ?>
                        <?php $canSendAny = false; ?>
                        <?php foreach ($materials as $item): ?>
                        <?php $canSendLine = (int) ($item['material_id'] ?? 0) > 0; ?>
                        <?php if ($canSendLine) { $canSendAny = true; } ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars(trim(($item['material_code'] ? $item['material_code'] . ' — ' : '') . $item['item_name'])); ?>
                                <?php if (!$canSendLine): ?><div class="help-text">Not in inventory. Warehouse must add this material before it can be requested.</div><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['description'] ?: '—'); ?></td>
                            <td><?php echo number_format((float) $item['quantity'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['unit'] ?: 'pcs'); ?></td>
                            <td>₱<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:1rem;">
                <button type="submit" class="btn btn-primary" <?php echo empty($canSendAny) ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i> Send to Procurement</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php else: ?>
<p style="color:#64748b;">Receive the project, then send a material request to Procurement.</p>
<?php endif; ?>

<?php if ($requestView): ?>
<div class="card">
    <h3>Your material requests</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Request</th><th>Project</th><th>Location / site</th><th>Engineer</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$engineer_requests): ?>
                    <tr><td colspan="6">No material requests yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($engineer_requests as $request): ?>
                    <?php
                    $ready = (int) $request['release_lines'] > 0 && (int) $request['released_lines'] === (int) $request['release_lines'];
                    $finished = $request['status'] === 'completed' && $ready;
                    if ($finished) {
                        $requestLabel = 'Completed';
                    } elseif ($ready) {
                        $requestLabel = 'Released';
                    } elseif ($request['status'] === 'received' || ($request['status'] === 'completed' && !$ready)) {
                        $requestLabel = 'Received by Warehouse';
                    } elseif (in_array($request['status'], ['ordered', 'confirmed', 'approved'], true)) {
                        $requestLabel = 'Ordered';
                    } elseif ($request['status'] === 'pending') {
                        $requestLabel = 'Sent to Procurement';
                    } else {
                        $requestLabel = ucfirst(str_replace('_', ' ', (string) $request['status']));
                    }
                    ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($request['pr_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars(trim(($request['project_code'] ?? '') . ' ' . ($request['project_name'] ?? ''))); ?></td>
                    <td><?php echo htmlspecialchars($request['location'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($request['engineer_name'] ?: '—'); ?></td>
                    <td><span class="badge badge-<?php echo $finished ? 'success' : htmlspecialchars($request['status'] === 'completed' ? 'received' : $request['status']); ?>"><?php echo htmlspecialchars($requestLabel); ?></span></td>
                    <td>
                        <?php if (!empty($request['project_id'])): ?>
                            <a class="btn btn-sm btn-outline" href="<?php echo htmlspecialchars(projectQuotationDownloadUrl((int) $request['project_id'])); ?>">Excel</a>
                        <?php endif; ?>
                        <?php if ($ready && !$finished && canWriteDepartmentData('projects')): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirm that you received the materials on site? The request will be marked Completed.');">
                                <?php if ($requestView): ?><input type="hidden" name="requests" value="1"><?php endif; ?>
                                <input type="hidden" name="receive_site_materials" value="1">
                                <input type="hidden" name="purchase_request_id" value="<?php echo (int) $request['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">Completed</button>
                            </form>
                        <?php elseif ($finished): ?>
                            <span class="text-muted">Completed</span>
                        <?php elseif ($request['status'] === 'pending'): ?>
                            <span class="text-muted">Sent to Procurement</span>
                        <?php else: ?>
                            <span class="text-muted">Waiting for warehouse release</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$requestView): ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Project</th><th>Client</th><th>Location</th><th>Subject</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$projects): ?>
                    <tr><td colspan="5">No projects yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($project['project_code']); ?></strong><br>
                        <?php echo htmlspecialchars($project['name']); ?>
                        <?php if (empty($project['engineering_received_at'])): ?>
                            <span class="badge badge-pending">New</span>
                        <?php else: ?>
                            <span class="badge badge-approved">Received</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($project['client'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($project['location'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($project['subject'] ?: '—'); ?></td>
                    <td style="white-space:nowrap;">
                        <a class="btn btn-sm btn-primary" href="?requests=1&id=<?php echo (int) $project['id']; ?>">Send to Procurement</a>
                        <a class="btn btn-sm btn-outline" href="project_details.php?id=<?php echo (int) $project['id']; ?>">Details</a>
                        <a class="btn btn-sm btn-outline" href="material_requirements.php?project_id=<?php echo (int) $project['id']; ?>">Warehouse</a>
                        <a class="btn btn-sm btn-outline" href="accomplishments.php">Progress</a>
                        <?php if (empty($project['engineering_received_at']) && canWriteDepartmentData('projects')): ?>
                            <form method="POST" action="received_projects.php" style="display:inline;">
                                <input type="hidden" name="receive_project" value="1">
                                <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                <button class="btn btn-sm btn-success" type="submit" name="receive_project_button" value="1">Receive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
