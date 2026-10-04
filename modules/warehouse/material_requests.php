<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';
require_once '../../includes/warehouse_release.php';

requireDepartment(['warehouse']);

$page_title = 'Material Release';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Check if material_requests table exists
$table_exists = false;
try {
    $result = $pdo->query("SHOW TABLES LIKE 'material_requests'");
    $table_exists = $result->fetch() !== false;
} catch (PDOException $e) {
    $table_exists = false;
}

// Create table if it doesn't exist
if (!$table_exists) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS material_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                project_id BIGINT UNSIGNED NOT NULL,
                material_id BIGINT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                request_date DATE NOT NULL,
                status ENUM('pending', 'approved', 'rejected', 'partial') NOT NULL DEFAULT 'pending',
                requested_by BIGINT UNSIGNED NOT NULL,
                notes TEXT NULL,
                released_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                released_by BIGINT UNSIGNED NULL,
                released_at DATETIME NULL,
                purchase_request_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_mr_project (project_id),
                KEY idx_mr_material (material_id),
                CONSTRAINT fk_mr_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_mr_material FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_mr_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_mr_released_by FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $table_exists = true;
    } catch (PDOException $e) {
        $error = userDatabaseError($e, 'Warehouse');
    }
}

if ($table_exists) {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM material_requests LIKE 'purchase_request_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE material_requests ADD COLUMN purchase_request_id BIGINT UNSIGNED NULL");
        }
        $moveCol = $pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'project_id'")->fetch();
        if (!$moveCol) {
            $pdo->exec("ALTER TABLE stock_movements ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER material_id, ADD KEY idx_movements_project (project_id)");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] material_requests purchase_request_id: ' . $e->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['approve_request']) || isset($_POST['release_project_requests'])) && !canWriteDepartmentData('warehouse')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: material_requests.php');
        exit();
    }

    if (isset($_POST['release_project_requests'])) {
        try {
            $result = releasePendingProjectMaterials($pdo, (int) ($_POST['project_id'] ?? 0), (int) $_SESSION['user_id']);
            foreach ($result['alerts'] as $alert) {
                logActivity($_SESSION['user_id'], 'Low stock alert', 'Warehouse', $alert['name'] . ' at ' . $alert['stock'] . ' (min ' . $alert['min'] . ')');
                try {
                    notifyLowStock($alert['name'], $alert['stock'], $alert['min'], $alert['unit'] ?? 'pcs');
                } catch (Throwable $mailError) {
                    logActivity($_SESSION['user_id'], 'Email failed', 'Mail', $mailError->getMessage());
                }
            }
            logActivity($_SESSION['user_id'], 'Released inventory', 'Warehouse', $result['project_label'] . ': ' . implode(', ', $result['released']));
            $message = 'Na-release papunta sa ' . $result['project_label'] . ': ' . implode(', ', $result['released']) . '. Naka-stock-out na. Puwede na itong tanggapin ng engineer para maging completed ang request.';
            if ($result['short']) {
                $shortNames = array_map(static function ($item) {
                    return $item['name'];
                }, $result['short']);
                $message .= ' Hindi kasama dahil kulang ang stock: ' . implode(', ', $shortNames) . '.';
            }
            $_SESSION['success'] = $message;
            header('Location: material_requests.php');
            exit();
        } catch (Throwable $e) {
            $error = $e instanceof PDOException ? userDatabaseError($e, 'Warehouse') : $e->getMessage();
        }
    }

    if (isset($_POST['approve_request'])) {
        try {
            if (!$table_exists) {
                throw new Exception('Material requests table does not exist.');
            }
            
            $request_id = (int)$_POST['request_id'];
            $release_quantity = (int)$_POST['release_quantity'];
            $notes = trim($_POST['notes'] ?? '');
            
            $pdo->beginTransaction();
            
            // Get request details
            $stmt = $pdo->prepare("SELECT mr.*, m.current_stock, m.name as material_name, p.project_code, p.name AS project_name FROM material_requests mr LEFT JOIN materials m ON mr.material_id = m.id LEFT JOIN projects p ON p.id = mr.project_id WHERE mr.id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                throw new Exception('Material request not found.');
            }
            
            if ($release_quantity <= 0) {
                throw new Exception('Nothing to release. Stock is empty.');
            }
            if ($release_quantity > (int) $request['current_stock']) {
                throw new Exception('Insufficient stock for release.');
            }
            if ($release_quantity > (int) $request['quantity']) {
                throw new Exception('Release quantity cannot exceed requested quantity.');
            }
            $status = $release_quantity >= (int) $request['quantity'] ? 'approved' : 'partial';
            $projectLabel = trim(($request['project_code'] ?? '') . ' ' . ($request['project_name'] ?? ''));
            $reason = 'Released to project site ' . ($projectLabel !== '' ? $projectLabel : ('#' . $request['project_id']));
            if ($notes !== '') {
                $reason .= ': ' . $notes;
            }

            $stmt = $pdo->prepare("UPDATE material_requests SET status = ?, released_quantity = ?, released_by = ?, released_at = NOW(), notes = ? WHERE id = ? AND status = 'pending'");
            $stmt->execute([$status, $release_quantity, $_SESSION['user_id'], $notes, $request_id]);
            $stmt = $pdo->prepare("UPDATE materials SET current_stock = current_stock - ? WHERE id = ?");
            $stmt->execute([$release_quantity, $request['material_id']]);
            $stockRow = $pdo->prepare("SELECT id FROM warehouse_stock WHERE material_id = ? ORDER BY id DESC LIMIT 1");
            $stockRow->execute([$request['material_id']]);
            $stockId = (int) $stockRow->fetchColumn();
            if ($stockId > 0) {
                $pdo->prepare("UPDATE warehouse_stock SET quantity = GREATEST(quantity - ?, 0) WHERE id = ?")->execute([$release_quantity, $stockId]);
            }
            $stmt = $pdo->prepare("INSERT INTO stock_movements (material_id, project_id, movement_type, quantity, reason, created_by) VALUES (?, ?, 'out', ?, ?, ?)");
            $stmt->execute([$request['material_id'], $request['project_id'], $release_quantity, $reason, $_SESSION['user_id']]);
            
            $pdo->commit();
            
            logActivity($_SESSION['user_id'], 'Released inventory', 'Warehouse', "Request: $request_id, Material: {$request['material_name']}, Quantity: $release_quantity");
            $_SESSION['success'] = 'Released ' . number_format($release_quantity) . ' to the project site. Naka-stock-out na.';
            header('Location: material_requests.php');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

    if (isset($_POST['send_to_procurement'])) {
        if (!canWriteDepartmentData('warehouse')) {
            $_SESSION['error'] = 'Administrators have view-only access.';
            header('Location: material_requests.php');
            exit();
        }
        $request_id = (int) ($_POST['request_id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                SELECT mr.*, m.current_stock, m.name AS material_name, m.unit, m.cost_per_unit
                FROM material_requests mr
                LEFT JOIN materials m ON mr.material_id = m.id
                WHERE mr.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            if (!$request || $request['status'] !== 'pending') {
                throw new Exception('Only a pending request can be sent to Procurement.');
            }
            if (!empty($request['purchase_request_id'])) {
                throw new Exception('This request already has a purchase request.');
            }
            $shortage = (int) $request['quantity'] - (int) $request['current_stock'];
            if ($shortage <= 0) {
                throw new Exception('Stock covers this request. Release it instead of buying more.');
            }
            $pr_number = generateNumber('PR', 'purchase_requests', 'pr_number');
            $unitCost = (float) ($request['cost_per_unit'] ?? 0);
            $total = round($shortage * $unitCost, 2);
            $purpose = 'Stock shortage for inventory release #' . $request_id . ' (' . $request['material_name'] . ')';
            $stmt = $pdo->prepare("INSERT INTO purchase_requests (pr_number, project_id, requestor_id, purpose, priority, status, total_amount, created_by) VALUES (?, ?, ?, ?, 'high', 'pending', ?, ?)");
            $stmt->execute([$pr_number, $request['project_id'], $_SESSION['user_id'], $purpose, $total, $_SESSION['user_id']]);
            $prId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, material_id, quantity, unit, estimated_cost, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$prId, $request['material_id'], $shortage, $request['unit'] ?: 'pcs', $unitCost, 'Raised by Warehouse because on-hand stock is short.']);
            $stmt = $pdo->prepare("UPDATE material_requests SET purchase_request_id = ? WHERE id = ?");
            $stmt->execute([$prId, $request_id]);
            $pdo->commit();
            logActivity($_SESSION['user_id'], 'Sent shortage to procurement', 'Warehouse', "Request #$request_id -> $pr_number, shortage $shortage");
            $_SESSION['success'] = "Purchase request $pr_number sent to Procurement for the shortage.";
            header('Location: material_requests.php');
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof PDOException ? userDatabaseError($e, 'Warehouse') : $e->getMessage();
        }
    }
}

