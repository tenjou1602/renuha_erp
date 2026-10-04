<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Material Requests';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_request'])) {
    if (!canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'You cannot accept material requests.';
        header('Location: received_requests.php');
        exit();
    }
    $request_id = (int) ($_POST['request_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE purchase_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        $_SESSION['success'] = $stmt->rowCount()
            ? 'Request accepted. Next: compare supplier prices and accept one.'
            : 'This request is no longer waiting to be accepted.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e, 'Procurement');
    }
    header('Location: received_requests.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_warehouse'])) {
    if (!canMarkReadyForWarehouse()) {
        $_SESSION['error'] = 'You cannot send this order to Warehouse.';
        header('Location: received_requests.php');
        exit();
    }
    $po_id = (int) ($_POST['po_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'ready_for_warehouse' WHERE id = ? AND status IN ('confirmed', 'sent')");
        $stmt->execute([$po_id]);
        $_SESSION['success'] = $stmt->rowCount()
            ? 'Naipadala na sa Warehouse ang order. Tatanggapin nila ito, tapos ire-release sa site ng Engineering.'
            : 'Set the order to Sent or Confirmed before sending it to Warehouse.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e, 'Procurement');
    }
    header('Location: received_requests.php');
    exit();
}

$requests = [];
try {
    $requests = $pdo->query("
        SELECT pr.id, pr.pr_number, pr.purpose, pr.priority, pr.status, pr.total_amount, pr.created_at,
               p.project_code, p.name AS project_name, p.location, u.full_name AS requestor_name,
               (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.purchase_request_id = pr.id) AS item_count,
               (SELECT q.id FROM quotations q WHERE q.purchase_request_id = pr.id AND q.status = 'accepted' ORDER BY q.id DESC LIMIT 1) AS accepted_quote_id,
               (SELECT po.id FROM purchase_orders po WHERE po.purchase_request_id = pr.id AND po.status <> 'cancelled' ORDER BY po.id DESC LIMIT 1) AS order_id,
               (SELECT po.status FROM purchase_orders po WHERE po.purchase_request_id = pr.id AND po.status <> 'cancelled' ORDER BY po.id DESC LIMIT 1) AS order_status
        FROM purchase_requests pr
        LEFT JOIN projects p ON p.id = pr.project_id
        LEFT JOIN users u ON u.id = pr.requestor_id
        WHERE pr.status IN ('pending', 'approved', 'confirmed', 'ordered', 'received')
        ORDER BY pr.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Procurement');
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-inbox"></i> Material Requests</h1>
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

<p style="color:#64748b;">Sunod lang ito: 1) Tanggapin ang request mula sa Engineering. 2) Ikumpara ang presyo at tanggapin ang isang supplier. 3) Gumawa ng purchase order. 4) Ipadala sa Warehouse. Kapag natanggap at na-release na sa site, ico-complete ng Engineering ang request.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Project</th>
                    <th>From</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$requests): ?>
                    <tr><td colspan="7">No received requests yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($requests as $request): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($request['pr_number']); ?></strong><br>
                        <?php echo htmlspecialchars($request['purpose']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($request['project_code'] ? $request['project_code'] . ' - ' . $request['project_name'] : '—'); ?></td>
                    <td><?php echo htmlspecialchars($request['requestor_name'] ?: '—'); ?></td>
                    <td><?php echo (int) $request['item_count']; ?></td>
                    <td>₱<?php echo number_format((float) $request['total_amount'], 2); ?></td>
                    <td><span class="badge badge-<?php echo htmlspecialchars($request['status']); ?>"><?php echo ucfirst($request['status']); ?></span></td>
                    <td style="white-space:nowrap;">
                        <?php if (in_array($request['order_status'] ?? '', ['sent', 'confirmed'], true) && canMarkReadyForWarehouse()): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="send_to_warehouse" value="1">
                                <input type="hidden" name="po_id" value="<?php echo (int) $request['order_id']; ?>">
                                <button class="btn btn-sm btn-success" type="submit">Send to Warehouse</button>
                            </form>
                        <?php elseif (($request['order_status'] ?? '') === 'ready_for_warehouse'): ?>
                            <span class="badge badge-info">Sent to Warehouse</span>
                        <?php elseif (in_array($request['order_status'] ?? '', ['delivered'], true) || $request['status'] === 'received'): ?>
                            <span class="badge badge-success">Received by Warehouse</span>
                        <?php elseif (!empty($request['order_id']) && ($request['order_status'] ?? '') === 'draft' && canWriteDepartmentData('procurement')): ?>
                            <a class="btn btn-sm btn-warning" href="purchase_orders.php?action=edit&id=<?php echo (int) $request['order_id']; ?>">Finish order</a>
                        <?php elseif (!empty($request['accepted_quote_id']) && canWriteDepartmentData('procurement')): ?>
                            <a class="btn btn-sm btn-primary" href="purchase_orders.php?action=add&quote=<?php echo (int) $request['accepted_quote_id']; ?>">Create order</a>
                        <?php elseif ($request['status'] === 'pending' && canWriteDepartmentData('procurement')): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="accept_request" value="1">
                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                <button class="btn btn-sm btn-primary" type="submit">Accept</button>
                            </form>
                        <?php elseif (in_array($request['status'], ['approved', 'confirmed'], true) && canWriteDepartmentData('procurement')): ?>
                            <a class="btn btn-sm btn-primary" href="quotations.php?action=add&pr=<?php echo (int) $request['id']; ?>">Compare prices</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline" href="purchase_requests.php?action=view&id=<?php echo (int) $request['id']; ?>">Open</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
