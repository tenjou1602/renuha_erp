<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['procurement']);

$page_title = 'Supplier Quotes';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generateQuotationNumber() {
    return generateNumber('QT', 'quotations', 'quotation_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_quotation']) || isset($_POST['update_quotation'])) && !canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . basename(__FILE__));
        exit();
    }
    if (isset($_POST['add_quotation']) || isset($_POST['update_quotation'])) {
        $quotation_number = $_POST['quotation_number'] ?? generateQuotationNumber();
        $project_id = (int)($_POST['project_id'] ?? 0);
        $purchase_request_id = (int)($_POST['purchase_request_id'] ?? 0);
        $project_id = $project_id > 0 ? $project_id : null;
        $purchase_request_id = $purchase_request_id > 0 ? $purchase_request_id : null;
        $supplier = trim($_POST['supplier'] ?? '');
        $supplier_contact = trim($_POST['supplier_contact'] ?? '');
        $valid_until = $_POST['valid_until'] ?? '';
        $valid_until = $valid_until !== '' ? $valid_until : null;
        $status = $_POST['status'] ?? 'requested';
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        if (empty($supplier)) $errors[] = 'Supplier is required.';
        if (!$purchase_request_id) $errors[] = 'Select the material request.';
        $request_items = [];
        $posted_prices = $_POST['item_price'] ?? [];
        if ($purchase_request_id) {
            $item_stmt = $pdo->prepare("SELECT id, material_id, quantity, unit FROM purchase_request_items WHERE purchase_request_id = ?");
            $item_stmt->execute([$purchase_request_id]);
            $request_items = $item_stmt->fetchAll();
            if (!$request_items) {
                $errors[] = 'This purchase request has no materials.';
            }
            foreach ($request_items as $request_item) {
                $unit_price = round((float) ($posted_prices[$request_item['id']] ?? 0), 2);
                if ($unit_price <= 0) {
                    $errors[] = 'Enter the supplier unit price for every material.';
                    break;
                }
            }
        }
        
        if (empty($errors)) {
            try {
                if ($purchase_request_id && !$project_id) {
                    $pr_stmt = $pdo->prepare("SELECT project_id FROM purchase_requests WHERE id = ?");
                    $pr_stmt->execute([$purchase_request_id]);
                    $linked_project = (int) $pr_stmt->fetchColumn();
                    $project_id = $linked_project > 0 ? $linked_project : null;
                }
                $total_amount = 0.0;
                $priced_items = [];
                foreach ($request_items as $request_item) {
                    $unit_price = round((float) $posted_prices[$request_item['id']], 2);
                    $total_amount += round((float) $request_item['quantity'] * $unit_price, 2);
                    $priced_items[] = [$request_item, $unit_price];
                }
                $total_amount = round($total_amount, 2);
                $pdo->beginTransaction();
                if (isset($_POST['add_quotation'])) {
                    $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, project_id, purchase_request_id, supplier, supplier_contact, valid_until, status, total_amount, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$quotation_number, $project_id, $purchase_request_id, $supplier, $supplier_contact, $valid_until, $status, $total_amount, $notes, $_SESSION['user_id']]);
                    $quoteId = (int) $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("UPDATE quotations SET project_id = ?, purchase_request_id = ?, supplier = ?, supplier_contact = ?, valid_until = ?, status = ?, total_amount = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$project_id, $purchase_request_id, $supplier, $supplier_contact, $valid_until, $status, $total_amount, $notes, $id]);
                    $quoteId = $id;
                    $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$quoteId]);
                }
                $line_stmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, purchase_request_item_id, material_id, quantity, unit, unit_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($priced_items as [$request_item, $unit_price]) {
                    $line_stmt->execute([$quoteId, $request_item['id'], $request_item['material_id'], $request_item['quantity'], $request_item['unit'], $unit_price]);
                }
                $pdo->commit();
                if ($status === 'accepted') {
                    $pdo->prepare("UPDATE quotations SET status = 'rejected' WHERE purchase_request_id = ? AND id <> ? AND status <> 'rejected'")->execute([$purchase_request_id, $quoteId]);
                    $pdo->prepare("UPDATE purchase_requests SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ? AND status IN ('pending', 'approved')")->execute([$_SESSION['user_id'], $purchase_request_id]);
                }
                if (isset($_POST['submit_to_accounting'])) {
                    $pdo->prepare("UPDATE quotations SET submitted_to_accounting_at = NOW() WHERE id = ?")->execute([$quoteId]);
                    notify(
                        'accounting',
                        'supplier_quotation',
                        'Supplier quotation',
                        $quotation_number . ' was submitted by Procurement.',
                        'modules/accounting/supplier_quotations.php?action=view&id=' . $quoteId,
                        $project_id ? (int) $project_id : null,
                        'quotation',
                        $quoteId
                    );
                }
                logActivity($_SESSION['user_id'], isset($_POST['submit_to_accounting']) ? 'Submitted quotation to accounting' : (isset($_POST['add_quotation']) ? 'Created quotation' : 'Updated quotation'), 'Procurement', "QT: $quotation_number");
                if (isset($_POST['submit_to_accounting'])) {
                    $_SESSION['success'] = 'Quotation sent to Accounting.';
                    header('Location: quotations.php');
                } else {
                    $_SESSION['success'] = $status === 'accepted'
                        ? 'Supplier accepted. Next: create the purchase order.'
                        : 'Supplier price saved. Set the status to Accepted when this is the supplier you will buy from.';
                    header('Location: ' . ($status === 'accepted' ? 'purchase_orders.php?action=add&quote=' . $quoteId : 'quotations.php'));
                }
                exit();
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

