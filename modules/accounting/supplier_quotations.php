<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Supplier Quotations';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_quotation_paid'])) {
    if (!canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Only Accounting can mark these materials as paid.';
    } else {
        try {
            $_SESSION['success'] = paySubmittedQuotation($pdo, (int) ($_POST['quotation_id'] ?? 0), (int) $_SESSION['user_id']);
        } catch (Throwable $e) {
            $_SESSION['error'] = $e instanceof PDOException ? userDatabaseError($e, 'Accounting') : $e->getMessage();
        }
    }
    $backId = (int) ($_POST['quotation_id'] ?? 0);
    header('Location: supplier_quotations.php' . ($backId > 0 ? '?action=view&id=' . $backId : ''));
    exit();
}

$quotations = [];
$quotation = null;
$lines = [];
$payment_status = '';
$paid_po_number = '';
$error = null;
try {
    if ($action === 'view' && $id > 0) {
        $stmt = $pdo->prepare("
            SELECT q.*, p.project_code, p.name AS project_name, u.full_name AS created_by_name
            FROM quotations q
            LEFT JOIN projects p ON p.id = q.project_id
            LEFT JOIN users u ON u.id = q.created_by
            WHERE q.id = ? AND q.submitted_to_accounting_at IS NOT NULL
        ");
        $stmt->execute([$id]);
        $quotation = $stmt->fetch() ?: null;
        if ($quotation && !empty($quotation['supplier'])) {
            $supplierStmt = $pdo->prepare("SELECT id, company_name FROM suppliers WHERE name = ? LIMIT 1");
            $supplierStmt->execute([$quotation['supplier']]);
            $supplierMatch = $supplierStmt->fetch() ?: null;
            if ($supplierMatch) {
                $quotation['supplier_id'] = $supplierMatch['id'];
                $quotation['company_name'] = $supplierMatch['company_name'];
            }
        }
        if ($quotation) {
            $lineStmt = $pdo->prepare("
                SELECT qi.id, qi.quantity, qi.unit, qi.unit_price, m.material_code, m.name AS material_name, m.description, pri.remarks
                FROM quotation_items qi
                LEFT JOIN materials m ON m.id = qi.material_id
                LEFT JOIN purchase_request_items pri ON pri.id = qi.purchase_request_item_id
                WHERE qi.quotation_id = ?
                ORDER BY qi.id
            ");
            $lineStmt->execute([$id]);
            $lines = $lineStmt->fetchAll();
            if (!empty($quotation['purchase_request_id'])) {
                $payStmt = $pdo->prepare("
                    SELECT po.po_number, e.status AS expense_status
                    FROM purchase_orders po
                    LEFT JOIN expenses e ON e.purchase_order_id = po.id
                    WHERE po.purchase_request_id = ? AND po.status <> 'cancelled'
                    ORDER BY po.id DESC
                    LIMIT 1
                ");
                $payStmt->execute([(int) $quotation['purchase_request_id']]);
                $payRow = $payStmt->fetch() ?: [];
                $payment_status = $payRow['expense_status'] ?? '';
                $paid_po_number = $payRow['po_number'] ?? '';
            }
        }
    } else {
        $quotations = $pdo->query("
            SELECT q.id, q.quotation_number, q.purchase_request_id, q.supplier, q.supplier_contact, q.total_amount, q.status, q.submitted_to_accounting_at,
                   p.project_code, p.name AS project_name, s.id AS supplier_id, s.company_name,
                   (
                       SELECT e.status
                       FROM purchase_orders po
                       LEFT JOIN expenses e ON e.purchase_order_id = po.id
                       WHERE po.purchase_request_id = q.purchase_request_id AND po.status <> 'cancelled'
                       ORDER BY po.id DESC
                       LIMIT 1
                   ) AS expense_status,
                   (
                       SELECT po.po_number
                       FROM purchase_orders po
                       WHERE po.purchase_request_id = q.purchase_request_id AND po.status <> 'cancelled'
                       ORDER BY po.id DESC
                       LIMIT 1
                   ) AS po_number
            FROM quotations q
            LEFT JOIN projects p ON p.id = q.project_id
            LEFT JOIN suppliers s ON s.name = q.supplier
            WHERE q.submitted_to_accounting_at IS NOT NULL
            ORDER BY q.submitted_to_accounting_at DESC
        ")->fetchAll();
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Accounting');
}

function quotationLineLabel(array $line): string
{
    $code = trim((string) ($line['material_code'] ?? ''));
    $name = trim((string) ($line['material_name'] ?? ''));
    $description = trim((string) ($line['description'] ?? ''));
    $remarks = trim((string) ($line['remarks'] ?? ''));
    $label = $code;
    if ($name !== '') {
        $label .= ($label !== '' ? ' — ' : '') . $name;
    }
    if ($description !== '' && strcasecmp($description, $name) !== 0) {
        $label .= ($label !== '' ? ' — ' : '') . $description;
    } elseif ($remarks !== '' && strcasecmp($remarks, $name) !== 0) {
        $label .= ($label !== '' ? ' — ' : '') . $remarks;
    }
    return $label !== '' ? $label : 'Material';
}

function quotationCompanyHtml(array $row): string
{
    $company = trim((string) ($row['supplier_contact'] ?? ''));
    if ($company === '') {
        $company = trim((string) ($row['company_name'] ?? ''));
    }
    if ($company !== '') {
        return '<span class="kv-value">' . htmlspecialchars($company) . '</span>';
    }
    $html = '<span class="kv-warn">Company name not set</span>';
    if (!empty($row['supplier_id']) && (isAdmin() || canViewModule('procurement'))) {
        $html .= ' <a href="' . APP_URL . 'modules/procurement/suppliers.php?action=edit&id=' . (int) $row['supplier_id'] . '">Edit supplier</a>';
    }
    return $html;
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Supplier Quotations</h1>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<p style="color:#64748b;">Quotations Procurement sends here include their materials. Mark as Paid records the payment and sends that purchase order to Warehouse for receiving.</p>

<?php if ($action === 'view'): ?>
<div class="card">
    <?php if (!$quotation): ?>
        <p>This quotation has not been sent to Accounting.</p>
    <?php else: ?>
        <div class="kv-grid">
            <div><span class="kv-label">Quotation #</span><strong class="kv-value"><?php echo htmlspecialchars($quotation['quotation_number']); ?></strong></div>
            <div><span class="kv-label">Project</span><span class="kv-value"><?php echo htmlspecialchars(trim(($quotation['project_code'] ?? '') . ' ' . ($quotation['project_name'] ?? '')) ?: '—'); ?></span></div>
            <div><span class="kv-label">Supplier</span><span class="kv-value"><?php echo htmlspecialchars($quotation['supplier']); ?></span></div>
            <div><span class="kv-label">Company Name</span><?php echo quotationCompanyHtml($quotation); ?></div>
            <div><span class="kv-label">Status</span><span class="badge badge-<?php echo htmlspecialchars($quotation['status']); ?>"><?php echo htmlspecialchars(ucfirst($quotation['status'])); ?></span></div>
            <div><span class="kv-label">Sent</span><span class="kv-value"><?php echo date('M d, Y h:i A', strtotime($quotation['submitted_to_accounting_at'])); ?></span></div>
        </div>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead>
                    <tr><th>Material</th><th>Qty</th><th>Unit</th><th>Unit price</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <?php if (!$lines): ?>
                        <tr><td colspan="5">No priced lines.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(quotationLineLabel($line)); ?></td>
                        <td><?php echo number_format((float) $line['quantity']); ?></td>
                        <td><?php echo htmlspecialchars($line['unit']); ?></td>
                        <td>₱<?php echo number_format((float) $line['unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $line['quantity'] * (float) $line['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="detail-row"><span>Supplier total:</span> <strong>₱<?php echo number_format((float) $quotation['total_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Payment:</span> <?php echo $payment_status === 'paid' ? 'Paid' . ($paid_po_number !== '' ? ' · ' . htmlspecialchars($paid_po_number) : '') : 'Pending'; ?><?php echo $payment_status === 'paid' ? '' : pendingWaitBadge($quotation['submitted_to_accounting_at'] ?? ''); ?></div>
        <?php if ($payment_status !== 'paid' && canWriteDepartmentData('accounting')): ?>
            <form method="POST" style="margin-top:1rem;" onsubmit="return confirm('Mark these materials as paid and send them to Warehouse?');">
                <input type="hidden" name="mark_quotation_paid" value="1">
                <input type="hidden" name="quotation_id" value="<?php echo (int) $quotation['id']; ?>">
                <button type="submit" class="btn btn-success">Mark as Paid</button>
            </form>
        <?php elseif ($payment_status === 'paid'): ?>
            <p style="color:#166534;">Warehouse can receive <?php echo htmlspecialchars($paid_po_number); ?> from Receiving.</p>
        <?php endif; ?>
    <?php endif; ?>
    <a href="supplier_quotations.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Quotation</th>
                    <th>Project</th>
                    <th>Supplier</th>
                    <th>Company Name</th>
                    <th>Total</th>
                    <th>Materials</th>
                    <th>Payment</th>
                    <th>Sent</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$quotations): ?>
                    <tr><td colspan="9">No supplier quotations have been sent to Accounting yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($quotations as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['quotation_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars(trim(($row['project_code'] ?? '') . ' ' . ($row['project_name'] ?? '')) ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($row['supplier']); ?></td>
                    <td><?php echo quotationCompanyHtml($row); ?></td>
                    <td>₱<?php echo number_format((float) $row['total_amount'], 2); ?></td>
                    <td>
                        <?php
                        $previewStmt = $pdo->prepare("SELECT m.material_code, m.name AS material_name, m.description, pri.remarks, qi.quantity, qi.unit FROM quotation_items qi LEFT JOIN materials m ON m.id = qi.material_id LEFT JOIN purchase_request_items pri ON pri.id = qi.purchase_request_item_id WHERE qi.quotation_id = ? ORDER BY qi.id LIMIT 3");
                        $previewStmt->execute([(int) $row['id']]);
                        $preview = $previewStmt->fetchAll();
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM quotation_items WHERE quotation_id = ?");
                        $countStmt->execute([(int) $row['id']]);
                        $lineCount = (int) $countStmt->fetchColumn();
                        foreach ($preview as $previewLine) {
                            echo '<div class="help-text">' . htmlspecialchars(quotationLineLabel($previewLine) . ' × ' . (int) $previewLine['quantity'] . ' ' . ($previewLine['unit'] ?? '')) . '</div>';
                        }
                        if ($lineCount > count($preview)) {
                            echo '<div style="font-size:0.8rem;color:#94a3b8;">+' . ($lineCount - count($preview)) . ' more</div>';
                        }
                        ?>
                    </td>
                    <td><?php echo ($row['expense_status'] ?? '') === 'paid' ? 'Paid' : 'Pending'; ?><?php echo ($row['expense_status'] ?? '') === 'paid' ? '' : pendingWaitBadge($row['submitted_to_accounting_at'] ?? ''); ?><?php if (!empty($row['po_number'])): ?><div style="font-size:0.8rem;color:#64748b;"><?php echo htmlspecialchars($row['po_number']); ?></div><?php endif; ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($row['submitted_to_accounting_at'])); ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="supplier_quotations.php?action=view&id=<?php echo (int) $row['id']; ?>">View</a>
                        <?php if (($row['expense_status'] ?? '') !== 'paid' && canWriteDepartmentData('accounting')): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark these materials as paid and send them to Warehouse?');">
                            <input type="hidden" name="mark_quotation_paid" value="1">
                            <input type="hidden" name="quotation_id" value="<?php echo (int) $row['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-success">Mark as Paid</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
