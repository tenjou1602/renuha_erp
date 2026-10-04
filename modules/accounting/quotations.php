<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';
require_once '../../includes/accounting_ledger.php';
require_once '../../includes/quotation_file.php';

requireDepartment(['accounting']);

$page_title = 'Client Quotations';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
$error = $error ?? null;

function quotationLinesFromPost($items) {
    $lines = [];
    foreach ($items as $item) {
        $itemName = trim($item['item_name'] ?? '');
        $description = trim($item['description'] ?? '');
        $qty = round((float) ($item['quantity'] ?? 0), 2);
        $price = round((float) ($item['unit_price'] ?? 0), 2);
        if ($itemName === '' && $description === '' && $qty <= 0 && $price <= 0) {
            continue;
        }
        if ($itemName === '') {
            $itemName = $description;
        }
        if ($itemName === '') {
            throw new RuntimeException('Each quotation line needs an item name.');
        }
        if ($qty <= 0) {
            throw new RuntimeException('Quantity must be greater than 0.');
        }
        if ($price < 0) {
            throw new RuntimeException('Unit price cannot be negative.');
        }
        $type = $item['line_type'] ?? 'other';
        if (!in_array($type, ['material', 'labor', 'other'], true)) {
            $type = 'other';
        }
        $materialId = (int) ($item['material_id'] ?? 0);
        $lines[] = [
            'line_type' => $type,
            'item_name' => $itemName,
            'material_id' => $materialId > 0 ? $materialId : null,
            'description' => $description,
            'quantity' => $qty,
            'unit' => trim($item['unit'] ?? '') ?: 'pcs',
            'unit_price' => $price,
            'line_total' => round($qty * $price, 2),
        ];
    }
    if (empty($lines)) {
        throw new RuntimeException('Add at least one quotation line.');
    }
    return $lines;
}

