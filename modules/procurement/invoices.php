<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/accounting_ledger.php';

requireDepartment(['procurement']);

$page_title = 'Invoices';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);
if ($action !== 'view') {
    $action = 'list';
}

$invoices = [];
try {
    $invoices = $pdo->query("
        SELECT i.*, p.name AS project_name, p.project_code, u.full_name AS created_by_name,
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
    ")->fetchAll();
    $invoices = array_map('invoiceWithAccountingPayment', $invoices);
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

$invoice_details = null;
$invoice_payments = [];
$invoice_items = [];
if ($action === 'view') {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, p.name AS project_name, p.project_code, u.full_name AS created_by_name,
                   cq.quotation_number AS client_quotation_number,
                   pq.quotation_number AS procurement_quotation_number,
                   pq.id AS procurement_quote_id,
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
        if ($invoice_details) {
            $invoice_details = invoiceWithAccountingPayment($invoice_details);
        }
        if (!$invoice_details) {
            $error = 'Invoice not found.';
            $action = 'list';
        } else {
            $payments_stmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY created_at DESC");
            $payments_stmt->execute([$id]);
            $invoice_payments = $payments_stmt->fetchAll();
            $item_stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
            $item_stmt->execute([$id]);
            $invoice_items = $item_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
        $action = 'list';
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> <?php echo $page_title; ?></h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'view' && $invoice_details): ?>
<div class="card">
    <h3>Invoice Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Invoice #:</span> <strong><?php echo htmlspecialchars($invoice_details['invoice_number']); ?></strong></div>
        <div class="detail-row"><span>Client Name:</span> <?php echo htmlspecialchars($invoice_details['client_label'] ?: invoiceClientLabel($invoice_details)); ?></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars(trim(($invoice_details['project_code'] ?? '') . ' ' . ($invoice_details['project_name'] ?? '')) ?: 'N/A'); ?></div>
        <?php if (!empty($invoice_details['procurement_quotation_number'])): ?>
            <div class="detail-row"><span>Quotation No.:</span> <a href="quotations.php?action=view&id=<?php echo (int) $invoice_details['procurement_quote_id']; ?>"><?php echo htmlspecialchars($invoice_details['procurement_quotation_number']); ?></a></div>
        <?php elseif (!empty($invoice_details['client_quotation_number'])): ?>
            <div class="detail-row"><span>Quotation No.:</span> <?php echo htmlspecialchars($invoice_details['client_quotation_number']); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Invoice Amount:</span> <strong>₱<?php echo number_format((float) $invoice_details['amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Amount Paid:</span> ₱<?php echo number_format((float) ($invoice_details['paid_amount'] ?? 0), 2); ?></div>
        <div class="detail-row"><span>Remaining Balance:</span> <strong>₱<?php echo number_format((float) $invoice_details['amount'] - (float) ($invoice_details['paid_amount'] ?? 0), 2); ?></strong></div>
        <?php if (!empty($invoice_details['accounting_expenses'])): ?>
            <div class="detail-row"><span>Accounting payment:</span> <?php echo htmlspecialchars($invoice_details['accounting_expenses']); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo htmlspecialchars($invoice_details['status']); ?>"><?php echo htmlspecialchars(invoiceStatusLabel(invoiceDisplayStatus($invoice_details))); ?></span></div>
        <div class="detail-row"><span>Invoice Date:</span> <?php echo date('M d, Y', strtotime($invoice_details['invoice_date'])); ?></div>
        <div class="detail-row"><span>Due Date:</span> <?php echo $invoice_details['due_date'] ? date('M d, Y', strtotime($invoice_details['due_date'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($invoice_details['created_by_name'] ?? 'N/A'); ?></div>
        <?php if (!empty($invoice_details['notes'])): ?>
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
                    <td><?php echo htmlspecialchars(ucfirst((string) $line['line_type'])); ?></td>
                    <td><?php echo htmlspecialchars($line['description']); ?></td>
                    <td><?php echo number_format((float) $line['quantity'], 2); ?></td>
                    <td><?php echo htmlspecialchars($line['unit']); ?></td>
                    <td>₱<?php echo number_format((float) $line['unit_price'], 2); ?></td>
                    <td>₱<?php echo number_format((float) $line['line_total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <h4 style="margin-top:1.5rem;">Payment History</h4>
    <?php if (empty($invoice_payments)): ?>
        <p style="color:#94a3b8;">No payments recorded yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Payment #</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_payments as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                        <td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div style="margin-top:1.5rem;">
        <a class="btn btn-outline" href="../accounting/invoice_print.php?id=<?php echo (int) $invoice_details['id']; ?>">Print / PDF</a>
        <a href="invoices.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table" id="invoiceTable">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Quotation No.</th>
                    <th>Client</th>
                    <th>Project</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Status</th>
                    <th>Date</th>
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
                    <?php
                        $clientLabel = trim((string) ($inv['client_label'] ?? ''));
                        if ($clientLabel === '') {
                            $clientLabel = invoiceClientLabel($inv);
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['quotation_number'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($clientLabel); ?></td>
                        <td><?php echo htmlspecialchars($inv['project_code'] ?? $inv['project_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format((float) $inv['amount'], 2); ?></td>
                        <td>₱<?php echo number_format((float) ($inv['paid_amount'] ?? 0), 2); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($inv['status']); ?>"><?php echo htmlspecialchars(invoiceStatusLabel(invoiceDisplayStatus($inv))); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo (int) $inv['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                            <a href="../accounting/invoice_print.php?id=<?php echo (int) $inv['id']; ?>" class="btn btn-sm btn-outline" title="Print / PDF"><i class="fas fa-file-pdf"></i></a>
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