if ($table_exists) {
    try {
        syncDepartmentWorkflow($pdo);
    } catch (Throwable $e) {
        error_log('[Runeha ERP] sync received orders for release: ' . $e->getMessage());
    }
}

// Get material requests
$material_requests = [];
try {
    if ($table_exists) {
        $material_requests = $pdo->query("
            SELECT mr.*, p.project_code, p.name as project_name, p.location AS project_location, m.material_code, m.name as material_name, m.unit, m.current_stock,
                   u1.full_name as requested_by_name, u2.full_name as released_by_name, prq.pr_number
            FROM material_requests mr
            LEFT JOIN projects p ON mr.project_id = p.id
            LEFT JOIN materials m ON mr.material_id = m.id
            LEFT JOIN users u1 ON mr.requested_by = u1.id
            LEFT JOIN users u2 ON mr.released_by = u2.id
            LEFT JOIN purchase_requests prq ON mr.purchase_request_id = prq.id
            ORDER BY mr.request_date DESC, mr.created_at DESC
        ")->fetchAll();
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}
$site_projects = [];
try {
    $site_projects = $pdo->query("SELECT id, project_code, name, location FROM projects WHERE status != 'completed' ORDER BY project_code")->fetchAll();
} catch (PDOException $e) {
    $site_projects = [];
}
$selected_project = (int) ($_GET['project_id'] ?? 0);
$selected_site = null;
foreach ($site_projects as $site) {
    if ((int) $site['id'] === $selected_project) {
        $selected_site = $site;
        break;
    }
}
if ($selected_project > 0) {
    $material_requests = array_values(array_filter($material_requests, static function ($row) use ($selected_project) {
        return (int) $row['project_id'] === $selected_project;
    }));
}

include '../../includes/header.php';
?>

<style>
.material-requests-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.material-requests-stats .card {
    border-left: 4px solid #4e73df;
}
</style>

<div class="page-header">
    <h1><i class="fas fa-clipboard-list"></i> <?php echo $page_title; ?></h1>
    <div class="button-row">
        <a href="project_materials.php" class="btn btn-outline"><i class="fas fa-project-diagram"></i> Project Materials</a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>

<p style="color:#64748b;margin-top:0;">Pagkatapos mag-request ang Engineering kay Procurement, ma-order ito, at matanggap ng Warehouse, lalabas dito ang project code, location, at ang engineer na nag-request. Pindutin ang Release all to site. Iyon ang stock-out.</p>

<div class="card">
    <form method="GET" class="report-filters">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" onchange="this.form.submit()">
                <option value="">All projects</option>
                <?php foreach ($site_projects as $site): ?>
                    <option value="<?php echo (int) $site['id']; ?>" <?php echo $selected_project === (int) $site['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($site['project_code'] . ' - ' . $site['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <?php if ($selected_site): ?>
        <p style="margin:0.75rem 0 0;"><strong><?php echo htmlspecialchars($selected_site['project_code']); ?></strong> · <?php echo htmlspecialchars($selected_site['location'] ?: 'Walang naka-save na location'); ?></p>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="material-requests-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Requests</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($material_requests); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">For release</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $pending = array_filter($material_requests, function($m) { return $m['status'] === 'pending'; });
            echo count($pending);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Released to site</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $approved = array_filter($material_requests, function($m) { return $m['status'] === 'approved'; });
            echo count($approved);
            ?>
        </div>
    </div>
    <div class="card" style="border-left-color:#e74a3b;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Partial</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $partial = array_filter($material_requests, function($m) { return $m['status'] === 'partial'; });
            echo count($partial);
            ?>
        </div>
    </div>
</div>

<!-- Material Requests List -->
<div class="card">
    <h3 class="section-title"><i class="fas fa-list"></i> Material Requests</h3>
    <?php if (!$table_exists): ?>
        <div class="empty-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Material requests table not available. Please check database configuration.</p>
        </div>
    <?php elseif (empty($material_requests)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>Wala pang natanggap na order para i-release. Lalabas ito kapag na-request na ng Engineering kay Procurement, na-order na, at natanggap na ng Warehouse.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Project</th>
                        <th>Material</th>
                        <th>Requested Qty</th>
                        <th>Released Qty</th>
                        <th>Current Stock</th>
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $projectReleaseShown = []; ?>
                    <?php foreach ($material_requests as $request): ?>
                    <tr>
                        <td><strong>#<?php echo $request['id']; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($request['project_code'] ?? 'N/A'); ?></strong>
                            <div style="color:#64748b;font-size:0.8rem;"><?php echo htmlspecialchars($request['project_location'] ?: 'Walang location'); ?></div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($request['material_name'] ?? 'N/A'); ?>
                            <?php if (!empty($request['pr_number'])): ?>
                                <div style="color:#64748b;font-size:0.8rem;"><?php echo htmlspecialchars($request['pr_number']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($request['quantity']); ?> <?php echo htmlspecialchars($request['unit'] ?? ''); ?></td>
                        <td><?php echo number_format($request['released_quantity']); ?> <?php echo htmlspecialchars($request['unit'] ?? ''); ?></td>
                        <td class="<?php echo $request['current_stock'] <= 10 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format($request['current_stock']); ?> <?php echo htmlspecialchars($request['unit'] ?? ''); ?></td>
                        <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                        <td><span class="badge badge-<?php echo $request['status'] === 'approved' ? 'success' : htmlspecialchars($request['status']); ?>"><?php echo $request['status'] === 'approved' ? 'Released to site' : ($request['status'] === 'partial' ? 'Partial release' : ucfirst($request['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars($request['requested_by_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($request['status'] === 'pending' && canWriteDepartmentData('warehouse')): ?>
                                <?php if (empty($projectReleaseShown[(int) $request['project_id']])): ?>
                                    <?php $projectReleaseShown[(int) $request['project_id']] = true; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('I-release ang lahat ng pending materials ng project na ito, sa dami na ni-request?');">
                                        <input type="hidden" name="release_project_requests" value="1">
                                        <input type="hidden" name="project_id" value="<?php echo (int) $request['project_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-arrow-up"></i> Release all to site</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int) $request['current_stock'] < (int) $request['quantity'] && empty($request['purchase_request_id'])): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button type="submit" name="send_to_procurement" class="btn btn-sm btn-warning" onclick="return confirm('Send the shortage to Procurement?')">Buy shortage</button>
                                    </form>
                                <?php elseif (!empty($request['pr_number'])): ?>
                                    <?php if (canViewModule('procurement')): ?>
                                        <a href="<?php echo APP_URL; ?>modules/procurement/purchase_requests.php?action=view&id=<?php echo (int) $request['purchase_request_id']; ?>" class="btn btn-sm btn-info"><?php echo htmlspecialchars($request['pr_number']); ?></a>
                                    <?php else: ?>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($request['pr_number']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!empty($request['pr_number']) && canViewModule('procurement')): ?>
                                    <a href="<?php echo APP_URL; ?>modules/procurement/purchase_requests.php?action=view&id=<?php echo (int) $request['purchase_request_id']; ?>" class="btn btn-sm btn-info"><?php echo htmlspecialchars($request['pr_number']); ?></a>
                                <?php else: ?>
                                    <span class="text-muted"><?php echo !empty($request['pr_number']) ? htmlspecialchars($request['pr_number']) : '-'; ?></span>
                                <?php endif; ?>
                                <a href="stock.php?action=out" class="btn btn-sm btn-outline">Stock-Out</a>
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
