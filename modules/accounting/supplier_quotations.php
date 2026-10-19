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
        if ($quotation) {
            $lineStmt = $pdo->prepare("
                SELECT qi.quantity, qi.unit, qi.unit_price, m.material_code, m.name AS material_name
                FROM quotation_items qi
                LEFT JOIN materials m ON m.id = qi.material_id
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
                   p.project_code, p.name AS project_name,
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
            WHERE q.submitted_to_accounting_at IS NOT NULL
            ORDER BY q.submitted_to_accounting_at DESC
        ")->fetchAll();
    }
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Accounting');
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
        <div class="detail-row"><span>Quotation #:</span> <strong><?php echo htmlspecialchars($quotation['quotation_number']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars(trim(($quotation['project_code'] ?? '') . ' ' . ($quotation['project_name'] ?? '')) ?: '—'); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($quotation['supplier']); ?></div>
        <div class="detail-row"><span>Company Name:</span> <?php echo htmlspecialchars($quotation['supplier_contact'] ?? '') !== '' ? htmlspecialchars($quotation['supplier_contact']) : 'Company name not set'; ?></div>
        <div class="detail-row"><span>Status:</span> <?php echo ucfirst($quotation['status']); ?></div>
        <div class="detail-row"><span>Sent:</span> <?php echo date('M d, Y h:i A', strtotime($quotation['submitted_to_accounting_at'])); ?></div>
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
                        <td><?php echo htmlspecialchars(trim(($line['material_code'] ?? '') . ' ' . ($line['material_name'] ?? '')) ?: '—'); ?></td>
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
        <div class="detail-row"><span>Payment:</span> <?php echo $payment_status === 'paid' ? 'Paid' . ($paid_po_number !== '' ? ' · ' . htmlspecialchars($paid_po_number) : '') : 'Pending'; ?></div>
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
                    <td><?php echo htmlspecialchars($row['supplier_contact'] ?? '') !== '' ? htmlspecialchars($row['supplier_contact']) : 'Company name not set'; ?></td>
                    <td>₱<?php echo number_format((float) $row['total_amount'], 2); ?></td>
                    <td>
                        <?php
                        $previewStmt = $pdo->prepare("SELECT m.name, qi.quantity, qi.unit FROM quotation_items qi LEFT JOIN materials m ON m.id = qi.material_id WHERE qi.quotation_id = ? ORDER BY qi.id LIMIT 3");
                        $previewStmt->execute([(int) $row['id']]);
                        $preview = $previewStmt->fetchAll();
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM quotation_items WHERE quotation_id = ?");
                        $countStmt->execute([(int) $row['id']]);
                        $lineCount = (int) $countStmt->fetchColumn();
                        foreach ($preview as $previewLine) {
                            echo '<div style="font-size:0.8rem;color:#64748b;">' . htmlspecialchars(($previewLine['name'] ?: 'Material') . ' × ' . (int) $previewLine['quantity'] . ' ' . ($previewLine['unit'] ?? '')) . '</div>';
                        }
                        if ($lineCount > count($preview)) {
                            echo '<div style="font-size:0.8rem;color:#94a3b8;">+' . ($lineCount - count($preview)) . ' more</div>';
                        }
                        ?>
                    </td>
                    <td><?php echo ($row['expense_status'] ?? '') === 'paid' ? 'Paid' : 'Pending'; ?><?php if (!empty($row['po_number'])): ?><div style="font-size:0.8rem;color:#64748b;"><?php echo htmlspecialchars($row['po_number']); ?></div><?php endif; ?></td>
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
