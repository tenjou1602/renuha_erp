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

function supplierQuoteMaterialRows(PDO $pdo, int $purchaseRequestId, int $projectId): array
{
    $itemStmt = $pdo->prepare("SELECT pri.id, pri.material_id, pri.quantity, pri.unit, pri.estimated_cost, pri.remarks, m.name AS material_name
        FROM purchase_request_items pri
        LEFT JOIN materials m ON m.id = pri.material_id
        WHERE pri.purchase_request_id = ?
        ORDER BY pri.id");
    $itemStmt->execute([$purchaseRequestId]);
    $existing = $itemStmt->fetchAll();
    $byName = [];
    foreach ($existing as $row) {
        $label = trim((string) (($row['remarks'] ?? '') !== '' ? $row['remarks'] : ($row['material_name'] ?? '')));
        $byName[strtolower($label)][] = $row;
    }
    $projectLines = [];
    if ($projectId > 0) {
        $projectStmt = $pdo->prepare("SELECT id, item_name, quantity, unit, unit_price FROM project_quotation_items WHERE project_id = ? ORDER BY id");
        $projectStmt->execute([$projectId]);
        $projectLines = $projectStmt->fetchAll();
    }
    if (!$projectLines) {
        $rows = [];
        foreach ($existing as $row) {
            $qty = (float) $row['quantity'];
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => trim((string) (($row['remarks'] ?? '') !== '' ? $row['remarks'] : ($row['material_name'] ?: 'Material'))),
                'quantity' => floor($qty) == $qty ? (int) $qty : $qty,
                'unit' => $row['unit'] ?: 'pcs',
                'estimate' => (float) $row['estimated_cost'],
                'pri_id' => (int) $row['id'],
                'material_id' => (int) $row['material_id'],
                'project_item_id' => 0,
            ];
        }
        return $rows;
    }
    $rows = [];
    $used = [];
    foreach ($projectLines as $line) {
        $name = trim((string) $line['item_name']);
        if ($name === '') {
            continue;
        }
        $match = null;
        foreach ($byName[strtolower($name)] ?? [] as $candidate) {
            if (!isset($used[(int) $candidate['id']])) {
                $match = $candidate;
                $used[(int) $candidate['id']] = true;
                break;
            }
        }
        $qty = (float) $line['quantity'];
        $rows[] = [
            'id' => $match ? (int) $match['id'] : ('pq' . (int) $line['id']),
            'name' => $name,
            'quantity' => floor($qty) == $qty ? (int) $qty : $qty,
            'unit' => trim((string) ($line['unit'] ?? '')) ?: 'lot',
            'estimate' => (float) $line['unit_price'],
            'pri_id' => $match ? (int) $match['id'] : 0,
            'material_id' => $match ? (int) $match['material_id'] : 0,
            'project_item_id' => (int) $line['id'],
        ];
    }
    return $rows;
}

