<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/warehouse_fifo.php';
require_once '../../includes/warehouse_release.php';

requireDepartment(['warehouse']);

try {
    ensureWarehouseFifoSchema($pdo);
} catch (PDOException $e) {
    error_log('[Runeha ERP] fifo schema: ' . $e->getMessage());
}

$page_title = 'Receiving';

function receivePurchaseLineItems(PDO $pdo, array $po): array {
    $items = [];
    try {
        $stmt = $pdo->prepare("SELECT material_id, quantity, received_quantity FROM purchase_order_items WHERE purchase_order_id = ?");
        $stmt->execute([(int)$po['id']]);
        $items = $stmt->fetchAll();
    } catch (PDOException $e) {
        $items = [];
    }
    if (empty($items) && !empty($po['purchase_request_id'])) {
        $stmt = $pdo->prepare("SELECT material_id, quantity, 0 AS received_quantity FROM purchase_request_items WHERE purchase_request_id = ?");
        $stmt->execute([(int)$po['purchase_request_id']]);
        $items = $stmt->fetchAll();
        $ins = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, material_id, quantity, unit_price, received_quantity) VALUES (?, ?, ?, 0, 0)");
        foreach ($items as $item) {
            $ins->execute([(int)$po['id'], (int)$item['material_id'], (int)$item['quantity']]);
        }
    }
    return $items;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_po'])) {
    if (!canWriteDepartmentData('warehouse')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: receive_purchases.php');
        exit();
    }
    $po_id = (int)($_POST['po_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status = 'ready_for_warehouse' AND received_at IS NULL");
        $stmt->execute([$po_id]);
        $po = $stmt->fetch();
        if (!$po) {
            $_SESSION['error'] = 'This purchase order is not ready to receive. Accounting must mark the materials paid first.';
        } else {
            $items = receivePurchaseLineItems($pdo, $po);
            if (empty($items)) {
                $_SESSION['error'] = 'No line items found for this purchase order. Add materials on the related purchase request first.';
            } else {
                $pdo->beginTransaction();
                $markItem = $pdo->prepare("UPDATE purchase_order_items SET received_quantity = quantity WHERE purchase_order_id = ? AND material_id = ?");
                foreach ($items as $item) {
                    $qty = (int)$item['quantity'] - (int)($item['received_quantity'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    warehouseReceiveBatch($pdo, (int)$item['material_id'], $qty, (int)$_SESSION['user_id'], 'Received PO ' . $po['po_number']);
                    try {
                        $markItem->execute([(int)$po['id'], (int)$item['material_id']]);
                    } catch (PDOException $e) {
                    }
                }
                $pdo->prepare("UPDATE purchase_orders SET status = 'delivered', received_at = NOW() WHERE id = ? AND status <> 'delivered'")->execute([$po_id]);
                if (!empty($po['purchase_request_id'])) {
                    $pdo->prepare("UPDATE purchase_requests SET status = 'completed' WHERE id = ?")->execute([$po['purchase_request_id']]);
                }
                notify('procurement', 'po_delivered', 'Purchase order delivered', $po['po_number'] . ' was received by Warehouse and is now completed.', 'modules/procurement/purchase_orders.php', null, 'purchase_order', $po_id);
                notify('accounting', 'po_delivered', 'Purchase order delivered', $po['po_number'] . ' was received by Warehouse. Record the supplier payment when the invoice is ready.', 'modules/accounting/expenses.php', null, 'purchase_order', $po_id);
                $pdo->commit();
                try {
                    syncReceivedOrdersForRelease($pdo);
                    syncPurchasedMaterialsToAccounting($pdo);
                } catch (Throwable $syncError) {
                    error_log('[Runeha ERP] sync received orders for release: ' . $syncError->getMessage());
                }
                logActivity($_SESSION['user_id'], 'Received purchase order', 'Warehouse', 'PO: ' . $po['po_number']);
                $_SESSION['success'] = 'Purchase order ' . $po['po_number'] . ' received and stock updated.';
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Warehouse') : $e->getMessage();
    }
    header('Location: receive_purchases.php');
    exit();
}

$receive_filter = $_GET['status'] ?? 'all';
if (!in_array($receive_filter, ['all', 'pending', 'delivered'], true)) {
    $receive_filter = 'all';
}
$status_sql = "po.status IN ('ready_for_warehouse', 'delivered')";
if ($receive_filter === 'pending') {
    $status_sql = "po.status = 'ready_for_warehouse'";
} elseif ($receive_filter === 'delivered') {
    $status_sql = "po.status = 'delivered'";
}
$po_materials = purchaseOrderMaterialsByOrder($pdo);
$orders = [];
try {
    $orders = $pdo->query("
        SELECT po.*, pr.pr_number, p.project_code, p.name AS project_name,
               s.company_name AS supplier_company
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN projects p ON COALESCE(po.project_id, pr.project_id) = p.id
        LEFT JOIN suppliers s ON s.id = (
            SELECT s2.id FROM suppliers s2
            WHERE LOWER(s2.name) = LOWER(po.supplier)
               OR (s2.company_name IS NOT NULL AND LOWER(s2.company_name) = LOWER(po.supplier))
            ORDER BY s2.id
            LIMIT 1
        )
        WHERE $status_sql
        ORDER BY FIELD(po.status, 'ready_for_warehouse', 'delivered'), po.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Warehouse');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> Receiving</h1>
    <a href="stock.php?action=in" class="btn btn-outline">Stock-In</a>
    <a href="movements.php" class="btn btn-outline">Movements</a>
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

<p class="muted-note">A purchase order appears here after Accounting marks its materials paid. Receive Materials adds those items to stock.</p>
<p>
    <a class="btn btn-sm <?php echo $receive_filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" href="receive_purchases.php">All</a>
    <a class="btn btn-sm <?php echo $receive_filter === 'pending' ? 'btn-primary' : 'btn-outline'; ?>" href="receive_purchases.php?status=pending">For receiving</a>
    <a class="btn btn-sm <?php echo $receive_filter === 'delivered' ? 'btn-primary' : 'btn-outline'; ?>" href="receive_purchases.php?status=delivered">Delivered</a>
</p>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Materials</th>
                    <th>PR #</th>
                    <th>Project</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="table-empty">No paid purchase orders are waiting. They appear after Accounting marks the materials paid.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr id="po-<?php echo (int) $order['id']; ?>" <?php echo (int) ($_GET['po'] ?? 0) === (int) $order['id'] ? 'style="outline:2px solid #4e73df;"' : ''; ?>>
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td>
                            <?php foreach ($po_materials[(int) $order['id']] ?? [] as $line): ?>
                                <div><?php echo htmlspecialchars(($line['material_name'] ?: 'Material') . ' × ' . (int) $line['quantity'] . ' ' . ($line['unit'] ?? '')); ?></div>
                            <?php endforeach; ?>
                            <?php if (empty($po_materials[(int) $order['id']])): ?><span style="color:#94a3b8;">No materials on this PO</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($order['pr_number'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(trim(($order['project_code'] ?? '') . ' ' . ($order['project_name'] ?? '')) ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($order['supplier_company'] ?? '') !== '' ? htmlspecialchars($order['supplier_company']) : 'Company name not set'; ?><div style="color:#64748b;font-size:0.8rem;"><?php echo htmlspecialchars($order['supplier']); ?></div></td>
                        <td><?php
                            $whLabel = $order['status'] === 'delivered' ? 'Delivered' : 'For receiving';
                            echo '<span class="badge badge-' . ($whLabel === 'Delivered' ? 'success' : 'warning') . '">' . $whLabel . '</span>';
                        ?></td>
                        <td>
                            <?php if ($order['status'] === 'ready_for_warehouse' && canWriteDepartmentData('warehouse')): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Receive this PO and add quantities to stock?');">
                                <input type="hidden" name="po_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" name="receive_po" class="btn btn-sm btn-primary">Receive Materials</button>
                            </form>
                            <?php else: ?>
                            <a href="movements.php" class="btn btn-sm btn-info">Movements</a>
                            <a href="stock.php" class="btn btn-sm btn-outline">Stock</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ((int) ($_GET['po'] ?? 0) > 0): ?>
<script>
document.getElementById('po-<?php echo (int) $_GET['po']; ?>')?.scrollIntoView({behavior:'smooth', block:'center'});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
