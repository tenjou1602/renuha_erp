<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mailer.php';
require_once '../../includes/accounting_ledger.php';

requireDepartment(['accounting']);

$page_title = 'Invoices';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Generate invoice number
function generateInvoiceNumber() {
    global $pdo;
    return generateNumber('INV', 'invoices', 'invoice_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_invoice']) || isset($_POST['update_invoice'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . basename(__FILE__));
        exit();
    }
    if (isset($_POST['add_invoice']) || isset($_POST['update_invoice'])) {
        $invoice_number = $_POST['invoice_number'] ?? generateInvoiceNumber();
        $project_id = (int)($_POST['project_id'] ?? 0);
        $locked = isset($_POST['update_invoice']) && ledgerEntryExists('invoice', $id);
        $client_id = null;
        $client = '';
        $errors = [];
        if (!$locked) {
            try {
                $clientRow = resolveAccountingClient($_POST['client_id'] ?? '');
                $client_id = (int) $clientRow['id'];
                $client = $clientRow['name'];
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $procurement_quotation_id = isset($_POST['add_invoice']) ? (int) ($_POST['procurement_quotation_id'] ?? 0) : 0;
        $postedInvoiceStatus = $_POST['invoice_status'] ?? '';
        $saveStatus = null;
        
        if (!$locked && $client === '') $errors[] = 'Client is required.';
        if (!$locked && $amount <= 0) $errors[] = 'Amount must be greater than 0.';
        if (isset($_POST['add_invoice'])) {
            if ($procurement_quotation_id <= 0) {
                $errors[] = 'Select a received quotation.';
            } else {
                $quoteStmt = $pdo->prepare("SELECT id, status, project_id FROM quotations WHERE id = ?");
                $quoteStmt->execute([$procurement_quotation_id]);
                $receivedQuote = $quoteStmt->fetch();
                $takenStmt = $pdo->prepare("SELECT id FROM invoices WHERE procurement_quotation_id = ?");
                $takenStmt->execute([$procurement_quotation_id]);
                if (!$receivedQuote || $receivedQuote['status'] !== 'received') {
                    $errors[] = 'This quotation is not available to invoice.';
                } elseif ($takenStmt->fetchColumn()) {
                    $errors[] = 'This quotation has already been invoiced.';
                } elseif ($project_id <= 0 && (int) $receivedQuote['project_id'] > 0) {
                    $project_id = (int) $receivedQuote['project_id'];
                }
            }
        }
        if (isset($_POST['update_invoice']) && $postedInvoiceStatus !== '') {
            $statusStmt = $pdo->prepare("SELECT status FROM invoices WHERE id = ?");
            $statusStmt->execute([$id]);
            $existingInvoiceStatus = (string) $statusStmt->fetchColumn();
            if ($existingInvoiceStatus === 'draft' && in_array($postedInvoiceStatus, ['draft', 'sent'], true)) {
                $saveStatus = $postedInvoiceStatus;
            } elseif ($existingInvoiceStatus === 'draft') {
                $errors[] = 'A draft invoice can be saved as Draft or Sent. Record a payment to mark it paid.';
            } elseif ($postedInvoiceStatus !== $existingInvoiceStatus) {
                $errors[] = 'Record a payment to mark this invoice paid.';
            }
        }
        if (!empty($errors)) {
            $error = implode(' ', $errors);
        }
        
            if (empty($errors) && $project_id > 0) {
                $budgetStmt = $pdo->prepare("SELECT estimated_budget FROM projects WHERE id = ?");
                $budgetStmt->execute([$project_id]);
                $budget = (float) $budgetStmt->fetchColumn();
                $usedStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE project_id = ? AND status <> 'cancelled' AND id <> ?");
                $usedStmt->execute([$project_id, isset($_POST['update_invoice']) ? $id : 0]);
                $used = (float) $usedStmt->fetchColumn();
                if ($budget > 0 && ($used + $amount) > $budget) {
                    $override = trim($_POST['override_reason'] ?? '');
                    if (!isAdmin() || $override === '') {
                        $errors[] = 'Invoiced amounts for this project cannot exceed its estimated budget unless Admin records a reason.';
                    } else {
                        writeAudit('Budget override', 'Accounting', 'invoices', $id ?: null, ['used' => $used, 'budget' => $budget], ['amount' => $amount], $override);
                    }
                }
            }
            if (!empty($errors)) {
                $error = implode(' ', $errors);
            }
            if (empty($errors)) {
            $project_id = $project_id > 0 ? $project_id : null;
            $due_date = $due_date !== '' ? $due_date : null;
            try {
                assertMonthOpen('accounting', $invoice_date);
                if (isset($_POST['add_invoice'])) {
                    $pdo->beginTransaction();
                    $lockQuote = $pdo->prepare("SELECT id, status FROM quotations WHERE id = ? FOR UPDATE");
                    $lockQuote->execute([$procurement_quotation_id]);
                    $lockedQuote = $lockQuote->fetch();
                    $lockInvoice = $pdo->prepare("SELECT id FROM invoices WHERE procurement_quotation_id = ?");
                    $lockInvoice->execute([$procurement_quotation_id]);
                    if (!$lockedQuote || $lockedQuote['status'] !== 'received' || $lockInvoice->fetchColumn()) {
                        throw new RuntimeException('This quotation has already been invoiced.');
                    }
                    $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, project_id, quotation_id, procurement_quotation_id, client_id, client, client_name, invoice_date, due_date, amount, subtotal, notes, status, created_by) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?)");
                    $stmt->execute([$invoice_number, $project_id, $procurement_quotation_id, $client_id, $client, $client, $invoice_date, $due_date, $amount, $amount, $notes, $_SESSION['user_id']]);
                    $markQuoted = $pdo->prepare("UPDATE quotations SET status = 'invoiced' WHERE id = ? AND status = 'received'");
                    $markQuoted->execute([$procurement_quotation_id]);
                    if ($markQuoted->rowCount() !== 1) {
                        throw new RuntimeException('This quotation has already been invoiced.');
                    }
                    $newId = (int) $pdo->lastInsertId();
                    postInvoiceEntry([
                        'id' => $newId,
                        'invoice_number' => $invoice_number,
                        'invoice_date' => $invoice_date,
                        'amount' => $amount,
                        'project_id' => $project_id,
                    ], $_SESSION['user_id']);
                    $pdo->commit();
                    try {
                        notifyInvoiceGenerated($invoice_number, $client, $amount);
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                    
                    logActivity($_SESSION['user_id'], 'Created invoice', 'Accounting', "INV: $invoice_number");
                    $_SESSION['success'] = "Invoice created. Accounts Receivable and Sales/Service Revenue were recorded.";
                } else {
                    $pdo->beginTransaction();
                    if ($locked) {
                        $stmt = $pdo->prepare("UPDATE invoices SET invoice_date = ?, due_date = ?, notes = ? WHERE id = ?");
                        $stmt->execute([$invoice_date, $due_date, $notes, $id]);
                        $_SESSION['success'] = "Dates and notes updated. The amount stays in the ledger.";
                    } elseif ($saveStatus !== null) {
                        $stmt = $pdo->prepare("UPDATE invoices SET project_id = ?, client_id = ?, client = ?, client_name = ?, invoice_date = ?, due_date = ?, amount = ?, subtotal = ?, notes = ?, status = ? WHERE id = ?");
                        $stmt->execute([$project_id, $client_id, $client, $client, $invoice_date, $due_date, $amount, $amount, $notes, $saveStatus, $id]);
                        if ($saveStatus === 'sent') {
                            postInvoiceEntry([
                                'id' => $id,
                                'invoice_number' => $invoice_number,
                                'invoice_date' => $invoice_date,
                                'amount' => $amount,
                                'project_id' => $project_id,
                            ], $_SESSION['user_id']);
                            logStatusChange('invoices', $id, 'draft', 'sent');
                            $_SESSION['success'] = 'Invoice marked sent. Accounts Receivable and Sales/Service Revenue were recorded.';
                        } else {
                            $_SESSION['success'] = "Invoice updated successfully!";
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE invoices SET project_id = ?, client_id = ?, client = ?, client_name = ?, invoice_date = ?, due_date = ?, amount = ?, subtotal = ?, notes = ? WHERE id = ?");
                        $stmt->execute([$project_id, $client_id, $client, $client, $invoice_date, $due_date, $amount, $amount, $notes, $id]);
                        $_SESSION['success'] = "Invoice updated successfully!";
                    }
                    $pdo->commit();
                    if ($saveStatus === 'sent') {
                        try {
                            notifyInvoiceGenerated($invoice_number, $client, $amount);
                        } catch (Throwable $mailError) {
                            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $mailError->getMessage());
                        }
                    }
                    
                    logActivity($_SESSION['user_id'], 'Updated invoice', 'Accounting', "INV: $invoice_number");
                }
                header('Location: invoices.php');
                exit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException ? $e->getMessage() : userDatabaseError($e);
            }
        }
    }
    
    if (isset($_POST['cancel_invoice'])) {
        if (!canWriteDepartmentData('accounting')) {
            $_SESSION['error'] = 'You cannot cancel this invoice.';
            header('Location: invoices.php');
            exit();
        }
        $invoice_id = (int) ($_POST['invoice_id'] ?? 0);
        $dated = $pdo->prepare("SELECT invoice_date FROM invoices WHERE id = ?");
        $dated->execute([$invoice_id]);
        try {
            assertMonthOpen('accounting', $dated->fetchColumn() ?: null);
        } catch (RuntimeException $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: invoices.php?action=view&id=' . $invoice_id);
            exit();
        }
        $stmt = $pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = ? AND status <> 'cancelled'");
        $stmt->execute([$invoice_id]);
        if ($stmt->rowCount() === 1) {
            logStatusChange('invoices', $invoice_id, 'sent', 'cancelled');
            $_SESSION['success'] = 'Invoice cancelled. The record was kept.';
        }
        header('Location: invoices.php?action=view&id=' . $invoice_id);
        exit();
    }

    if (isset($_POST['record_payment'])) {
        if (!canWriteDepartmentData('accounting')) {
            $_SESSION['error'] = 'Administrators have view-only access.';
            header('Location: invoices.php');
            exit();
        }
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $payment_number = generateNumber('PAY', 'payments', 'payment_number');
        $amount = round((float)($_POST['payment_amount'] ?? 0), 2);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $reference_number = trim($_POST['reference_number'] ?? '');
        
        if ($amount <= 0) {
            $error = 'Payment amount must be greater than 0.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
                $stmt->execute([$invoice_id]);
                $invoice = $stmt->fetch();
                if (!$invoice || in_array($invoice['status'], ['draft', 'paid', 'cancelled'], true)) {
                    throw new RuntimeException($invoice && $invoice['status'] === 'draft'
                        ? 'Mark the invoice as Sent before recording a payment.'
                        : 'This invoice cannot accept a payment.');
                }
                $remaining = round((float) $invoice['amount'] - (float) ($invoice['paid_amount'] ?? 0), 2);
                if ($amount > $remaining) {
                    throw new RuntimeException('Amount paid cannot be greater than the remaining balance of ₱' . number_format($remaining, 2) . '.');
                }
                assertNotOwnApproval($invoice['created_by'] ?? 0);
                assertMonthOpen('accounting', $payment_date);
                
                $new_paid = round((float) ($invoice['paid_amount'] ?? 0) + $amount, 2);
                $new_status = $new_paid >= (float) $invoice['amount'] ? 'paid' : 'partial';
                
                $stmt = $pdo->prepare("INSERT INTO payments (payment_number, invoice_id, amount, payment_date, payment_method, reference_number, created_by) VALUES (?, ?, ?, ?, 'Cash', ?, ?)");
                $stmt->execute([$payment_number, $invoice_id, $amount, $payment_date, $reference_number, $_SESSION['user_id']]);
                $paymentId = (int) $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("UPDATE invoices SET paid_amount = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_paid, $new_status, $invoice_id]);
                logStatusChange('invoices', $invoice_id, $invoice['status'], $new_status);
                postPaymentEntry([
                    'id' => $paymentId,
                    'payment_number' => $payment_number,
                    'payment_date' => $payment_date,
                    'amount' => $amount,
                ], $invoice, $_SESSION['user_id']);
                
                $pdo->commit();
                
                logActivity($_SESSION['user_id'], 'Recorded payment', 'Accounting', "Payment: $payment_number");
                $_SESSION['success'] = "Cash payment recorded. Remaining balance is ₱" . number_format(max(0, $remaining - $amount), 2) . ".";
                header('Location: invoices.php?action=view&id=' . $invoice_id);
                exit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException ? $e->getMessage() : userDatabaseError($e);
                $action = 'view';
                $id = $invoice_id;
            }
        }
    }
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, name, project_code, client FROM projects ORDER BY name")->fetchAll();
$clients = activeClients();
$received_quotations = [];
if ($action === 'add') {
    $received_quotations = $pdo->query("
        SELECT q.id, q.quotation_number, q.total_amount, q.project_id,
               p.project_code, p.name AS project_name, p.client
        FROM quotations q
        LEFT JOIN projects p ON p.id = q.project_id
        WHERE q.status = 'received'
          AND NOT EXISTS (
              SELECT 1 FROM invoices i WHERE i.procurement_quotation_id = q.id
          )
        ORDER BY q.quotation_number
    ")->fetchAll();
}

// Get invoices
$invoices = [];
try {
    $query = "
        SELECT i.*, p.name as project_name, p.project_code, u.full_name as created_by_name,
               COALESCE(cq.quotation_number, pq.quotation_number) AS quotation_number,
               COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(i.client_name), ''), NULLIF(TRIM(i.client), '')) AS client_label,
               " . invoiceAccountingPaidSql('i') . " AS accounting_paid,
               " . invoiceAccountingExpenseSql('i') . " AS accounting_expenses
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN clients c ON c.id = i.client_id
        LEFT JOIN client_quotations cq ON cq.id = i.quotation_id
        LEFT JOIN quotations pq ON pq.id = i.procurement_quotation_id
        ORDER BY i.created_at DESC
    ";
    $invoices = $pdo->query($query)->fetchAll();
    $invoices = array_map('invoiceWithAccountingPayment', $invoices);
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single invoice for edit/view
$invoice_details = null;
$invoice_payments = [];
$invoice_items = [];
$invoice_journal = [];
$invoice_locked = false;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT i.*, p.name as project_name, p.project_code, u.full_name as created_by_name,
               cq.quotation_number AS client_quotation_number,
               pq.quotation_number AS procurement_quotation_number,
               COALESCE(cq.quotation_number, pq.quotation_number) AS quotation_number,
               COALESCE(NULLIF(TRIM(c.name), ''), NULLIF(TRIM(i.client_name), ''), NULLIF(TRIM(i.client), '')) AS client_label,
               " . invoiceAccountingPaidSql('i') . " AS accounting_paid,
               " . invoiceAccountingExpenseSql('i') . " AS accounting_expenses
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN users u ON i.created_by = u.id
        LEFT JOIN clients c ON c.id = i.client_id
        LEFT JOIN client_quotations cq ON cq.id = i.quotation_id
        LEFT JOIN quotations pq ON pq.id = i.procurement_quotation_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $invoice_details = $stmt->fetch();
    if ($invoice_details && $action === 'view') {
        $invoice_details = invoiceWithAccountingPayment($invoice_details);
    }
    
    if ($invoice_details) {
        $payments_stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY created_at DESC");
        $payments_stmt->execute([$id]);
        $invoice_payments = $payments_stmt->fetchAll();
        $item_stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
        $item_stmt->execute([$id]);
        $invoice_items = $item_stmt->fetchAll();
        $journal_stmt = $pdo->prepare("
            SELECT je.entry_date, je.description, a.name AS account_name, jl.debit, jl.credit
            FROM journal_entries je
            JOIN journal_lines jl ON jl.journal_entry_id = je.id
            JOIN chart_of_accounts a ON a.id = jl.account_id
            WHERE (je.reference_type = 'invoice' AND je.reference_id = ?)
               OR (je.reference_type = 'payment' AND je.reference_id IN (SELECT id FROM payments WHERE invoice_id = ?))
            ORDER BY je.entry_date, je.id, jl.id
        ");
        $journal_stmt->execute([$id, $id]);
        $invoice_journal = $journal_stmt->fetchAll();
        $invoice_locked = ledgerEntryExists('invoice', $id);
    }
}


if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: ' . basename(__FILE__) . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</a><?php endif; ?>
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
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Invoice</h3>
    <form method="POST" class="form">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_invoice" value="1">
            <input type="hidden" name="invoice_number" value="<?php echo htmlspecialchars($invoice_details['invoice_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_invoice" value="1">
        <?php endif; ?>

        <?php if ($action === 'add'): ?>
        <div class="form-group">
            <label class="required">Received Quotation</label>
            <select name="procurement_quotation_id" id="receivedQuotation" required>
                <option value="">Select a received quotation</option>
                <?php foreach ($received_quotations as $quote): ?>
                    <?php
                    $quoteLabel = $quote['quotation_number'];
                    if (!empty($quote['project_code'])) {
                        $quoteLabel .= ' - ' . $quote['project_code'];
                    }
                    if (!empty($quote['project_name'])) {
                        $quoteLabel .= ' - ' . $quote['project_name'];
                    }
                    ?>
                    <option
                        value="<?php echo (int) $quote['id']; ?>"
                        data-project="<?php echo (int) ($quote['project_id'] ?? 0); ?>"
                        data-client="<?php echo htmlspecialchars($quote['client'] ?? ''); ?>"
                        data-amount="<?php echo htmlspecialchars(number_format((float) ($quote['total_amount'] ?? 0), 2, '.', '')); ?>"
                    ><?php echo htmlspecialchars($quoteLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Invoice Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($invoice_details['invoice_number']) : generateInvoiceNumber(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="invoice_number" value="<?php echo generateInvoiceNumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Project</label>
                <select name="project_id" id="invoiceProject">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" data-client="<?php echo htmlspecialchars($project['client'] ?? ''); ?>" <?php echo ($invoice_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Client</label>
                <?php if (!empty($invoice_locked)): ?>
                    <input type="text" value="<?php echo htmlspecialchars(invoiceClientLabel($invoice_details)); ?>" readonly>
                <?php else: ?>
                    <select name="client_id" id="invoiceClient" required>
                        <option value="">Select client</option>
                        <?php foreach ($clients as $clientRow): ?>
                            <?php
                            $selectedClient = (int) ($invoice_details['client_id'] ?? 0) === (int) $clientRow['id']
                                || ((int) ($invoice_details['client_id'] ?? 0) === 0 && isset($invoice_details) && strcasecmp(invoiceClientLabel($invoice_details), $clientRow['name']) === 0);
                            ?>
                            <option value="<?php echo (int) $clientRow['id']; ?>" data-name="<?php echo htmlspecialchars($clientRow['name']); ?>" <?php echo $selectedClient ? 'selected' : ''; ?>><?php echo htmlspecialchars($clientRow['name']); ?></option>
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
                            <option value="name:<?php echo htmlspecialchars($projectClient); ?>" data-name="<?php echo htmlspecialchars($projectClient); ?>"><?php echo htmlspecialchars($projectClient); ?></option>
                        <?php } ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Invoice Date</label>
                <input type="date" name="invoice_date" value="<?php echo $invoice_details['invoice_date'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?php echo $invoice_details['due_date'] ?? ''; ?>">
            </div>
            <?php if ($action === 'edit' && ($invoice_details['status'] ?? '') === 'draft'): ?>
            <div class="form-group">
                <label>Status</label>
                <select name="invoice_status">
                    <option value="draft" selected>Draft</option>
                    <option value="sent">Sent</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" id="invoiceAmount" required value="<?php echo $invoice_details['amount'] ?? 0; ?>" min="0" <?php echo !empty($invoice_locked) ? 'readonly' : ''; ?>>
                <?php if (!empty($invoice_locked)): ?>
                    <small style="color:#64748b;">This amount is already in the ledger.</small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"><?php echo htmlspecialchars($invoice_details['notes'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Invoice</button>
            <a href="invoices.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $invoice_details): ?>
<!-- View Invoice -->
<div class="card">
    <h3>Invoice Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Invoice #:</span> <strong><?php echo htmlspecialchars($invoice_details['invoice_number']); ?></strong></div>
        <div class="detail-row"><span>Client Name:</span> <?php echo htmlspecialchars(invoiceClientLabel($invoice_details)); ?></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($invoice_details['project_name'] ?? 'N/A'); ?></div>
        <?php if (!empty($invoice_details['client_quotation_number'])): ?>
            <div class="detail-row"><span>Source Quotation:</span> <a href="quotations.php?action=view&id=<?php echo (int) $invoice_details['quotation_id']; ?>"><?php echo htmlspecialchars($invoice_details['client_quotation_number']); ?></a></div>
        <?php elseif (!empty($invoice_details['procurement_quotation_number'])): ?>
            <div class="detail-row"><span>Quotation No.:</span> <?php echo htmlspecialchars($invoice_details['procurement_quotation_number']); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Invoice Amount:</span> <strong>₱<?php echo number_format($invoice_details['amount'], 2); ?></strong></div>
        <?php if ($action === 'view' && !empty($invoice_details['accounting_expenses'])): ?>
            <div class="detail-row"><span>Accounting payment:</span> <?php echo htmlspecialchars($invoice_details['accounting_expenses']); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Amount Paid:</span> ₱<?php echo number_format($invoice_details['paid_amount'] ?? 0, 2); ?></div>
        <div class="detail-row"><span>Remaining Balance:</span> <strong>₱<?php echo number_format(($invoice_details['amount'] - ($invoice_details['paid_amount'] ?? 0)), 2); ?></strong></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $invoice_details['status']; ?>"><?php echo ucfirst($invoice_details['status']); ?></span></div>
        <div class="detail-row"><span>Invoice Date:</span> <?php echo date('M d, Y', strtotime($invoice_details['invoice_date'])); ?></div>
        <div class="detail-row"><span>Due Date:</span> <?php echo $invoice_details['due_date'] ? date('M d, Y', strtotime($invoice_details['due_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($invoice_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($invoice_details['created_at'])); ?></div>
        <?php if ($invoice_details['notes']): ?>
            <div class="detail-row"><span>Notes:</span> <?php echo nl2br(htmlspecialchars($invoice_details['notes'])); ?></div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($invoice_items)): ?>
    <h4 style="margin-top:1.5rem;">Invoice Items</h4>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Type</th><th>Description</th><th>Qty</th><th>Unit</th><th>Unit Price</th><th>Line Total</th></tr></thead>
            <tbody>
                <?php foreach ($invoice_items as $line): ?>
                <tr>
                    <td><?php echo ucfirst($line['line_type']); ?></td>
                    <td><?php echo htmlspecialchars($line['description']); ?></td>
                    <td><?php echo number_format($line['quantity'], 2); ?></td>
                    <td><?php echo htmlspecialchars($line['unit']); ?></td>
                    <td>₱<?php echo number_format($line['unit_price'], 2); ?></td>
                    <td>₱<?php echo number_format($line['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($invoice_journal)): ?>
    <h4 style="margin-top:1.5rem;">Accounting Entry</h4>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Date</th><th>Reference</th><th>Account</th><th>Cash</th></tr></thead>
            <tbody>
                <?php foreach ($invoice_journal as $line): ?>
                <?php if ((float) $line['debit'] <= 0) continue; ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($line['entry_date'])); ?></td>
                    <td><?php echo htmlspecialchars($line['description']); ?></td>
                    <td>Cash</td>
                    <td>₱<?php echo number_format($line['debit'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <h4 style="margin-top:1.5rem;">Payment History</h4>
    <p><span class="badge badge-approved">Paid</span></p>
    
    <!-- Record Payment -->
    <?php
        $remaining_balance = round((float) $invoice_details['amount'] - (float) ($invoice_details['paid_amount'] ?? 0), 2);
    ?>
    <?php if (canWriteDepartmentData('accounting') && !in_array($invoice_details['status'], ['draft', 'paid', 'cancelled'], true) && $remaining_balance > 0 && !ownsRecord($invoice_details['created_by'] ?? 0)): ?>
    <div style="margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid #e2e8f0;">
        <h4>Cash Payment</h4>
        <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;align-items:end;">
            <input type="hidden" name="invoice_id" value="<?php echo $invoice_details['id']; ?>">
            <input type="hidden" name="record_payment" value="1">
            <div class="form-group">
                <label>Invoice Number</label>
                <input type="text" value="<?php echo htmlspecialchars($invoice_details['invoice_number']); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Client Name</label>
                <input type="text" value="<?php echo htmlspecialchars(invoiceClientLabel($invoice_details)); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Invoice Amount</label>
                <input type="text" value="₱<?php echo number_format($invoice_details['amount'], 2); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Remaining Balance</label>
                <input type="text" value="₱<?php echo number_format($remaining_balance, 2); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Amount Paid</label>
                <input type="number" step="0.01" name="payment_amount" required min="0.01" max="<?php echo $remaining_balance; ?>">
            </div>
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="form-group">
                <label>Payment Reference</label>
                <input type="text" name="reference_number" placeholder="OR / receipt number">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <input type="text" value="CASH" disabled>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Record Cash Payment</button>
        </form>
    </div>
    <?php endif; ?>
    <?php if (canWriteDepartmentData('accounting') && !in_array($invoice_details['status'], ['cancelled', 'paid'], true)): ?>
    <form method="post" class="action-row" style="margin-top:1rem;">
        <input type="hidden" name="invoice_id" value="<?php echo (int) $invoice_details['id']; ?>">
        <button class="btn btn-danger" name="cancel_invoice" value="1">Cancel invoice</button>
    </form>
    <?php endif; ?>
    
    <div style="margin-top:1.5rem;">
        <a class="btn btn-outline" href="invoice_print.php?id=<?php echo (int) $invoice_details['id']; ?>"><i class="fas fa-file-pdf"></i> Download PDF</a>
        <?php if ($invoice_details['status'] !== 'paid' && $invoice_details['status'] !== 'cancelled'): ?>
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $invoice_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php endif; ?>
        <a href="invoices.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="invoiceTable">
            <thead>
                <tr>
                    <th data-sort-type="string" onclick="sortTable('invoiceTable', 0)">Invoice #</th>
                    <th data-sort-type="string" onclick="sortTable('invoiceTable', 1)">Quotation No.</th>
                    <th onclick="sortTable('invoiceTable', 2)">Client</th>
                    <th onclick="sortTable('invoiceTable', 3)">Project</th>
                    <th onclick="sortTable('invoiceTable', 4)">Amount</th>
                    <th onclick="sortTable('invoiceTable', 5)">Paid</th>
                    <th onclick="sortTable('invoiceTable', 6)">Status</th>
                    <th onclick="sortTable('invoiceTable', 7)">Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">No invoices found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['quotation_number'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars(($inv['client_label'] ?? '') !== '' && ($inv['client_label'] ?? '') !== null ? $inv['client_label'] : invoiceClientLabel($inv)); ?></td>
                        <td><?php echo htmlspecialchars($inv['project_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($inv['amount'], 2); ?></td>
                        <td>₱<?php echo number_format($inv['paid_amount'] ?? 0, 2); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($inv['status']); ?>"><?php echo htmlspecialchars(invoiceStatusLabel(invoiceDisplayStatus($inv))); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                            <a href="invoice_print.php?id=<?php echo (int) $inv['id']; ?>" class="btn btn-sm btn-outline" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                            <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
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

<script>
(function () {
    const projectSelect = document.getElementById('invoiceProject');
    const clientName = document.getElementById('invoiceClient');
    if (!projectSelect || !clientName) return;
    function applyProjectClient() {
        const wanted = (projectSelect.selectedOptions[0]?.dataset.client || '').trim().toLowerCase();
        if (!wanted) return;
        for (const option of clientName.options) {
            if ((option.dataset.name || '').trim().toLowerCase() === wanted) {
                clientName.value = option.value;
                return;
            }
        }
    }
    projectSelect.addEventListener('change', applyProjectClient);
    if (!clientName.value) applyProjectClient();

    const received = document.getElementById('receivedQuotation');
    const amount = document.getElementById('invoiceAmount');
    function applyReceivedQuotation() {
        const option = received?.selectedOptions[0];
        if (!option || !option.value) return;
        if (option.dataset.project) {
            projectSelect.value = option.dataset.project;
        }
        applyProjectClient();
        const wanted = (option.dataset.client || '').trim().toLowerCase();
        if (wanted) {
            for (const clientOption of clientName.options) {
                if ((clientOption.dataset.name || '').trim().toLowerCase() === wanted) {
                    clientName.value = clientOption.value;
                    break;
                }
            }
        }
        if (amount && option.dataset.amount !== '') {
            amount.value = option.dataset.amount;
        }
    }
    received?.addEventListener('change', applyReceivedQuotation);
})();
</script>
<?php include '../../includes/footer.php'; ?>