function supplierQuoteMaterialId(PDO $pdo, string $name, string $unit, int $projectItemId, float $cost): int
{
    $find = $pdo->prepare("SELECT id FROM materials WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $find->execute([$name]);
    $existing = $find->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }
    $code = $projectItemId > 0 ? ('BOQ-' . $projectItemId) : ('BOQ-' . strtoupper(bin2hex(random_bytes(3))));
    $byCode = $pdo->prepare("SELECT id FROM materials WHERE material_code = ? LIMIT 1");
    $byCode->execute([$code]);
    $coded = $byCode->fetchColumn();
    if ($coded) {
        return (int) $coded;
    }
    $insert = $pdo->prepare("INSERT INTO materials (material_code, name, unit, cost_per_unit, status) VALUES (?, ?, ?, ?, 'active')");
    $insert->execute([$code, mb_substr($name, 0, 200), $unit !== '' ? mb_substr($unit, 0, 30) : 'pcs', $cost]);
    return (int) $pdo->lastInsertId();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quotation']) && $id > 0) {
        $paidGuard = $pdo->prepare("
            SELECT e.status
            FROM quotations q
            INNER JOIN purchase_orders po ON po.purchase_request_id = q.purchase_request_id AND po.status <> 'cancelled'
            INNER JOIN expenses e ON e.purchase_order_id = po.id
            WHERE q.id = ? AND q.submitted_to_accounting_at IS NOT NULL
            ORDER BY e.id DESC
            LIMIT 1
        ");
        $paidGuard->execute([$id]);
        if ((string) $paidGuard->fetchColumn() === 'paid') {
            $_SESSION['error'] = 'This quotation is paid. It can only be viewed.';
            header('Location: ' . basename(__FILE__) . '?action=view&id=' . $id);
            exit();
        }
    }
    if ((isset($_POST['add_quotation']) || isset($_POST['update_quotation'])) && !canWriteDepartmentData('procurement')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . basename(__FILE__));
        exit();
    }
    if (isset($_POST['add_quotation']) || isset($_POST['update_quotation'])) {
        $postedRequest = (int) ($_POST['purchase_request_id'] ?? 0);
        header('Location: buying.php' . ($postedRequest > 0 ? '?request=' . $postedRequest : ''));
        exit();
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
        $supplier_rows = [];
        $posted_prices = $_POST['item_price'] ?? [];
        $locked_prices = [];
        if (isset($_POST['update_quotation']) && $id > 0) {
            $locked_stmt = $pdo->prepare("SELECT purchase_request_item_id, unit_price FROM quotation_items WHERE quotation_id = ?");
            $locked_stmt->execute([$id]);
            foreach ($locked_stmt->fetchAll() as $locked_row) {
                if ((float) $locked_row['unit_price'] > 0) {
                    $locked_prices[(int) $locked_row['purchase_request_item_id']] = $locked_row['unit_price'];
                }
            }
        }
        if ($purchase_request_id) {
            if (!$project_id) {
                $pr_lookup = $pdo->prepare("SELECT project_id FROM purchase_requests WHERE id = ?");
                $pr_lookup->execute([$purchase_request_id]);
                $project_id = (int) $pr_lookup->fetchColumn() ?: null;
            }
            $supplier_rows = supplierQuoteMaterialRows($pdo, $purchase_request_id, (int) $project_id);
            if (!$supplier_rows) {
                $errors[] = 'This purchase request has no materials.';
            }
            foreach ($supplier_rows as $supplier_row) {
                $item_key = (string) $supplier_row['id'];
                $pri_id = (int) $supplier_row['pri_id'];
                $unit_price = ($pri_id > 0 && array_key_exists($pri_id, $locked_prices))
                    ? (float) $locked_prices[$pri_id]
                    : round((float) ($posted_prices[$item_key] ?? 0), 2);
                $price_locked = $pri_id > 0 && array_key_exists($pri_id, $locked_prices);
                $estimate = round((float) $supplier_row['estimate'], 2);
                if (!$price_locked && $unit_price < 0) {
                    $errors[] = $supplier_row['name'] . ': Enter a supplier unit price.';
                    break;
                }
                if (!$price_locked && $unit_price > $estimate) {
                    $errors[] = $supplier_row['name'] . ' exceeds the allocated budget. Supplier unit price cannot be more than ₱' . number_format($estimate, 2) . '.';
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
                $pdo->beginTransaction();
                $total_amount = 0.0;
                $priced_items = [];
                $insertRequestItem = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, material_id, quantity, unit, estimated_cost, remarks) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($supplier_rows as $supplier_row) {
                    $item_key = (string) $supplier_row['id'];
                    $pri_id = (int) $supplier_row['pri_id'];
                    $unit_price = ($pri_id > 0 && array_key_exists($pri_id, $locked_prices))
                        ? (float) $locked_prices[$pri_id]
                        : round((float) ($posted_prices[$item_key] ?? 0), 2);
                    $qty = max(1, (int) round((float) $supplier_row['quantity']));
                    $material_id = (int) $supplier_row['material_id'];
                    if ($pri_id <= 0) {
                        $material_id = supplierQuoteMaterialId($pdo, $supplier_row['name'], (string) $supplier_row['unit'], (int) $supplier_row['project_item_id'], (float) $supplier_row['estimate']);
                        $insertRequestItem->execute([$purchase_request_id, $material_id, $qty, $supplier_row['unit'], $supplier_row['estimate'], $supplier_row['name']]);
                        $pri_id = (int) $pdo->lastInsertId();
                    }
                    $total_amount += round($qty * $unit_price, 2);
                    $priced_items[] = [['id' => $pri_id, 'material_id' => $material_id, 'quantity' => $qty, 'unit' => $supplier_row['unit']], $unit_price];
                }
                $total_amount = round($total_amount, 2);
                if (isset($_POST['add_quotation'])) {
                    $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, project_id, purchase_request_id, supplier, supplier_contact, valid_until, status, total_amount, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$quotation_number, $project_id, $purchase_request_id, $supplier, $supplier_contact, $valid_until, $status, $total_amount, $notes, $_SESSION['user_id']]);
                    $quoteId = (int) $pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare("UPDATE quotations SET project_id = ?, purchase_request_id = ?, supplier = ?, supplier_contact = ?, status = ?, total_amount = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$project_id, $purchase_request_id, $supplier, $supplier_contact, $status, $total_amount, $notes, $id]);
                    $quoteId = $id;
                    $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$quoteId]);
                }
                $line_stmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, purchase_request_item_id, material_id, quantity, unit, unit_price) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($priced_items as [$request_item, $unit_price]) {
                    $line_stmt->execute([$quoteId, $request_item['id'], $request_item['material_id'], $request_item['quantity'], $request_item['unit'], $unit_price]);
                }
                $draftInvoiceId = null;
                if (isset($_POST['submit_to_accounting'])) {
                    $pdo->prepare("UPDATE quotations SET submitted_to_accounting_at = COALESCE(submitted_to_accounting_at, NOW()) WHERE id = ?")->execute([$quoteId]);
                }
                if ($status === 'received' || isset($_POST['submit_to_accounting'])) {
                    assertMonthOpen('accounting', date('Y-m-d'));
                    $draftInvoiceId = createDraftInvoiceForReceivedQuotation($pdo, $quoteId, (int) $_SESSION['user_id']);
                }
                $pdo->commit();
                if ($status === 'accepted') {
                    $pdo->prepare("UPDATE quotations SET status = 'rejected' WHERE purchase_request_id = ? AND id <> ? AND status <> 'rejected'")->execute([$purchase_request_id, $quoteId]);
                    $pdo->prepare("UPDATE purchase_requests SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ? AND status IN ('pending', 'approved')")->execute([$_SESSION['user_id'], $purchase_request_id]);
                }
                if (isset($_POST['submit_to_accounting'])) {
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
                writeAudit(isset($_POST['submit_to_accounting']) ? 'Submit to Accounting' : 'Update quotation', 'Procurement', 'quotations', (int) $quoteId, null, ['quotation_number' => $quotation_number, 'status' => $status, 'total' => $total_amount ?? null], null);
                if (isset($_POST['submit_to_accounting'])) {
                    $_SESSION['success'] = $draftInvoiceId
                        ? 'Quotation sent to Accounting. A draft invoice was added.'
                        : 'Quotation sent to Accounting.';
                    header('Location: quotations.php');
                } elseif ($status === 'received') {
                    $_SESSION['success'] = $draftInvoiceId
                        ? 'Quotation marked received. A draft invoice was added in Accounting.'
                        : 'Quotation marked received.';
                    header('Location: quotations.php');
                } else {
                    $_SESSION['success'] = $status === 'accepted'
                        ? 'Supplier accepted. Next: create the purchase order.'
                        : 'Supplier price saved. Set the status to Accepted when this is the supplier you will buy from.';
                    header('Location: ' . ($status === 'accepted' ? 'purchase_orders.php?action=add&quote=' . $quoteId : 'quotations.php'));
                }
                exit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException ? $e->getMessage() : userDatabaseError($e);
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
        SELECT q.*, p.name as project_name, p.project_code, pr.pr_number, u.full_name as created_by_name,
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
$listed_pr = false;
foreach ($purchase_requests as $linked_pr) {
    if ((int) $linked_pr['id'] === $selected_pr) {
        $listed_pr = true;
        if ($selected_project === 0) {
            $selected_project = (int) $linked_pr['project_id'];
        }
        break;
    }
}
if ($selected_pr > 0 && !$listed_pr) {
    $linked_stmt = $pdo->prepare("SELECT id, pr_number, project_id, purpose, status FROM purchase_requests WHERE id = ?");
    $linked_stmt->execute([$selected_pr]);
    $linked_pr = $linked_stmt->fetch();
    if ($linked_pr) {
        $purchase_requests[] = $linked_pr;
        if ($selected_project === 0) {
            $selected_project = (int) $linked_pr['project_id'];
        }
    }
}
$quote_materials = [];
$saved_prices = [];
try {
    foreach ($purchase_requests as $request_row) {
        $requestId = (int) $request_row['id'];
        $quote_materials[$requestId] = supplierQuoteMaterialRows($pdo, $requestId, (int) ($request_row['project_id'] ?? 0));
    }
    if ($id > 0) {
        $saved_stmt = $pdo->prepare("SELECT purchase_request_item_id, unit_price FROM quotation_items WHERE quotation_id = ?");
        $saved_stmt->execute([$id]);
        foreach ($saved_stmt->fetchAll() as $saved_row) {
            $saved_prices[(int) $saved_row['purchase_request_item_id']] = (string) $saved_row['unit_price'];
        }
    }
} catch (PDOException $e) {
    $quote_materials = [];
    $saved_prices = [];
}


$quoteIsPaid = is_array($quotation_details)
    && !empty($quotation_details['submitted_to_accounting_at'])
    && ($quotation_details['payment_status'] ?? '') === 'paid';
if ($action === 'edit' && $quoteIsPaid) {
    $_SESSION['error'] = 'This quotation is paid. It can only be viewed.';
    header('Location: ' . basename(__FILE__) . '?action=view&id=' . $id);
    exit();
}
if ($action === 'add' || $action === 'edit') {
    $requestOnQuote = (int) ($_GET['pr'] ?? 0);
    header('Location: buying.php' . ($requestOnQuote > 0 ? '?request=' . $requestOnQuote : ''));
    exit();
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
    <form method="POST" id="supplierQuoteForm">
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
                        <option value="<?php echo htmlspecialchars($supplier_row['name']); ?>" data-company="<?php echo htmlspecialchars($supplier_row['company_name'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($supplier_row['phone'] ?? ''); ?>" data-email="<?php echo htmlspecialchars($supplier_row['email'] ?? ''); ?>" data-address="<?php echo htmlspecialchars($supplier_row['address'] ?? ''); ?>" <?php echo $is_current ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supplier_row['name']); ?>
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
        </div>
        
        <div class="form-row">
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
function fillSupplierDetails() {
    const select = document.getElementById('supplierSelect');
    const selected = select?.selectedOptions[0];
    const company = document.getElementById('supplierCompany');
    if (company) company.value = selected?.dataset.company || '';
    const map = { supplierPhone: 'phone', supplierEmail: 'email', supplierAddress: 'address' };
    Object.keys(map).forEach(function (id) {
        const input = document.getElementById(id);
        if (input) input.value = selected?.dataset[map[id]] || '';
    });
}
document.getElementById('supplierSelect')?.addEventListener('change', fillSupplierDetails);
fillSupplierDetails();
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
        const saved = savedPrices[item.id];
        const hasSaved = saved !== undefined && saved !== null && saved !== '' && Number(saved) > 0;
        const estimate = Number(item.estimate || 0);
        input.type = 'text';
        input.inputMode = 'decimal';
        input.autocomplete = 'off';
        input.name = 'item_price[' + item.id + ']';
        input.required = true;
        input.pattern = '[0-9]+(\\.[0-9]+)?';
        input.value = hasSaved ? String(saved) : '';
        input.placeholder = '';
        input.dataset.maxPrice = String(estimate);
        input.dataset.lastValid = hasSaved ? String(saved) : '';
        if (hasSaved) {
            input.readOnly = true;
            input.style.background = '#f8fafc';
            input.title = 'This supplier price is locked.';
        }
        const error = document.createElement('div');
        error.style.color = '#e74a3b';
        error.style.fontSize = '0.75rem';
        error.style.marginTop = '0.25rem';
        input.addEventListener('input', function () {
            guardSupplierPrice(input);
            updateQuoteTotal();
            markSupplierPrice(input);
        });
        priceCell.appendChild(input);
        priceCell.appendChild(error);
        row.appendChild(priceCell);
        body.appendChild(row);
    });
    updateQuoteTotal();
}
function guardSupplierPrice(input) {
    if (!input || input.readOnly) return;
    const raw = input.value.trim();
    const maximum = Number(input.dataset.maxPrice);
    input.dataset.blocked = '';
    if (raw === '' || raw === '.' || raw.endsWith('.')) return;
    const amount = Number(raw);
    if (!Number.isFinite(amount)) return;
    if (amount > maximum) {
        input.value = input.dataset.lastValid || '';
        input.dataset.blocked = 'budget';
        return;
    }
    if (amount >= 0) input.dataset.lastValid = input.value;
}
function supplierPriceMessage(input) {
    if (!input || input.readOnly) return '';
    const maximum = Number(input.dataset.maxPrice);
    if (input.dataset.blocked === 'budget') return 'Cannot exceed the allocated budget (' + money(maximum) + ').';
    if (input.value.trim() === '') return '';
    const amount = Number(input.value);
    if (!Number.isFinite(amount) || amount < 0) return 'Enter a supplier unit price.';
    if (amount > maximum) return 'Cannot exceed the allocated budget (' + money(maximum) + ').';
    return '';
}
function markSupplierPrice(input) {
    if (!input || input.readOnly) return;
    const message = supplierPriceMessage(input);
    input.style.borderColor = message ? '#e74a3b' : '';
    input.setCustomValidity(message);
    const error = input.nextElementSibling;
    if (error) error.textContent = message;
}
function updateQuoteTotal() {
    const totalInput = document.getElementById('supplierTotal');
    let total = 0;
    document.querySelectorAll('#quoteItemBody input').forEach((input) => {
        const amount = Number(input.value || 0);
        if (supplierPriceMessage(input)) return;
        const qty = Number(input.closest('tr')?.children[1]?.textContent || 0);
        total += qty * amount;
    });
    if (totalInput) totalInput.value = money(total);
}
document.getElementById('purchaseRequest')?.addEventListener('change', renderQuoteItems);
document.getElementById('supplierQuoteForm')?.addEventListener('submit', function (event) {
    const invalid = Array.from(document.querySelectorAll('#quoteItemBody input[data-max-price]')).find(function (input) {
        if (input.readOnly) return false;
        const amount = Number(input.value);
        const maximum = Number(input.dataset.maxPrice);
        return input.value.trim() === '' || !Number.isFinite(amount) || amount < 0 || amount > maximum;
    });
    if (!invalid) return;
    event.preventDefault();
    markSupplierPrice(invalid);
    invalid.focus();
    alert(supplierPriceMessage(invalid) || 'Enter a supplier unit price that does not exceed the allocated budget.');
});
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
        <?php if (!$quoteIsPaid && canWriteDepartmentData('procurement')): ?><a href="?action=edit&id=<?php echo $quotation_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (!$quoteIsPaid && ($quotation_details['status'] ?? '') === 'accepted' && canWriteDepartmentData('procurement')): ?>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No quotations found.</td>
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
                        <td>
                            <?php $rowIsPaid = !empty($qt['submitted_to_accounting_at']) && ($qt['payment_status'] ?? '') === 'paid'; ?>
                            <a href="?action=view&id=<?php echo $qt['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if (!$rowIsPaid && canWriteDepartmentData('procurement')): ?><a href="?action=edit&id=<?php echo $qt['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
                            <?php if (!$rowIsPaid && ($qt['status'] ?? '') === 'accepted' && canWriteDepartmentData('procurement')): ?>
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