// Get projects, purchase requests, and suppliers for the form
$projects = $pdo->query("SELECT id, name, project_code FROM projects ORDER BY name")->fetchAll();
$purchase_requests = [];
$suppliers = [];
try {
    $purchase_requests = $pdo->query("SELECT id, pr_number, project_id, purpose, status FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered') ORDER BY created_at DESC")->fetchAll();
    $suppliers = $pdo->query("SELECT id, name, company_name, phone, email, address, tin_number FROM suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $purchase_requests = [];
    $suppliers = [];
}

// Get quotations
$quotations = [];
try {
    $query = "
        SELECT q.*, p.name as project_name, p.project_code, u.full_name as created_by_name,
               (
                   SELECT e.status
                   FROM purchase_orders po
                   INNER JOIN expenses e ON e.purchase_order_id = po.id
                   WHERE po.purchase_request_id = q.purchase_request_id
                     AND po.status <> 'cancelled'
                   ORDER BY e.id DESC
                   LIMIT 1
               ) AS payment_status
        FROM quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN users u ON q.created_by = u.id
        ORDER BY q.created_at DESC
    ";
    $quotations = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single quotation
$quotation_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT q.*, p.name as project_name, p.project_code, pr.pr_number, u.full_name as created_by_name
        FROM quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN purchase_requests pr ON q.purchase_request_id = pr.id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.id = ?
    ");
    $stmt->execute([$id]);
    $quotation_details = $stmt->fetch() ?: null;
}
$selected_pr = (int) ($_GET['pr'] ?? 0);
$selected_project = 0;
if (is_array($quotation_details)) {
    if (!empty($quotation_details['purchase_request_id'])) {
        $selected_pr = (int) $quotation_details['purchase_request_id'];
    }
    $selected_project = (int) ($quotation_details['project_id'] ?? 0);
}
if ($selected_project === 0 && $selected_pr > 0) {
    foreach ($purchase_requests as $linked_pr) {
        if ((int) $linked_pr['id'] === $selected_pr) {
            $selected_project = (int) $linked_pr['project_id'];
            break;
        }
    }
}
$quote_materials = [];
$saved_prices = [];
try {
    $material_rows = $pdo->query("SELECT pri.id, pri.purchase_request_id, pri.quantity, pri.unit, pri.estimated_cost, m.name AS material_name
        FROM purchase_request_items pri
        LEFT JOIN materials m ON m.id = pri.material_id
        ORDER BY pri.id")->fetchAll();
    foreach ($material_rows as $material_row) {
        $quote_materials[(int) $material_row['purchase_request_id']][] = [
            'id' => (int) $material_row['id'],
            'name' => $material_row['material_name'] ?: 'Material',
            'quantity' => (int) $material_row['quantity'],
            'unit' => $material_row['unit'],
            'estimate' => (float) $material_row['estimated_cost'],
        ];
    }
    if ($id > 0) {
        $saved_stmt = $pdo->prepare("SELECT purchase_request_item_id, unit_price FROM quotation_items WHERE quotation_id = ?");
        $saved_stmt->execute([$id]);
        foreach ($saved_stmt->fetchAll() as $saved_row) {
            $saved_prices[(int) $saved_row['purchase_request_item_id']] = (float) $saved_row['unit_price'];
        }
    }
} catch (PDOException $e) {
    $quote_materials = [];
    $saved_prices = [];
}


if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('procurement')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: ' . basename(__FILE__) . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-signature"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <?php if (canWriteDepartmentData('procurement')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a><?php endif; ?>
    <?php endif; ?>
</div>

<p style="color:#64748b;margin-top:0;">Enter the supplier unit price for every material. Only one supplier can be Accepted. That price is what the purchase order uses.</p>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3>Update Quotation</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_quotation" value="1">
            <input type="hidden" name="quotation_number" value="<?php echo htmlspecialchars($quotation_details['quotation_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_quotation" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Quotation Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($quotation_details['quotation_number']) : generateQuotationNumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="quotation_number" value="<?php echo generateQuotationNumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="required">Purchase Request</label>
                <select name="purchase_request_id" id="purchaseRequest" required>
                    <option value="">No purchase request</option>
                    <?php foreach ($purchase_requests as $pr): ?>
                        <option value="<?php echo (int) $pr['id']; ?>" data-project="<?php echo (int) $pr['project_id']; ?>" <?php echo $selected_pr === (int) $pr['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pr['pr_number'] . ' — ' . $pr['status']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Project</label>
                <select name="project_id" id="projectSelect">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo $selected_project === (int) $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Supplier</label>
                <select name="supplier" id="supplierSelect" required>
                    <option value="">Select supplier</option>
                    <?php
                    $current_supplier = $quotation_details['supplier'] ?? '';
                    $supplier_known = false;
                    foreach ($suppliers as $supplier_row):
                        $is_current = strcasecmp($current_supplier, $supplier_row['name']) === 0;
                        $supplier_known = $supplier_known || $is_current;
                    ?>
                        <option value="<?php echo htmlspecialchars($supplier_row['name']); ?>" data-company="<?php echo htmlspecialchars($supplier_row['company_name'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($supplier_row['phone'] ?? ''); ?>" data-email="<?php echo htmlspecialchars($supplier_row['email'] ?? ''); ?>" data-address="<?php echo htmlspecialchars($supplier_row['address'] ?? ''); ?>" data-tin="<?php echo htmlspecialchars($supplier_row['tin_number'] ?? ''); ?>" <?php echo $is_current ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(($supplier_row['company_name'] ?? '') !== '' ? $supplier_row['company_name'] : ('Company name not set — ' . $supplier_row['name'])); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_supplier !== '' && !$supplier_known): ?>
                        <option value="<?php echo htmlspecialchars($current_supplier); ?>" selected><?php echo htmlspecialchars($current_supplier); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Company Name</label>
                <input type="text" name="supplier_contact" id="supplierCompany" readonly value="<?php echo htmlspecialchars($quotation_details['supplier_contact'] ?? ''); ?>" placeholder="Company name not set">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Phone</label><input type="text" id="supplierPhone" readonly></div>
            <div class="form-group"><label>Email</label><input type="text" id="supplierEmail" readonly></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Address</label><input type="text" id="supplierAddress" readonly></div>
            <div class="form-group"><label>TIN</label><input type="text" id="supplierTin" readonly></div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Valid Until</label>
                <input type="date" name="valid_until" value="<?php echo $quotation_details['valid_until'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label>Supplier total</label>
                <input type="text" id="supplierTotal" readonly value="₱0.00" style="background:#f8fafc;">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="requested" <?php echo ($quotation_details['status'] ?? '') == 'requested' ? 'selected' : ''; ?>>Requested</option>
                    <option value="received" <?php echo ($quotation_details['status'] ?? '') == 'received' ? 'selected' : ''; ?>>Received</option>
                    <option value="accepted" <?php echo ($quotation_details['status'] ?? '') == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                    <option value="rejected" <?php echo ($quotation_details['status'] ?? '') == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
        </div>
        
        <h4 style="margin:1rem 0 0.5rem;">Supplier unit price per material</h4>
        <?php if ($action === 'edit' && !$saved_prices && (float) ($quotation_details['total_amount'] ?? 0) > 0): ?>
            <p style="color:#b45309;">The previous total was ₱<?php echo number_format((float) $quotation_details['total_amount'], 2); ?>. Enter the supplier unit price for every material. The purchase order uses those prices.</p>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Material</th><th>Qty</th><th>Unit</th><th>Request estimate</th><th>Supplier unit price</th></tr>
                </thead>
                <tbody id="quoteItemBody">
                    <tr><td colspan="5" style="color:#94a3b8;">Select a purchase request to enter supplier prices.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"><?php echo htmlspecialchars($quotation_details['notes'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Quotation</button>
            <button type="submit" name="submit_to_accounting" value="1" class="btn btn-success" onclick="return confirm('Send this quotation to Accounting?');"><i class="fas fa-paper-plane"></i> Submit to Accounting</button>
            <a href="quotations.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
document.getElementById('purchaseRequest')?.addEventListener('change', function () {
    const projectId = this.selectedOptions[0]?.dataset.project || '';
    const projectSelect = document.getElementById('projectSelect');
    if (projectSelect && projectId) {
        projectSelect.value = projectId;
    }
});
document.getElementById('supplierSelect')?.addEventListener('change', function () {
    const selected = this.selectedOptions[0];
    const company = document.getElementById('supplierCompany');
    if (company) company.value = selected?.dataset.company || '';
    const map = { supplierPhone: 'phone', supplierEmail: 'email', supplierAddress: 'address', supplierTin: 'tin' };
    Object.keys(map).forEach(function (id) {
        const input = document.getElementById(id);
        if (input) input.value = selected?.dataset[map[id]] || '';
    });
});
const quoteMaterials = <?php echo json_encode($quote_materials, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const savedPrices = <?php echo json_encode($saved_prices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
function money(value) {
    return '₱' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function renderQuoteItems() {
    const select = document.getElementById('purchaseRequest');
    const body = document.getElementById('quoteItemBody');
    const totalInput = document.getElementById('supplierTotal');
    if (!select || !body) return;
    const items = quoteMaterials[select.value] || [];
    body.textContent = '';
    if (!items.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.style.color = '#94a3b8';
        cell.textContent = select.value ? 'This purchase request has no materials.' : 'Select a purchase request to enter supplier prices.';
        row.appendChild(cell);
        body.appendChild(row);
        if (totalInput) totalInput.value = money(0);
        return;
    }
    items.forEach((item) => {
        const row = document.createElement('tr');
        [item.name, item.quantity, item.unit, money(item.estimate)].forEach((text) => {
            const cell = document.createElement('td');
            cell.textContent = text;
            row.appendChild(cell);
        });
        const priceCell = document.createElement('td');
        const input = document.createElement('input');
        input.type = 'number';
        input.name = 'item_price[' + item.id + ']';
        input.min = '0.01';
        input.step = '0.01';
        input.required = true;
        input.value = savedPrices[item.id] ?? '';
        input.addEventListener('input', updateQuoteTotal);
        priceCell.appendChild(input);
        row.appendChild(priceCell);
        body.appendChild(row);
    });
    updateQuoteTotal();
}
function updateQuoteTotal() {
    const totalInput = document.getElementById('supplierTotal');
    let total = 0;
    document.querySelectorAll('#quoteItemBody input').forEach((input) => {
        const qty = Number(input.closest('tr')?.children[1]?.textContent || 0);
        total += qty * Number(input.value || 0);
    });
    if (totalInput) totalInput.value = money(total);
}
document.getElementById('purchaseRequest')?.addEventListener('change', renderQuoteItems);
renderQuoteItems();
</script>

<?php elseif ($action === 'view' && $quotation_details): ?>
<!-- View Quotation -->
<div class="card">
    <h3>Quotation Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Quotation #:</span> <strong><?php echo htmlspecialchars($quotation_details['quotation_number']); ?></strong></div>
        <div class="detail-row"><span>Purchase Request:</span> <?php echo htmlspecialchars($quotation_details['pr_number'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($quotation_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($quotation_details['supplier']); ?></div>
        <div class="detail-row"><span>Company Name:</span> <?php echo htmlspecialchars($quotation_details['supplier_contact'] ?? '') !== '' ? htmlspecialchars($quotation_details['supplier_contact']) : 'Company name not set'; ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $quotation_details['status']; ?>"><?php echo ucfirst($quotation_details['status']); ?></span>
            <?php
            $detailPayment = '';
            if (!empty($quotation_details['submitted_to_accounting_at']) && !empty($quotation_details['purchase_request_id'])) {
                $payStmt = $pdo->prepare("SELECT e.status FROM purchase_orders po INNER JOIN expenses e ON e.purchase_order_id = po.id WHERE po.purchase_request_id = ? AND po.status <> 'cancelled' ORDER BY e.id DESC LIMIT 1");
                $payStmt->execute([(int) $quotation_details['purchase_request_id']]);
                $detailPayment = (string) $payStmt->fetchColumn();
                echo $detailPayment === 'paid' ? ' <span class="badge badge-success">Paid</span>' : ' <span class="badge badge-warning">Pending</span>';
            }
            ?>
        </div>
        <div class="detail-row"><span>Valid Until:</span> <?php echo $quotation_details['valid_until'] ? date('M d, Y', strtotime($quotation_details['valid_until'])) : 'N/A'; ?></div>
        <?php
        $quote_lines = [];
        try {
            $line_stmt = $pdo->prepare("SELECT qi.quantity, qi.unit, qi.unit_price, m.name AS material_name
                FROM quotation_items qi
                LEFT JOIN materials m ON m.id = qi.material_id
                WHERE qi.quotation_id = ?
                ORDER BY qi.id");
            $line_stmt->execute([(int) $quotation_details['id']]);
            $quote_lines = $line_stmt->fetchAll();
        } catch (PDOException $e) {
            $quote_lines = [];
        }
        ?>
        <?php if ($quote_lines): ?>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>Material</th><th>Qty</th><th>Unit</th><th>Supplier unit price</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($quote_lines as $line): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($line['material_name'] ?? 'Material'); ?></td>
                        <td><?php echo (int) $line['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($line['unit']); ?></td>
                        <td>₱<?php echo number_format((float) $line['unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $line['quantity'] * (float) $line['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="detail-row"><span>Supplier total:</span> <strong>₱<?php echo number_format((float) ($quotation_details['total_amount'] ?? 0), 2); ?></strong></div>
        <div class="detail-row"><span>Notes:</span> <?php echo nl2br(htmlspecialchars($quotation_details['notes'] ?? '')); ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($quotation_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($quotation_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem;">
        <?php if (canWriteDepartmentData('procurement')): ?><a href="?action=edit&id=<?php echo $quotation_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (($quotation_details['status'] ?? '') === 'accepted' && canWriteDepartmentData('procurement')): ?>
            <a href="purchase_orders.php?action=add&quote=<?php echo (int) $quotation_details['id']; ?>" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Create Purchase Order</a>
        <?php endif; ?>
        <a href="quotations.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="quotationTable">
            <thead>
                <tr>
                    <th onclick="sortTable('quotationTable', 0)">QT #</th>
                    <th onclick="sortTable('quotationTable', 1)">Project</th>
                    <th onclick="sortTable('quotationTable', 2)">Supplier</th>
                    <th>Price</th>
                    <th onclick="sortTable('quotationTable', 3)">Status</th>
                    <th onclick="sortTable('quotationTable', 4)">Valid Until</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">No quotations found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotations as $qt): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($qt['quotation_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($qt['project_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($qt['supplier']); ?></td>
                        <td>₱<?php echo number_format((float) ($qt['total_amount'] ?? 0), 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $qt['status']; ?>"><?php echo ucfirst($qt['status']); ?></span>
                            <?php if (!empty($qt['submitted_to_accounting_at'])): ?>
                                <?php if (($qt['payment_status'] ?? '') === 'paid'): ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $qt['valid_until'] ? date('M d, Y', strtotime($qt['valid_until'])) : 'N/A'; ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $qt['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (canWriteDepartmentData('procurement')): ?><a href="?action=edit&id=<?php echo $qt['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
                            <?php if (($qt['status'] ?? '') === 'accepted' && canWriteDepartmentData('procurement')): ?>
                                <a href="purchase_orders.php?action=add&quote=<?php echo (int) $qt['id']; ?>" class="btn btn-sm btn-primary">Create PO</a>
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