function quotationLinesFromExtract(array $items) {
    $posted = [];
    foreach ($items as $item) {
        $posted[] = [
            'item_name' => $item['item_name'] ?? ($item['description'] ?? ''),
            'description' => isset($item['item_name']) ? ($item['description'] ?? '') : '',
            'quantity' => $item['quantity'] ?? 0,
            'unit' => $item['unit'] ?? '',
            'unit_price' => $item['unit_price'] ?? 0,
            'line_type' => $item['line_type'] ?? 'other',
            'material_id' => $item['material_id'] ?? 0,
        ];
    }
    return quotationLinesFromPost($posted);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $writeActions = isset($_POST['add_quotation']) || isset($_POST['update_quotation']) || isset($_POST['set_status']) || isset($_POST['convert_quotation']);
    if ($writeActions && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: quotations.php');
        exit();
    }

    if (isset($_POST['extract_quotation_file'])) {
        header('Content-Type: application/json');
        try {
            if (!canWriteDepartmentData('accounting')) {
                throw new RuntimeException('You cannot import a quotation file.');
            }
            echo json_encode(extractQuotationUpload($_FILES['quotation_file'] ?? []));
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }

    if (isset($_POST['load_project_quotation'])) {
        header('Content-Type: application/json');
        try {
            if (!canWriteDepartmentData('accounting')) {
                throw new RuntimeException('You cannot open this quotation file.');
            }
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT quotation_path, quotation_name FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $projectFile = $stmt->fetch();
            if (!$projectFile || empty($projectFile['quotation_path'])) {
                throw new RuntimeException('This project has no quotation file from Executive Admin.');
            }
            $data = extractQuotationPath($projectFile['quotation_path'], (string) $projectFile['quotation_name']);
            $data['file_url'] = APP_URL . $projectFile['quotation_path'];
            $data['file_name'] = $projectFile['quotation_name'] ?: 'Quotation file';
            echo json_encode($data);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }

    if (isset($_POST['add_quotation']) || isset($_POST['update_quotation'])) {
        $project_id = (int) ($_POST['project_id'] ?? 0);
        $project_id = $project_id > 0 ? $project_id : null;
        $scope = trim($_POST['scope_of_work'] ?? '');
        $validity = $_POST['validity_date'] ?? '';
        $validity = $validity !== '' ? $validity : null;
        $status = $_POST['status'] ?? 'draft';
        $notes = trim($_POST['notes'] ?? '');
        if (!in_array($status, ['draft', 'sent', 'approved', 'rejected'], true)) {
            $status = 'draft';
        }

        try {
            $uploaded = $_FILES['quotation_file'] ?? [];
            $extracted = null;
            $projectFile = null;
            if (($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($uploaded['name'] ?? '') !== '') {
                $extracted = extractQuotationUpload($uploaded);
            } elseif ($project_id) {
                $fileStmt = $pdo->prepare("SELECT quotation_path, quotation_name FROM projects WHERE id = ?");
                $fileStmt->execute([$project_id]);
                $projectFile = $fileStmt->fetch() ?: null;
                if ($projectFile && !empty($projectFile['quotation_path'])) {
                    $extracted = extractQuotationPath($projectFile['quotation_path'], (string) $projectFile['quotation_name']);
                }
            }
            if ($extracted && ($extracted['client'] ?? '') !== '') {
                $client = findOrCreateClientByName($extracted['client']);
            } else {
                $client = resolveAccountingClient($_POST['client_id'] ?? '');
            }
            $client_id = (int) $client['id'];
            $client_name = $client['name'];
            if ($extracted && ($extracted['scope'] ?? '') !== '') {
                $scope = $extracted['scope'];
            }
            if ($extracted && ($extracted['notes'] ?? '') !== '') {
                $notes = $extracted['notes'];
            }
            if ($extracted && ($extracted['validity_date'] ?? '') !== '') {
                $validity = $extracted['validity_date'];
            }
            if ($extracted && !$project_id) {
                throw new RuntimeException('Select the project code this quotation is for.');
            }
            $lines = ($extracted && !empty($extracted['items']))
                ? quotationLinesFromExtract($extracted['items'])
                : quotationLinesFromPost($_POST['items'] ?? []);
            $subtotal = 0.0;
            foreach ($lines as $line) {
                $subtotal += $line['line_total'];
            }
            $subtotal = round($subtotal, 2);

            $pdo->beginTransaction();
            if (isset($_POST['add_quotation'])) {
                $quotation_number = generateYearlyNumber('Q', 'client_quotations', 'quotation_number');
                $stmt = $pdo->prepare("INSERT INTO client_quotations (quotation_number, client_id, client_name, project_id, scope_of_work, validity_date, status, subtotal, grand_total, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$quotation_number, $client_id, $client_name, $project_id, $scope, $validity, $status, $subtotal, $subtotal, $notes, $_SESSION['user_id']]);
                $quoteId = (int) $pdo->lastInsertId();
                logActivity($_SESSION['user_id'], 'Created client quotation', 'Accounting', "Q: $quotation_number");
                $_SESSION['success'] = 'Client quotation saved. No accounting entry was created.';
            } else {
                $existing = $pdo->prepare("SELECT status, quotation_number, grand_total FROM client_quotations WHERE id = ? FOR UPDATE");
                $existing->execute([$id]);
                $current = $existing->fetch();
                if (!$current || in_array($current['status'], ['converted', 'approved'], true)) {
                    throw new RuntimeException('This quotation can no longer be edited.');
                }
                $stmt = $pdo->prepare("UPDATE client_quotations SET client_id = ?, client_name = ?, project_id = ?, scope_of_work = ?, validity_date = ?, status = ?, subtotal = ?, grand_total = ?, notes = ? WHERE id = ?");
                $stmt->execute([$client_id, $client_name, $project_id, $scope, $validity, $status, $subtotal, $subtotal, $notes, $id]);
                $pdo->prepare("DELETE FROM client_quotation_items WHERE quotation_id = ?")->execute([$id]);
                $quoteId = $id;
                $quotation_number = $current['quotation_number'];
                $oldTotal = number_format((float) $current['grand_total'], 2);
                $newTotal = number_format($subtotal, 2);
                $amountNote = $oldTotal === $newTotal ? "amount unchanged at ₱$newTotal" : "amount ₱$oldTotal -> ₱$newTotal";
                logActivity($_SESSION['user_id'], $oldTotal === $newTotal ? 'Updated client quotation' : 'Changed quotation amount', 'Accounting', "Q: $quotation_number, $amountNote");
                $_SESSION['success'] = 'Client quotation updated. No accounting entry was created.';
            }

            $itemStmt = $pdo->prepare("INSERT INTO client_quotation_items (quotation_id, line_type, item_name, material_id, description, quantity, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($lines as $line) {
                $itemStmt->execute([$quoteId, $line['line_type'], $line['item_name'], $line['material_id'], $line['description'], $line['quantity'], $line['unit'], $line['unit_price'], $line['line_total']]);
            }
            $attachment = storeQuotationAttachment($_FILES['quotation_file'] ?? [], $quoteId);
            if (!$attachment && $projectFile && !empty($projectFile['quotation_path'])) {
                $attachment = copyStoredQuotation($projectFile['quotation_path'], (string) $projectFile['quotation_name'], $quoteId);
            }
            if ($attachment) {
                $pdo->prepare("UPDATE client_quotations SET attachment_path = ?, attachment_name = ? WHERE id = ?")->execute([$attachment['path'], $attachment['name'], $quoteId]);
            }
            $pdo->commit();
            header('Location: quotations.php?action=view&id=' . $quoteId);
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : userDatabaseError($e);
        }
    }

    if (isset($_POST['set_status'])) {
        $quoteId = (int) ($_POST['quotation_id'] ?? 0);
        $next = $_POST['next_status'] ?? '';
        try {
            $stmt = $pdo->prepare("SELECT * FROM client_quotations WHERE id = ?");
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch();
            if (!$quote) {
                throw new RuntimeException('Quotation not found.');
            }
            $allowed = [
                'draft' => ['sent', 'approved', 'rejected'],
                'sent' => ['approved', 'rejected', 'draft'],
                'approved' => ['rejected'],
                'rejected' => ['draft'],
            ];
            if (!in_array($next, $allowed[$quote['status']] ?? [], true)) {
                throw new RuntimeException('That status change is not allowed.');
            }
            $pdo->prepare("UPDATE client_quotations SET status = ? WHERE id = ?")->execute([$next, $quoteId]);
            logActivity($_SESSION['user_id'], 'Updated quotation status', 'Accounting', $quote['quotation_number'] . ' -> ' . $next);
            $_SESSION['success'] = $next === 'approved'
                ? 'Quotation approved. Executive Admin can encode it as a project from Manage Projects.'
                : 'Quotation status updated.';
            header('Location: quotations.php?action=view&id=' . $quoteId);
            exit();
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : userDatabaseError($e);
        }
    }

    if (isset($_POST['convert_quotation'])) {
        $quoteId = (int) ($_POST['quotation_id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM client_quotations WHERE id = ? FOR UPDATE");
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch();
            if (!$quote) {
                throw new RuntimeException('Quotation not found.');
            }
            if (!quotationCanConvert($quote)) {
                throw new RuntimeException('Only an approved quotation that is still valid can be converted.');
            }
            $dup = $pdo->prepare("SELECT id FROM invoices WHERE quotation_id = ?");
            $dup->execute([$quoteId]);
            if ($dup->fetchColumn()) {
                throw new RuntimeException('This quotation was already converted to an invoice.');
            }
            $itemStmt = $pdo->prepare("SELECT * FROM client_quotation_items WHERE quotation_id = ?");
            $itemStmt->execute([$quoteId]);
            $lines = $itemStmt->fetchAll();
            if (empty($lines)) {
                throw new RuntimeException('The quotation has no lines to copy.');
            }

            $invoice_number = generateNumber('INV', 'invoices', 'invoice_number');
            $amount = round((float) $quote['grand_total'], 2);
            $invoiceDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            $notes = 'Converted from ' . $quote['quotation_number'];
            $insert = $pdo->prepare("INSERT INTO invoices (invoice_number, project_id, quotation_id, client_id, client, client_name, invoice_date, due_date, amount, subtotal, paid_amount, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'sent', ?, ?)");
            $insert->execute([
                $invoice_number,
                $quote['project_id'],
                $quoteId,
                $quote['client_id'] ?? null,
                $quote['client_name'],
                $quote['client_name'],
                $invoiceDate,
                $dueDate,
                $amount,
                $amount,
                $notes,
                $_SESSION['user_id'],
            ]);
            $invoiceId = (int) $pdo->lastInsertId();

            $copy = $pdo->prepare("INSERT INTO invoice_items (invoice_id, line_type, material_id, description, quantity, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($lines as $line) {
                $itemName = trim((string) ($line['item_name'] ?? ''));
                $detail = trim((string) ($line['description'] ?? ''));
                $invoiceDesc = $itemName !== '' ? $itemName : $detail;
                if ($itemName !== '' && $detail !== '' && strcasecmp($itemName, $detail) !== 0) {
                    $invoiceDesc .= ' — ' . $detail;
                }
                $copy->execute([$invoiceId, $line['line_type'], $line['material_id'], $invoiceDesc, $line['quantity'], $line['unit'], $line['unit_price'], $line['line_total']]);
            }
            $pdo->prepare("UPDATE client_quotations SET status = 'converted' WHERE id = ?")->execute([$quoteId]);
            postInvoiceEntry([
                'id' => $invoiceId,
                'invoice_number' => $invoice_number,
                'invoice_date' => $invoiceDate,
                'amount' => $amount,
                'project_id' => $quote['project_id'],
            ], $_SESSION['user_id']);
            $pdo->commit();

            try {
                notifyInvoiceGenerated($invoice_number, $quote['client_name'], $amount);
            } catch (Throwable $e) {
                logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
            }
            logActivity($_SESSION['user_id'], 'Converted quotation to invoice', 'Accounting', $quote['quotation_number'] . ' -> ' . $invoice_number);
            $_SESSION['success'] = 'Invoice ' . $invoice_number . ' created. Accounts Receivable and Sales/Service Revenue were recorded.';
            header('Location: invoices.php?action=view&id=' . $invoiceId);
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof RuntimeException ? $e->getMessage() : userDatabaseError($e);
            $action = 'view';
            $id = $quoteId;
        }
    }
}

$projects = [];
$clients = activeClients();
try {
    $projects = $pdo->query("SELECT id, name, project_code, client, location, subject, quotation_path, quotation_name FROM projects ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $error = $error ?: userDatabaseError($e);
}

$quotations = [];
try {
    $quotations = $pdo->query("
        SELECT q.*, p.name AS project_name, p.project_code
        FROM client_quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        ORDER BY q.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = $error ?: userDatabaseError($e);
}

$quotation = null;
$quotation_items = [];
if (in_array($action, ['edit', 'view'], true) && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT q.*, p.name AS project_name, p.project_code, u.full_name AS created_by_name
        FROM client_quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.id = ?
    ");
    $stmt->execute([$id]);
    $quotation = $stmt->fetch();
    if ($quotation) {
        $items = $pdo->prepare("SELECT * FROM client_quotation_items WHERE quotation_id = ? ORDER BY id");
        $items->execute([$id]);
        $quotation_items = $items->fetchAll();
    }
}

if ($action === 'add' || $action === 'edit') {
    if (!canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: quotations.php' . ($id ? '?action=view&id=' . $id : ''));
        exit();
    }
    if ($action === 'edit' && $quotation && in_array($quotation['status'], ['approved', 'converted'], true)) {
        $_SESSION['error'] = 'Approved and converted quotations cannot be edited.';
        header('Location: quotations.php?action=view&id=' . $id);
        exit();
    }
}

$form_items = $quotation_items;
if ($action === 'add' && empty($form_items)) {
    $form_items = [
        ['line_type' => 'other', 'item_name' => '', 'description' => '', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 0, 'material_id' => ''],
    ];
}

$preview_number = $quotation['quotation_number'] ?? generateYearlyNumber('Q', 'client_quotations', 'quotation_number');

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-signature"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list' && canWriteDepartmentData('accounting')): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $quotation)): ?>
<div class="card">
    <h3><?php echo $action === 'add' ? 'New' : 'Edit'; ?> Client Quotation</h3>
    <p style="color:#64748b;margin-top:0;">This is a client sales proposal. It is separate from Supplier Quotes in Procurement. Saving or approving it does not create income or a receivable. <a href="clients.php">Manage clients</a></p>
    <form method="POST" class="form" id="quotationForm" enctype="multipart/form-data">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_quotation" value="1">
        <?php else: ?>
            <input type="hidden" name="add_quotation" value="1">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label>Quotation Number</label>
                <input type="text" value="<?php echo htmlspecialchars($preview_number); ?>" disabled style="background:#f1f5f9;">
            </div>
            <div class="form-group">
                <label class="required">Client</label>
                <select name="client_id" id="clientName" required>
                    <option value="">Select client</option>
                    <?php foreach ($clients as $clientRow): ?>
                        <?php
                        $selectedClient = (int) ($quotation['client_id'] ?? 0) === (int) $clientRow['id']
                            || ((int) ($quotation['client_id'] ?? 0) === 0 && strcasecmp((string) ($quotation['client_name'] ?? ''), $clientRow['name']) === 0);
                        ?>
                        <option value="<?php echo (int) $clientRow['id']; ?>" data-name="<?php echo htmlspecialchars($clientRow['name']); ?>" <?php echo $selectedClient ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($clientRow['name']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php
                    $knownClientNames = [];
                    foreach ($clients as $clientRow) {
                        $knownClientNames[strtolower($clientRow['name'])] = true;
                    }
                    foreach ($projects as $project) {
                        $projectClient = trim((string) ($project['client'] ?? ''));
                        $key = strtolower($projectClient);
                        if ($projectClient === '' || isset($knownClientNames[$key])) {
                            continue;
                        }
                        $knownClientNames[$key] = true;
                        ?>
                        <option value="name:<?php echo htmlspecialchars($projectClient); ?>" data-name="<?php echo htmlspecialchars($projectClient); ?>">
                            <?php echo htmlspecialchars($projectClient); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Project</label>
                <select name="project_id" id="projectSelect">
                    <option value="">Select project code</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo (int) $project['id']; ?>" data-client="<?php echo htmlspecialchars($project['client'] ?? ''); ?>" <?php echo !empty($project['quotation_path']) ? 'data-quotation="1"' : ''; ?> <?php echo (int) ($quotation['project_id'] ?? ($_GET['project'] ?? 0)) === (int) $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a id="adminQuoteLink" href="#" target="_blank" hidden style="display:inline-block;margin-top:0.35rem;">View quotation file from Executive Admin</a>
            </div>
        </div>
        <div class="form-group">
            <label>Scope of Work</label>
            <textarea name="scope_of_work" rows="3"><?php echo htmlspecialchars($quotation['scope_of_work'] ?? ''); ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Validity Date</label>
                <input type="date" name="validity_date" value="<?php echo htmlspecialchars($quotation['validity_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php foreach (['draft' => 'Draft', 'sent' => 'Sent', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($quotation['status'] ?? 'draft') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Attach quotation file</label>
            <input type="file" name="quotation_file" id="quoteFile" accept=".csv,.txt,.xlsx,.pdf">
            <small id="extractStatus" style="display:block;margin-top:0.35rem;">Choose the project code that Executive Admin encoded. If that project has a quotation file, its client, scope, and items fill this form. You can also attach a file here.</small>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="2"><?php echo htmlspecialchars($quotation['notes'] ?? ''); ?></textarea>
        </div>
        </div>

        <h4>Items</h4>
        <div class="table-responsive">
            <table class="table" id="lineTable">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Section / details</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Unit Price</th>
                        <th>Line Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($form_items as $index => $line): ?>
                    <tr class="line-row">
                        <td>
                            <input type="text" name="items[<?php echo $index; ?>][item_name]" class="line-item" value="<?php echo htmlspecialchars($line['item_name'] ?? $line['description'] ?? ''); ?>" placeholder="Item name">
                            <input type="hidden" name="items[<?php echo $index; ?>][line_type]" class="line-type" value="other">
                            <input type="hidden" name="items[<?php echo $index; ?>][material_id]" class="material-id" value="<?php echo (int) ($line['material_id'] ?? 0); ?>">
                        </td>
                        <td><textarea name="items[<?php echo $index; ?>][description]" class="line-desc" rows="2" placeholder="Section or details"><?php echo htmlspecialchars((isset($line['item_name']) && $line['item_name'] !== '' && strcasecmp((string) $line['item_name'], (string) ($line['description'] ?? '')) === 0) ? '' : ($line['description'] ?? '')); ?></textarea></td>
                        <td><input type="number" step="0.01" min="0" name="items[<?php echo $index; ?>][quantity]" class="line-qty" value="<?php echo htmlspecialchars($line['quantity'] ?? 1); ?>"></td>
                        <td><input type="text" name="items[<?php echo $index; ?>][unit]" class="line-unit" value="<?php echo htmlspecialchars($line['unit'] ?? 'lot'); ?>"></td>
                        <td><input type="number" step="0.01" min="0" name="items[<?php echo $index; ?>][unit_price]" class="line-price" value="<?php echo htmlspecialchars($line['unit_price'] ?? 0); ?>"></td>
                        <td class="line-total">₱0.00</td>
                        <td><button type="button" class="btn btn-sm btn-secondary remove-line">Remove</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-secondary" id="addLine"><i class="fas fa-plus"></i> Add Line</button>
        <div style="margin-top:1rem;text-align:right;">
            <div>Subtotal: <strong id="subtotalText">₱0.00</strong></div>
            <div>Grand Total: <strong id="grandText">₱0.00</strong></div>
            <div style="color:#64748b;font-size:0.85rem;">No VAT. Grand total equals the subtotal.</div>
        </div>
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Quotation</button>
            <a href="quotations.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<template id="lineTemplate">
    <tr class="line-row">
        <td>
            <input type="text" class="line-item" value="" placeholder="Item name">
            <input type="hidden" class="line-type" value="other">
            <input type="hidden" class="material-id" value="">
        </td>
        <td><textarea class="line-desc" rows="2" placeholder="Section or details"></textarea></td>
        <td><input type="number" step="0.01" min="0" class="line-qty" value="1"></td>
        <td><input type="text" class="line-unit" value="lot"></td>
        <td><input type="number" step="0.01" min="0" class="line-price" value="0"></td>
        <td class="line-total">₱0.00</td>
        <td><button type="button" class="btn btn-sm btn-secondary remove-line">Remove</button></td>
    </tr>
</template>
<script>
(function () {
    const table = document.querySelector('#lineTable tbody');
    const money = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function reindex() {
        table.querySelectorAll('.line-row').forEach((row, i) => {
            row.querySelector('.line-item').name = 'items[' + i + '][item_name]';
            row.querySelector('.line-type').name = 'items[' + i + '][line_type]';
            row.querySelector('.material-id').name = 'items[' + i + '][material_id]';
            row.querySelector('.line-desc').name = 'items[' + i + '][description]';
            row.querySelector('.line-qty').name = 'items[' + i + '][quantity]';
            row.querySelector('.line-unit').name = 'items[' + i + '][unit]';
            row.querySelector('.line-price').name = 'items[' + i + '][unit_price]';
        });
    }
    function recalc() {
        let subtotal = 0;
        table.querySelectorAll('.line-row').forEach((row) => {
            const qty = parseFloat(row.querySelector('.line-qty').value) || 0;
            const price = parseFloat(row.querySelector('.line-price').value) || 0;
            const total = Math.round(qty * price * 100) / 100;
            row.querySelector('.line-total').textContent = money(total);
            subtotal += total;
        });
        subtotal = Math.round(subtotal * 100) / 100;
        document.getElementById('subtotalText').textContent = money(subtotal);
        document.getElementById('grandText').textContent = money(subtotal);
    }
    table.addEventListener('input', recalc);
    table.addEventListener('click', (event) => {
        if (!event.target.closest('.remove-line')) return;
        const rows = table.querySelectorAll('.line-row');
        if (rows.length === 1) return;
        event.target.closest('.line-row').remove();
        reindex();
        recalc();
    });
    document.getElementById('addLine').addEventListener('click', () => {
        const row = document.getElementById('lineTemplate').content.firstElementChild.cloneNode(true);
        table.appendChild(row);
        reindex();
        recalc();
    });
    const projectSelect = document.getElementById('projectSelect');
    const clientName = document.getElementById('clientName');
    let clientFromFile = false;
    function applyProjectClient() {
        if (clientFromFile) return;
        const wanted = (projectSelect.selectedOptions[0]?.dataset.client || '').trim().toLowerCase();
        if (!wanted) return;
        for (const option of clientName.options) {
            if ((option.dataset.name || '').trim().toLowerCase() === wanted) {
                clientName.value = option.value;
                return;
            }
        }
    }
    projectSelect.addEventListener('change', () => {
        const option = projectSelect.selectedOptions[0];
        if (option && option.dataset.quotation === '1') {
            loadProjectQuotation();
            return;
        }
        const link = document.getElementById('adminQuoteLink');
        if (link) link.hidden = true;
        if (!clientFromFile) applyProjectClient();
    });
    if (!clientName.value) applyProjectClient();

    function applyExtracted(data) {
        clientFromFile = true;
        if (data.client) {
            const wanted = data.client.trim().toLowerCase();
            let matched = false;
            for (const option of clientName.options) {
                if ((option.dataset.name || '').trim().toLowerCase() === wanted) {
                    clientName.value = option.value;
                    matched = true;
                    break;
                }
            }
            if (!matched) {
                const option = new Option(data.client, 'name:' + data.client);
                option.dataset.name = data.client;
                clientName.add(option);
                clientName.value = option.value;
            }
        }
        if (data.validity_date) {
            document.querySelector('[name="validity_date"]').value = data.validity_date;
        }
        if (data.scope) {
            document.querySelector('[name="scope_of_work"]').value = data.scope;
        }
        if (data.notes) {
            document.querySelector('[name="notes"]').value = data.notes;
        }
        if (Array.isArray(data.items) && data.items.length) {
            table.innerHTML = '';
            data.items.forEach((item) => {
                const row = document.getElementById('lineTemplate').content.firstElementChild.cloneNode(true);
                row.querySelector('.line-item').value = item.item_name || item.description || '';
                row.querySelector('.line-desc').value = item.item_name ? (item.description || '') : '';
                row.querySelector('.line-qty').value = item.quantity || 1;
                row.querySelector('.line-unit').value = item.unit || 'pcs';
                row.querySelector('.line-price').value = item.unit_price || 0;
                table.appendChild(row);
            });
            reindex();
            recalc();
        }
        const link = document.getElementById('adminQuoteLink');
        if (link) {
            if (data.file_url) {
                link.href = data.file_url;
                link.textContent = 'View ' + (data.file_name || 'quotation file');
                link.hidden = false;
            } else {
                link.hidden = true;
            }
        }
        projectSelect.required = true;
    }

    async function loadProjectQuotation() {
        const status = document.getElementById('extractStatus');
        const saveBtn = document.querySelector('#quotationForm button[type="submit"]');
        status.textContent = 'Loading the quotation file from Executive Admin...';
        if (saveBtn) saveBtn.disabled = true;
        const body = new FormData();
        body.append('load_project_quotation', '1');
        body.append('project_id', projectSelect.value);
        try {
            const response = await fetch('quotations.php', { method: 'POST', body });
            const data = await response.json();
            if (!response.ok || data.error) {
                status.textContent = data.error || 'Could not open the quotation file.';
                return;
            }
            applyExtracted(data);
            status.textContent = 'Client, subject, and items came from the quotation file Executive Admin attached. Review them, then save.';
        } catch (error) {
            status.textContent = 'Could not open the quotation file.';
        } finally {
            if (saveBtn) saveBtn.disabled = false;
        }
    }

    document.getElementById('quoteFile')?.addEventListener('change', async () => {
        const input = document.getElementById('quoteFile');
        const status = document.getElementById('extractStatus');
        const saveBtn = document.querySelector('#quotationForm button[type="submit"]');
        if (!input.files[0]) {
            return;
        }
        status.textContent = 'Reading the file...';
        if (saveBtn) saveBtn.disabled = true;
        const body = new FormData();
        body.append('extract_quotation_file', '1');
        body.append('quotation_file', input.files[0]);
        try {
            const response = await fetch('quotations.php', { method: 'POST', body });
            const data = await response.json();
            if (!response.ok || data.error) {
                status.textContent = data.error || 'Could not read the file.';
                return;
            }
            applyExtracted(data);
            status.textContent = 'Client and items are from the file. Select the project code, then save.';
            projectSelect.focus();
        } catch (error) {
            status.textContent = 'Could not read the file.';
        } finally {
            if (saveBtn) saveBtn.disabled = false;
        }
    });
    if (new URLSearchParams(location.search).get('project') && projectSelect.selectedOptions[0]?.dataset.quotation === '1') {
        loadProjectQuotation();
    }
    reindex();
    recalc();
})();
</script>

<?php elseif ($action === 'view' && $quotation): ?>
<div class="card">
    <h3>Quotation <?php echo htmlspecialchars($quotation['quotation_number']); ?></h3>
    <p style="color:#64748b;">This document is a proposal. <?php echo $quotation['status'] === 'converted' ? 'The accounting entry was created on the invoice.' : 'It has no accounting entry.'; ?></p>
    <div class="view-details">
        <div class="detail-row"><span>Client Name:</span> <strong><?php echo htmlspecialchars($quotation['client_name']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($quotation['project_code'] ? $quotation['project_code'] . ' - ' . $quotation['project_name'] : 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo htmlspecialchars($quotation['status']); ?>"><?php echo ucfirst($quotation['status']); ?></span></div>
        <div class="detail-row"><span>Validity Date:</span> <?php echo $quotation['validity_date'] ? date('M d, Y', strtotime($quotation['validity_date'])) : 'No expiry'; ?></div>
        <div class="detail-row"><span>Grand Total:</span> <strong>₱<?php echo number_format($quotation['grand_total'], 2); ?></strong></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($quotation['created_by_name'] ?? 'N/A'); ?></div>
        <?php if ($quotation['scope_of_work']): ?>
            <div class="detail-row"><span>Scope of Work:</span> <?php echo nl2br(htmlspecialchars($quotation['scope_of_work'])); ?></div>
        <?php endif; ?>
        <?php if ($quotation['notes']): ?>
            <div class="detail-row"><span>Notes:</span> <?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($quotation['attachment_path'])): ?>
            <div class="detail-row"><span>Attachment:</span> <a href="<?php echo APP_URL . htmlspecialchars($quotation['attachment_path']); ?>" target="_blank"><?php echo htmlspecialchars($quotation['attachment_name'] ?: 'Quotation file'); ?></a></div>
        <?php endif; ?>
    </div>
    <div class="table-responsive" style="margin-top:1rem;">
        <table class="table">
            <thead>
                <tr><th>Item</th><th>Section / details</th><th>Qty</th><th>Unit</th><th>Unit Price</th><th>Line Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($quotation_items as $line): ?>
                <tr>
                    <td><?php echo htmlspecialchars($line['item_name'] ?: $line['description']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars((isset($line['item_name']) && $line['item_name'] !== '' && strcasecmp((string) $line['item_name'], (string) $line['description']) === 0) ? '' : $line['description'])); ?></td>
                    <td><?php echo number_format($line['quantity'], 2); ?></td>
                    <td><?php echo htmlspecialchars($line['unit']); ?></td>
                    <td>₱<?php echo number_format($line['unit_price'], 2); ?></td>
                    <td>₱<?php echo number_format($line['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top:1.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('accounting') && $quotation['status'] !== 'converted'): ?>
            <?php if (in_array($quotation['status'], ['draft', 'sent'], true)): ?>
                <a href="?action=edit&id=<?php echo (int) $quotation['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
                <form method="POST">
                    <input type="hidden" name="set_status" value="1">
                    <input type="hidden" name="quotation_id" value="<?php echo (int) $quotation['id']; ?>">
                    <input type="hidden" name="next_status" value="approved">
                    <button type="submit" class="btn btn-success">Mark Approved</button>
                </form>
            <?php endif; ?>
            <?php if ($quotation['status'] === 'draft'): ?>
                <form method="POST">
                    <input type="hidden" name="set_status" value="1">
                    <input type="hidden" name="quotation_id" value="<?php echo (int) $quotation['id']; ?>">
                    <input type="hidden" name="next_status" value="sent">
                    <button type="submit" class="btn btn-secondary">Mark Sent</button>
                </form>
            <?php endif; ?>
            <?php if (in_array($quotation['status'], ['draft', 'sent', 'approved'], true)): ?>
                <form method="POST">
                    <input type="hidden" name="set_status" value="1">
                    <input type="hidden" name="quotation_id" value="<?php echo (int) $quotation['id']; ?>">
                    <input type="hidden" name="next_status" value="rejected">
                    <button type="submit" class="btn btn-secondary">Reject</button>
                </form>
            <?php endif; ?>
            <?php if ($quotation['status'] === 'rejected'): ?>
                <form method="POST">
                    <input type="hidden" name="set_status" value="1">
                    <input type="hidden" name="quotation_id" value="<?php echo (int) $quotation['id']; ?>">
                    <input type="hidden" name="next_status" value="draft">
                    <button type="submit" class="btn btn-secondary">Reopen as Draft</button>
                </form>
            <?php endif; ?>
            <?php if (quotationCanConvert($quotation)): ?>
                <form method="POST">
                    <input type="hidden" name="convert_quotation" value="1">
                    <input type="hidden" name="quotation_id" value="<?php echo (int) $quotation['id']; ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice-dollar"></i> Convert to Invoice</button>
                </form>
            <?php elseif ($quotation['status'] === 'approved'): ?>
                <span class="badge badge-overdue">Past validity date</span>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($quotation['status'] === 'approved' && empty($quotation['project_id'])): ?>
            <a href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add&source=quotation:<?php echo (int) $quotation['id']; ?>" class="btn btn-primary"><i class="fas fa-building"></i> Encode Project</a>
        <?php endif; ?>
        <a href="quotations.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Client Name</th>
                    <th>Project</th>
                    <th>Grand Total</th>
                    <th>Validity</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">No client quotations yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($quotations as $quote): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($quote['quotation_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($quote['client_name']); ?></td>
                        <td><?php echo htmlspecialchars($quote['project_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($quote['grand_total'], 2); ?></td>
                        <td><?php echo $quote['validity_date'] ? date('M d, Y', strtotime($quote['validity_date'])) : '—'; ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($quote['status']); ?>"><?php echo ucfirst($quote['status']); ?></span></td>
                        <td>
                            <a href="?action=view&id=<?php echo (int) $quote['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if ($quote['status'] === 'approved' && empty($quote['project_id'])): ?>
                                <a href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add&source=quotation:<?php echo (int) $quote['id']; ?>" class="btn btn-sm btn-primary" title="Encode project">Encode</a>
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
