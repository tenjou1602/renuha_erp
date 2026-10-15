<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';

requireDepartment(['procurement']);

$page_title = 'Purchase Orders';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generatePONumber() {
    return generateNumber('PO', 'purchase_orders', 'po_number');
}

function savePurchaseOrderItems(PDO $pdo, int $orderId, int $requestId): float
{
    $quote = $pdo->prepare("SELECT id FROM quotations WHERE purchase_request_id = ? AND status = 'accepted' ORDER BY id DESC LIMIT 1");
    $quote->execute([$requestId]);
    $quoteId = (int) $quote->fetchColumn();
    $prices = [];
    if ($quoteId > 0) {
        $priceStmt = $pdo->prepare("SELECT purchase_request_item_id, unit_price FROM quotation_items WHERE quotation_id = ?");
        $priceStmt->execute([$quoteId]);
        foreach ($priceStmt->fetchAll() as $row) {
            $prices[(int) $row['purchase_request_item_id']] = (float) $row['unit_price'];
        }
    }
    $items = $pdo->prepare("SELECT id, material_id, quantity FROM purchase_request_items WHERE purchase_request_id = ?");
    $items->execute([$requestId]);
    $rows = $items->fetchAll();
    if (!$rows) {
        throw new RuntimeException('This purchase request has no materials.');
    }
    foreach ($rows as $item) {
        $unitPrice = $prices[(int) $item['id']] ?? 0;
        if ($unitPrice <= 0) {
            throw new RuntimeException('Enter the supplier unit price for every material in the price comparison first.');
        }
    }
    $pdo->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = ?")->execute([$orderId]);
    $insert = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, material_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    $total = 0.0;
    foreach ($rows as $item) {
        $unitPrice = $prices[(int) $item['id']];
        $total += round((float) $item['quantity'] * $unitPrice, 2);
        $insert->execute([$orderId, $item['material_id'], $item['quantity'], $unitPrice]);
    }
    return round($total, 2);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_order']) || isset($_POST['update_order'])) && !canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'Access denied. Administrators have view-only access to procurement records.';
        header('Location: purchase_orders.php');
        exit();
    }

    if (isset($_POST['add_order']) || isset($_POST['update_order'])) {
        $po_number = $_POST['po_number'] ?? generatePONumber();
        $purchase_request_id = (int)($_POST['purchase_request_id'] ?? 0);
        $purchase_request_id = $purchase_request_id > 0 ? $purchase_request_id : null;
        $supplier = trim($_POST['supplier'] ?? '');
        $supplier_contact = trim($_POST['supplier_contact'] ?? '');
        $order_date = $_POST['order_date'] ?? date('Y-m-d');
        $delivery_date = trim((string) ($_POST['delivery_date'] ?? ''));
        $delivery_date = $delivery_date !== '' ? $delivery_date : null;
        $payment_terms = trim($_POST['payment_terms'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        
        $errors = [];
        $acceptedQuote = null;
        $orderProjectId = null;
        if (!$purchase_request_id) {
            $errors[] = 'Select the purchase request so the order has materials and a total.';
        } else {
            $projectStmt = $pdo->prepare("SELECT project_id FROM purchase_requests WHERE id = ?");
            $projectStmt->execute([$purchase_request_id]);
            $orderProjectId = (int) $projectStmt->fetchColumn();
            if ($orderProjectId <= 0) {
                $errors[] = 'The purchase request must be linked to a project before an order can be created.';
                $orderProjectId = null;
            }
            $accepted = $pdo->prepare("SELECT supplier, supplier_contact FROM quotations WHERE purchase_request_id = ? AND status = 'accepted' ORDER BY id DESC LIMIT 1");
            $accepted->execute([$purchase_request_id]);
            $acceptedQuote = $accepted->fetch();
            if (!$acceptedQuote) {
                $errors[] = 'Accept one supplier price before creating the purchase order.';
            } else {
                $supplier = trim((string) $acceptedQuote['supplier']);
                $supplier_contact = trim((string) ($acceptedQuote['supplier_contact'] ?? ''));
                if ($supplier_contact === '' || strcasecmp($supplier_contact, $supplier) === 0) {
                    $phoneStmt = $pdo->prepare("SELECT phone FROM suppliers WHERE name = ? AND phone IS NOT NULL AND phone <> '' LIMIT 1");
                    $phoneStmt->execute([$supplier]);
                    $phone = trim((string) $phoneStmt->fetchColumn());
                    if ($phone !== '') {
                        $supplier_contact = $phone;
                    }
                }
            }
        }
        if ($supplier === '') {
            $errors[] = 'Supplier is required.';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                if (isset($_POST['add_order'])) {
                    $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, purchase_request_id, project_id, supplier, supplier_contact, order_date, delivery_date, payment_terms, total_amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
                    $stmt->execute([$po_number, $purchase_request_id, $orderProjectId, $supplier, $supplier_contact, $order_date, $delivery_date, $payment_terms, $status, $_SESSION['user_id']]);
                    $orderId = (int) $pdo->lastInsertId();
                    $poTotal = savePurchaseOrderItems($pdo, $orderId, $purchase_request_id);
                    $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?")->execute([$poTotal, $orderId]);
                    $pdo->prepare("UPDATE material_requests SET flow_status = 'accounting' WHERE purchase_request_id = ? AND flow_status = 'procurement'")->execute([$purchase_request_id]);
                    if (!in_array($status, ['draft', 'cancelled'], true)) {
                        $pdo->prepare("UPDATE purchase_requests SET status = 'ordered', total_amount = ? WHERE id = ? AND status IN ('approved', 'confirmed', 'ordered')")->execute([$poTotal, $purchase_request_id]);
                    } else {
                        $pdo->prepare("UPDATE purchase_requests SET total_amount = ? WHERE id = ?")->execute([$poTotal, $purchase_request_id]);
                    }
                    
                    logActivity($_SESSION['user_id'], 'Created purchase order', 'Procurement', "PO: $po_number");
                    $_SESSION['success'] = "Purchase order created for ₱" . number_format($poTotal, 2) . " from the purchase request.";
                } else {
                    $stmt = $pdo->prepare("UPDATE purchase_orders SET purchase_request_id = ?, project_id = ?, supplier = ?, supplier_contact = ?, order_date = ?, delivery_date = ?, payment_terms = ?, status = ? WHERE id = ?");
                    $stmt->execute([$purchase_request_id, $orderProjectId, $supplier, $supplier_contact, $order_date, $delivery_date, $payment_terms, $status, $id]);
                    $poTotal = savePurchaseOrderItems($pdo, $id, $purchase_request_id);
                    $pdo->prepare("UPDATE purchase_orders SET total_amount = ? WHERE id = ?")->execute([$poTotal, $id]);
                    if (!in_array($status, ['draft', 'cancelled'], true)) {
                        $pdo->prepare("UPDATE purchase_requests SET status = 'ordered', total_amount = ? WHERE id = ? AND status IN ('approved', 'confirmed', 'ordered')")->execute([$poTotal, $purchase_request_id]);
                    }
                    
                    logActivity($_SESSION['user_id'], 'Updated purchase order', 'Procurement', "PO: $po_number");
                    $_SESSION['success'] = "Purchase order updated successfully!";
                }
                if ($status === 'confirmed' || $status === 'ready_for_warehouse') {
                    try {
                        notifyPurchaseOrderApproved($po_number, $supplier, lookupSupplierEmail($supplier));
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                }
                $pdo->commit();
                if (isset($_POST['add_order']) && !in_array($status, ['draft', 'cancelled'], true)) {
                    notify('accounting', 'purchase_order', 'Purchase order', $po_number . ' was created and is waiting for Accounting.', 'modules/accounting/project_financials.php', $orderProjectId, 'purchase_order', (int) $orderId);
                }
                header('Location: purchase_orders.php');
                exit();
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = userDatabaseError($e);
            }
        } else {
            $error = implode(' ', $errors);
        }
    }
}

if (isset($_POST['mark_ready_warehouse']) && canMarkReadyForWarehouse()) {
    $po_id = (int)($_POST['po_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'ready_for_warehouse' WHERE id = ? AND status IN ('confirmed', 'sent')");
        $stmt->execute([$po_id]);
        logActivity($_SESSION['user_id'], 'Marked PO ready for warehouse', 'Procurement', 'PO id ' . $po_id);
        $_SESSION['success'] = 'Purchase marked ready for Warehouse receiving.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e);
    }
    header('Location: purchase_orders.php');
    exit();
}

// Approved, confirmed, and ordered requests can be linked to a purchase order.
$purchase_requests = $pdo->query("
    SELECT pr.*, u.full_name as requestor_name, q.id AS accepted_quote_id, q.supplier AS quote_supplier, q.supplier_contact AS quote_contact, q.total_amount AS quote_total
    FROM purchase_requests pr
    INNER JOIN quotations q ON q.purchase_request_id = pr.id AND q.status = 'accepted'
    LEFT JOIN users u ON pr.requestor_id = u.id
    WHERE pr.status IN ('approved', 'confirmed', 'ordered')
      AND q.id = (SELECT MAX(q2.id) FROM quotations q2 WHERE q2.purchase_request_id = pr.id AND q2.status = 'accepted')
    ORDER BY pr.created_at DESC
")->fetchAll();
$pr_items = [];
try {
    $itemRows = $pdo->query("SELECT pri.purchase_request_id, pri.quantity, pri.unit, qi.unit_price AS supplier_price, m.name AS material_name
        FROM purchase_request_items pri
        LEFT JOIN materials m ON m.id = pri.material_id
        LEFT JOIN quotations q ON q.id = (
            SELECT MAX(q2.id) FROM quotations q2
            WHERE q2.purchase_request_id = pri.purchase_request_id AND q2.status = 'accepted'
        )
        LEFT JOIN quotation_items qi ON qi.quotation_id = q.id AND qi.purchase_request_item_id = pri.id")->fetchAll();
    foreach ($itemRows as $itemRow) {
        $pr_items[(int) $itemRow['purchase_request_id']][] = [
            'name' => $itemRow['material_name'] ?: 'Material',
            'quantity' => (int) $itemRow['quantity'],
            'unit' => $itemRow['unit'],
            'price' => $itemRow['supplier_price'] === null ? null : (float) $itemRow['supplier_price'],
        ];
    }
} catch (PDOException $e) {
    $pr_items = [];
}
$suppliers = [];
try {
    $suppliers = $pdo->query("SELECT id, name, contact_person, phone FROM suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}

// Get purchase orders
$orders = [];
try {
    $query = "
        SELECT po.*, pr.pr_number, u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users u ON po.created_by = u.id
        ORDER BY po.created_at DESC
    ";
    $orders = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single order
$order_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT po.*, pr.pr_number, u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ?
    ");
    $stmt->execute([$id]);
    $order_details = $stmt->fetch() ?: null;
}
$prefill_pr = (int) ($_GET['pr'] ?? 0);
$prefill_supplier = '';
$prefill_contact = '';
if (is_array($order_details)) {
    $prefill_pr = (int) ($order_details['purchase_request_id'] ?? $prefill_pr);
}
if ($prefill_pr > 0) {
    $alreadyListed = false;
    foreach ($purchase_requests as $listed) {
        if ((int) $listed['id'] === $prefill_pr) {
            $alreadyListed = true;
            break;
        }
    }
    if (!$alreadyListed) {
        $extra = $pdo->prepare("
            SELECT pr.*, u.full_name as requestor_name, q.id AS accepted_quote_id, q.supplier AS quote_supplier, q.supplier_contact AS quote_contact, q.total_amount AS quote_total
            FROM purchase_requests pr
            LEFT JOIN users u ON pr.requestor_id = u.id
            LEFT JOIN quotations q ON q.id = (
                SELECT MAX(q2.id) FROM quotations q2 WHERE q2.purchase_request_id = pr.id AND q2.status = 'accepted'
            )
            WHERE pr.id = ?
        ");
        $extra->execute([$prefill_pr]);
        $extraRow = $extra->fetch();
        if ($extraRow) {
            $purchase_requests[] = $extraRow;
        }
    }
    foreach ($purchase_requests as $listed) {
        if ((int) $listed['id'] === $prefill_pr && !empty($listed['quote_supplier'])) {
            $prefill_supplier = (string) $listed['quote_supplier'];
            $prefill_contact = (string) ($listed['quote_contact'] ?? '');
            if ($prefill_contact === '' || strcasecmp($prefill_contact, $prefill_supplier) === 0) {
                $phoneStmt = $pdo->prepare("SELECT phone FROM suppliers WHERE name = ? AND phone IS NOT NULL AND phone <> '' LIMIT 1");
                $phoneStmt->execute([$prefill_supplier]);
                $phone = trim((string) $phoneStmt->fetchColumn());
                if ($phone !== '') {
                    $prefill_contact = $phone;
                }
            }
            break;
        }
    }
}
if ($action === 'add' && (int) ($_GET['quote'] ?? 0) > 0) {
    $quote_stmt = $pdo->prepare("SELECT supplier, supplier_contact, purchase_request_id FROM quotations WHERE id = ? AND status = 'accepted'");
    $quote_stmt->execute([(int) $_GET['quote']]);
    $linked_quote = $quote_stmt->fetch();
    if ($linked_quote) {
        $prefill_supplier = $linked_quote['supplier'];
        $prefill_contact = $linked_quote['supplier_contact'] ?? '';
        if ((int) ($linked_quote['purchase_request_id'] ?? 0) > 0) {
            $prefill_pr = (int) $linked_quote['purchase_request_id'];
        }
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('procurement')) {
    $_SESSION['error'] = 'Administrators have view-only access to purchase orders.';
    header('Location: purchase_orders.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-shopping-cart"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list' && canWriteDepartmentData('procurement')): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase Order</a>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Purchase Order</h3>
    <p style="color:#64748b;margin-top:0;">A purchase order can be created only after a supplier is accepted. Materials come from the request, and the supplier is the one accepted in the price comparison.</p>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_order" value="1">
            <input type="hidden" name="po_number" value="<?php echo htmlspecialchars($order_details['po_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_order" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>PO Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($order_details['po_number']) : generatePONumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="po_number" value="<?php echo generatePONumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Purchase Request</label>
                <select name="purchase_request_id" id="poRequest" required>
                    <option value="">Select purchase request</option>
                    <?php if (!$purchase_requests): ?>
                        <option value="" disabled>No request has an accepted supplier yet</option>
                    <?php endif; ?>
                    <?php foreach ($purchase_requests as $pr): ?>
                        <option value="<?php echo $pr['id']; ?>"
                            data-items="<?php echo htmlspecialchars(json_encode($pr_items[(int) $pr['id']] ?? []), ENT_QUOTES); ?>"
                            data-total="<?php echo htmlspecialchars($pr['total_amount'] ?? 0); ?>"
                            data-supplier="<?php echo htmlspecialchars($pr['quote_supplier'] ?? ''); ?>"
                            data-contact="<?php echo htmlspecialchars($pr['quote_contact'] ?? ''); ?>"
                            <?php echo $prefill_pr === (int) $pr['id'] ? 'selected' : ''; ?>>
                            <?php
                            $optionLabel = $pr['pr_number'] . ' - ' . $pr['requestor_name'];
                            if ($pr['quote_total'] !== null && $pr['quote_total'] !== '') {
                                $optionLabel .= ' — ₱' . number_format((float) $pr['quote_total'], 2);
                            }
                            echo htmlspecialchars($optionLabel);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Supplier</label>
                <input type="text" name="supplier" id="supplierName" readonly value="<?php echo htmlspecialchars($prefill_supplier); ?>" placeholder="From the accepted supplier" style="background:#f8fafc;">
            </div>
            <div class="form-group">
                <label>Supplier Contact</label>
                <input type="text" name="supplier_contact" id="supplierContact" readonly value="<?php echo htmlspecialchars($prefill_contact); ?>" style="background:#f8fafc;">
            </div>
        </div>
        <?php if ($prefill_pr > 0 && $prefill_supplier === ''): ?>
            <p style="color:#b45309;">No supplier has been accepted in the price comparison for this request. Accept a supplier there before saving the order.</p>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Order Date</label>
                <input type="date" name="order_date" value="<?php echo $order_details['order_date'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Delivery Date</label>
                <input type="date" name="delivery_date" value="<?php echo $order_details['delivery_date'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="draft" <?php echo ($order_details['status'] ?? '') == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="sent" <?php echo ($order_details['status'] ?? '') == 'sent' ? 'selected' : ''; ?>>Sent</option>
                    <option value="confirmed" <?php echo ($order_details['status'] ?? '') == 'confirmed' ? 'selected' : ''; ?>>Confirmed / Purchased</option>
                    <option value="ready_for_warehouse" <?php echo ($order_details['status'] ?? '') == 'ready_for_warehouse' ? 'selected' : ''; ?>>Ready for Warehouse</option>
                    <option value="delivered" <?php echo ($order_details['status'] ?? '') == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo ($order_details['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Payment Terms</label>
            <input type="text" name="payment_terms" value="<?php echo htmlspecialchars($order_details['payment_terms'] ?? ''); ?>" placeholder="e.g., Net 30, COD">
        </div>

        <h4 style="margin:1rem 0 0.5rem;">Materials from the purchase request</h4>
        <p id="poPriceNote" style="color:#b45309;display:none;">These materials do not have a supplier unit price yet. Enter it in the price comparison, then accept the supplier.</p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Material</th><th>Qty</th><th>Unit</th><th>Supplier unit price</th><th>Amount</th></tr>
                </thead>
                <tbody id="poItemBody">
                    <tr><td colspan="5" style="color:#94a3b8;">Select a purchase request to load the materials.</td></tr>
                </tbody>
                <tfoot>
                    <tr><th colspan="4" style="text-align:right;">Total</th><th id="poItemTotal">₱0.00</th></tr>
                </tfoot>
            </table>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Order</button>
            <a href="purchase_orders.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
function money(value) {
    return '₱' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function renderPoItems() {
    const select = document.getElementById('poRequest');
    const body = document.getElementById('poItemBody');
    const totalCell = document.getElementById('poItemTotal');
    if (!select || !body) return;
    const option = select.selectedOptions[0];
    let items = [];
    try { items = JSON.parse(option?.dataset.items || '[]'); } catch (e) { items = []; }
    body.textContent = '';
    const supplierName = document.getElementById('supplierName');
    const supplierContact = document.getElementById('supplierContact');
    if (supplierName) {
        supplierName.value = option?.dataset.supplier || '';
    }
    if (supplierContact) {
        supplierContact.value = option?.dataset.contact || '';
    }
    if (!option || !option.value || !items.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.style.color = '#94a3b8';
        cell.textContent = option && option.value ? 'This purchase request has no materials.' : 'Select a purchase request to load the materials.';
        row.appendChild(cell);
        body.appendChild(row);
        if (totalCell) totalCell.textContent = money(0);
        return;
    }
    let total = 0;
    let missingPrice = false;
    items.forEach((item) => {
        const hasPrice = item.price !== null && item.price !== '';
        const amount = hasPrice ? Number(item.quantity || 0) * Number(item.price) : 0;
        if (!hasPrice) missingPrice = true;
        total += amount;
        const row = document.createElement('tr');
        [item.name, item.quantity, item.unit, hasPrice ? money(item.price) : '—', hasPrice ? money(amount) : '—'].forEach((text) => {
            const cell = document.createElement('td');
            cell.textContent = text;
            row.appendChild(cell);
        });
        body.appendChild(row);
    });
    if (totalCell) totalCell.textContent = money(total);
    const note = document.getElementById('poPriceNote');
    if (note) note.style.display = missingPrice ? 'block' : 'none';
}
document.getElementById('poRequest')?.addEventListener('change', renderPoItems);
renderPoItems();
</script>

<?php elseif ($action === 'view' && $order_details): ?>
<!-- View Order -->
<div class="card">
    <h3>Purchase Order Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>PO Number:</span> <strong><?php echo htmlspecialchars($order_details['po_number']); ?></strong></div>
        <div class="detail-row"><span>PR Number:</span> <?php echo htmlspecialchars($order_details['pr_number'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($order_details['supplier']); ?></div>
        <div class="detail-row"><span>Supplier Contact:</span> <?php echo htmlspecialchars($order_details['supplier_contact'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $order_details['status']; ?>"><?php echo ucfirst($order_details['status']); ?></span></div>
        <div class="detail-row"><span>Order Date:</span> <?php echo date('M d, Y', strtotime($order_details['order_date'])); ?></div>
        <div class="detail-row"><span>Delivery Date:</span> <?php echo $order_details['delivery_date'] ? date('M d, Y', strtotime($order_details['delivery_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Payment Terms:</span> <?php echo htmlspecialchars($order_details['payment_terms'] ?? 'N/A'); ?></div>
        <?php
        $order_items = [];
        try {
            $itemStmt = $pdo->prepare("SELECT poi.quantity, poi.unit_price, m.name AS material_name
                FROM purchase_order_items poi
                LEFT JOIN materials m ON m.id = poi.material_id
                WHERE poi.purchase_order_id = ?");
            $itemStmt->execute([(int) $order_details['id']]);
            $order_items = $itemStmt->fetchAll();
        } catch (PDOException $e) {
            $order_items = [];
        }
        $order_total = 0.0;
        ?>
        <?php if ($order_items): ?>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>Material</th><th>Qty</th><th>Supplier unit price</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($order_items as $item):
                        $line = (float) $item['quantity'] * (float) $item['unit_price'];
                        $order_total += $line;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['material_name'] ?? 'Material'); ?></td>
                        <td><?php echo (int) $item['quantity']; ?></td>
                        <td>₱<?php echo number_format((float) $item['unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format($line, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="detail-row"><span>Total:</span> <strong>₱<?php echo number_format($order_total, 2); ?></strong></div>
        <?php endif; ?>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($order_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($order_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem;">
        <?php if (canWriteDepartmentData('procurement')): ?>
        <a href="?action=edit&id=<?php echo $order_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <?php endif; ?>
        <?php if (in_array($order_details['status'], ['sent', 'confirmed', 'ready_for_warehouse'], true)): ?>
            <a href="<?php echo APP_URL; ?>modules/warehouse/receive_purchases.php?po=<?php echo (int) $order_details['id']; ?>" class="btn btn-primary"><i class="fas fa-truck-loading"></i> Receive in Warehouse</a>
        <?php endif; ?>
        <?php if ($order_details['status'] === 'delivered'): ?>
            <a href="purchased_materials.php" class="btn btn-primary"><i class="fas fa-boxes"></i> Purchased Materials</a>
        <?php endif; ?>
        <a href="purchase_orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="orderTable">
            <thead>
                <tr>
                    <th data-sort-type="string" onclick="sortTable('orderTable', 0)">PO #</th>
                    <th data-sort-type="string" onclick="sortTable('orderTable', 1)">PR #</th>
                    <th onclick="sortTable('orderTable', 2)">Supplier</th>
                    <th onclick="sortTable('orderTable', 3)">Status</th>
                    <th onclick="sortTable('orderTable', 4)">Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No purchase orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['pr_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['supplier']); ?></td>
                        <td><span class="badge badge-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (canWriteDepartmentData('procurement')): ?>
                            <a href="?action=edit&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <?php if (in_array($order['status'], ['confirmed', 'sent'], true)): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="po_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" name="mark_ready_warehouse" class="btn btn-sm btn-success">Send to Warehouse</button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>