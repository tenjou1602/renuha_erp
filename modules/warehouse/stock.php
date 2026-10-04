<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/warehouse_fifo.php';
require_once '../../includes/warehouse_release.php';

requireDepartment(['warehouse']);

try {
    ensureWarehouseFifoSchema($pdo);
    ensureWarehouseFifoBatches($pdo);
} catch (PDOException $e) {
    error_log('[Runeha ERP] fifo schema: ' . $e->getMessage());
}

$page_title = 'Stock Management';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['receive_po_line']) && !canWriteDepartmentData('warehouse')) {
        $_SESSION['error'] = 'Administrators have view-only access to warehouse stock.';
        header('Location: stock.php?action=in');
        exit();
    }

    if (isset($_POST['receive_po_line'])) {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $expiration = trim($_POST['expiration_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        try {
            $stmt = $pdo->prepare("
                SELECT poi.id, poi.material_id, poi.quantity, poi.received_quantity, poi.purchase_order_id,
                       po.po_number, po.status
                FROM purchase_order_items poi
                INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
                WHERE poi.id = ? AND po.status IN ('sent', 'confirmed', 'ready_for_warehouse')
            ");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            $remaining = $item ? (int) $item['quantity'] - (int) $item['received_quantity'] : 0;
            if (!$item || $remaining <= 0) {
                throw new RuntimeException('Wala nang quantity na matatanggap sa line na ito.');
            }
            $pdo->beginTransaction();
            $batch = warehouseReceiveBatch(
                $pdo,
                (int) $item['material_id'],
                $remaining,
                (int) $_SESSION['user_id'],
                'Received PO ' . $item['po_number'],
                $expiration !== '' ? $expiration : null,
                $location !== '' ? $location : null,
                $notes !== '' ? $notes : null
            );
            $pdo->prepare("UPDATE purchase_order_items SET received_quantity = quantity WHERE id = ?")->execute([$item_id]);
            $open = $pdo->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = ? AND received_quantity < quantity");
            $open->execute([(int) $item['purchase_order_id']]);
            if ((int) $open->fetchColumn() === 0) {
                $pdo->prepare("UPDATE purchase_orders SET status = 'delivered', received_at = NOW() WHERE id = ?")->execute([(int) $item['purchase_order_id']]);
            }
            $pdo->commit();
            try {
                syncReceivedOrdersForRelease($pdo);
                syncPurchasedMaterialsToAccounting($pdo);
            } catch (Throwable $syncError) {
                error_log('[Runeha ERP] sync received orders for release: ' . $syncError->getMessage());
            }
            logActivity($_SESSION['user_id'], 'Received stock', 'Warehouse', $item['po_number'] . ' batch ' . $batch . ' qty ' . $remaining);
            $_SESSION['success'] = 'Natanggap ang ' . number_format($remaining) . '. Batch ' . $batch . ' (FIFO). Expiration ay optional.';
            header('Location: stock.php?action=in');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof PDOException ? userDatabaseError($e) : $e->getMessage();
        }
    }
}

$stock_action = $_GET['action'] ?? '';

// Get incoming purchase lines. Quantity and batch come from the order.
$incoming_lines = [];
try {
    $incoming_lines = $pdo->query("
        SELECT poi.id, poi.quantity, poi.received_quantity, m.material_code, m.name AS material_name, m.unit,
               po.po_number, po.supplier, p.project_code, p.name AS project_name
        FROM purchase_order_items poi
        INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
        INNER JOIN materials m ON m.id = poi.material_id
        LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        LEFT JOIN projects p ON p.id = pr.project_id
        WHERE po.status IN ('sent', 'confirmed', 'ready_for_warehouse')
          AND poi.received_quantity < poi.quantity
        ORDER BY po.po_number, m.name
    ")->fetchAll();
} catch (PDOException $e) {
    $incoming_lines = [];
}
$site_releases = [];
try {
    $site_releases = $pdo->query("
        SELECT sm.quantity, sm.batch_number, sm.reason, sm.created_at,
               m.material_code, m.name AS material_name, m.unit,
               p.project_code, p.name AS project_name, p.location
        FROM stock_movements sm
        LEFT JOIN materials m ON m.id = sm.material_id
        LEFT JOIN projects p ON p.id = sm.project_id
        WHERE sm.movement_type = 'out' AND sm.project_id IS NOT NULL
        ORDER BY sm.created_at DESC
        LIMIT 50
    ")->fetchAll();
} catch (PDOException $e) {
    $site_releases = [];
}

// Get warehouse stock
$warehouse_stock = [];
try {
    $query = "
        SELECT
            m.id AS material_id,
            m.name AS material_name,
            m.material_code,
            m.unit,
            COALESCE(ws.quantity, m.current_stock) AS quantity,
            ws.location,
            ws.batch_number,
            ws.expiration_date,
            COALESCE(ws.created_at, m.updated_at, m.created_at) AS created_at
        FROM materials m
        LEFT JOIN warehouse_stock ws ON ws.material_id = m.id
        WHERE m.status = 'active'
          AND (ws.id IS NOT NULL OR m.current_stock <> 0)
        ORDER BY ws.created_at ASC, ws.id ASC
    ";
    $warehouse_stock = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> <?php echo $page_title; ?></h1>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="receive_purchases.php" class="btn btn-outline"><i class="fas fa-truck-loading"></i> Receive Purchases</a>
        <a href="material_requests.php" class="btn btn-outline"><i class="fas fa-box-open"></i> Inventory Release</a>
        <a href="movements.php" class="btn btn-outline"><i class="fas fa-exchange-alt"></i> Movements</a>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>

<?php if (canWriteDepartmentData('warehouse') && $stock_action !== 'out'): ?>
<div class="card" id="stock-in">
    <h3><i class="fas fa-arrow-down"></i> Receive Stock</h3>
    <p style="color:#64748b;">Ang quantity ay galing sa purchase order. Ang batch number ay awtomatikong FIFO: pinakamatandang batch ang unang ilalabas. Puwedeng walang expiration.</p>
    <?php if (!$incoming_lines): ?>
        <p style="color:#94a3b8;">Wala pang purchase order na hinihintay. Kapag nag-send ang Procurement, dito na lalabas ang material, quantity, at batch.</p>
    <?php else: ?>
        <?php $batchPreview = 1; ?>
        <?php foreach ($incoming_lines as $line): ?>
            <?php $remaining = (int) $line['quantity'] - (int) $line['received_quantity']; ?>
            <form method="POST" style="border-top:1px solid #e2e8f0;padding-top:1rem;margin-top:1rem;">
                <input type="hidden" name="receive_po_line" value="1">
                <input type="hidden" name="item_id" value="<?php echo (int) $line['id']; ?>">
                <p><strong><?php echo htmlspecialchars($line['po_number']); ?></strong>
                    <?php if (!empty($line['project_code'])): ?> · <?php echo htmlspecialchars($line['project_code'] . ' ' . $line['project_name']); ?><?php endif; ?>
                    <?php if (!empty($line['supplier'])): ?> · <?php echo htmlspecialchars($line['supplier']); ?><?php endif; ?>
                </p>
                <div class="form-row">
                    <div class="form-group">
                        <label>Material</label>
                        <input type="text" readonly value="<?php echo htmlspecialchars($line['material_code'] . ' - ' . $line['material_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="text" readonly value="<?php echo number_format($remaining) . ' ' . $line['unit']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Batch number (FIFO)</label>
                        <input type="text" readonly value="<?php echo 'FIFO-' . date('Ymd') . '-' . str_pad((string) $batchPreview, 3, '0', STR_PAD_LEFT); ?>">
                    </div>
                    <div class="form-group">
                        <label>Expiration (optional)</label>
                        <input type="date" name="expiration_date">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Warehouse location</label>
                        <input type="text" name="location" placeholder="Aisle / shelf, puwedeng blangko">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Delivery notes">
                    </div>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Receive Stock</button>
            </form>
            <?php $batchPreview++; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($stock_action === 'out'): ?>
<div class="card" id="stock-out">
    <h3><i class="fas fa-arrow-up"></i> Release to project site</h3>
    <p style="color:#64748b;">Dito lumalabas ang na-release sa Material Release. Ang release mismo ay doon pinipindot kapag may request at may stock.</p>
    <?php if (!$site_releases): ?>
        <p style="color:#94a3b8;">Wala pang na-release mula sa Material Release.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Project</th><th>Location</th><th>Material</th><th>Qty</th><th>FIFO batch</th><th>When</th></tr></thead>
                <tbody>
                    <?php foreach ($site_releases as $release): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(trim(($release['project_code'] ?? '') . ' ' . ($release['project_name'] ?? '')) ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($release['location'] ?? '') ?: '—'; ?></td>
                        <td><?php echo htmlspecialchars(trim(($release['material_code'] ?? '') . ' ' . ($release['material_name'] ?? ''))); ?></td>
                        <td><?php echo number_format((int) $release['quantity']); ?> <?php echo htmlspecialchars($release['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($release['batch_number'] ?? '—'); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($release['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Warehouse Stock List -->
<div class="card">
    <h3><i class="fas fa-list"></i> Warehouse stock, FIFO</h3>
    <p style="color:#64748b;">Pinakamatandang batch ang nasa unahan. Iyon ang unang lalabas sa release.</p>
    <?php if (empty($warehouse_stock)): ?>
        <div class="empty-state">
            <i class="fas fa-warehouse"></i>
            <p>No stock in warehouse.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" id="stockTable">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Quantity</th>
                        <th>Location</th>
                        <th>Batch</th>
                        <th>Expiration</th>
                        <th>Received</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warehouse_stock as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['material_name'] ?? 'N/A'); ?></strong><br>
                            <small><?php echo htmlspecialchars($item['material_code'] ?? ''); ?></small>
                        </td>
                        <td><?php echo number_format($item['quantity']) . ' ' . htmlspecialchars($item['unit'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['location'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['batch_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $item['expiration_date'] ? date('M d, Y', strtotime($item['expiration_date'])) : '—'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                        <td>
                            <a href="inventory_history.php?material_id=<?php echo (int) $item['material_id']; ?>" class="btn btn-sm btn-info">History</a>
                            <a href="material_requests.php" class="btn btn-sm btn-warning">Release</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php if (in_array($stock_action, ['in', 'out'], true)): ?>
<script>
document.getElementById(<?php echo json_encode($stock_action === 'out' ? 'stock-out' : 'stock-in'); ?>)?.scrollIntoView({behavior:'smooth', block:'start'});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>