<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['warehouse']);

$page_title = 'Receive Purchases';

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
        $stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status IN ('sent', 'confirmed', 'ready_for_warehouse')");
        $stmt->execute([$po_id]);
        $po = $stmt->fetch();
        if (!$po) {
            $_SESSION['error'] = 'This purchase order is not waiting to be received.';
        } else {
            $items = receivePurchaseLineItems($pdo, $po);
            if (empty($items)) {
                $_SESSION['error'] = 'No line items found for this purchase order. Add materials on the related purchase request first.';
            } else {
                $pdo->beginTransaction();
                $stock = $pdo->prepare("UPDATE materials SET current_stock = current_stock + ? WHERE id = ?");
                $move = $pdo->prepare("INSERT INTO stock_movements (material_id, movement_type, quantity, reason, created_by) VALUES (?, 'in', ?, ?, ?)");
                $markItem = $pdo->prepare("UPDATE purchase_order_items SET received_quantity = quantity WHERE purchase_order_id = ? AND material_id = ?");
                foreach ($items as $item) {
                    $qty = (int)$item['quantity'] - (int)($item['received_quantity'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    $stock->execute([$qty, (int)$item['material_id']]);
                    $move->execute([(int)$item['material_id'], $qty, 'Received PO ' . $po['po_number'], $_SESSION['user_id']]);
                    try {
                        $markItem->execute([(int)$po['id'], (int)$item['material_id']]);
                    } catch (PDOException $e) {
                        // table may not have received_quantity on older schemas
                    }
                }
                $pdo->prepare("UPDATE purchase_orders SET status = 'delivered', received_at = NOW() WHERE id = ?")->execute([$po_id]);
                if (!empty($po['purchase_request_id'])) {
                    $pdo->prepare("UPDATE purchase_requests SET status = 'received' WHERE id = ?")->execute([$po['purchase_request_id']]);
                }
                $pdo->commit();
                logActivity($_SESSION['user_id'], 'Received purchase order', 'Warehouse', 'PO: ' . $po['po_number']);
                $_SESSION['success'] = 'Purchase order ' . $po['po_number'] . ' received and stock updated.';
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = userDatabaseError($e, 'Warehouse');
    }
    header('Location: receive_purchases.php');
    exit();
}

$orders = [];
try {
    $orders = $pdo->query("
        SELECT po.*, pr.pr_number, p.project_code, p.name AS project_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN projects p ON pr.project_id = p.id
        WHERE po.status IN ('sent', 'confirmed', 'ready_for_warehouse', 'delivered')
        ORDER BY FIELD(po.status, 'ready_for_warehouse', 'confirmed', 'sent', 'delivered'), po.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Warehouse');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> Receive Purchases</h1>
    <a href="stock.php?action=in" class="btn btn-outline">Manual Stock-In</a>
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

<p class="muted-note">Incoming purchase orders from Procurement. Receiving increments material stock and logs a stock-in movement.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>PR #</th>
                    <th>Project</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="table-empty">No purchase orders to receive.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['pr_number'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(trim(($order['project_code'] ?? '') . ' ' . ($order['project_name'] ?? '')) ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($order['supplier']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                        <td>
                            <?php if (in_array($order['status'], ['sent', 'confirmed', 'ready_for_warehouse'], true) && canWriteDepartmentData('warehouse')): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Receive this PO and add quantities to stock?');">
                                <input type="hidden" name="po_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" name="receive_po" class="btn btn-sm btn-primary">Receive</button>
                            </form>
                            <?php else: ?>
                            <span class="muted-note">Received<?php echo !empty($order['received_at']) ? ' ' . date('M d, Y', strtotime($order['received_at'])) : ''; ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
