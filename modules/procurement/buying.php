<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/procurement_excel.php';
require_once '../../includes/quotation_file.php';
require_once '../../includes/report_export.php';

requireDepartment(['procurement']);
$page_title = 'Buying';
$requestId = (int) ($_GET['request'] ?? $_POST['request_id'] ?? 0);

if (isset($_GET['template']) && $requestId > 0) {
    $boq = procurementCurrentBoq($pdo, $requestId);
    if (!$boq) {
        $_SESSION['error'] = 'Upload the material Excel file before downloading the supplier template.';
        header('Location: buying.php?request=' . $requestId);
        exit();
    }
    $tmp = tempnam(sys_get_temp_dir(), 'boq');
    runehaWriteXlsx($tmp, procurementTemplateRows($boq['lines']), [4]);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="supplier-prices-' . (int) $requestId . '.xlsx"');
    readfile($tmp);
    unlink($tmp);
    exit();
}

if (isset($_GET['export']) && $requestId > 0) {
    $comparison = procurementComparison($pdo, $requestId);
    $header = ['Material code', 'Description', 'Unit', 'Quantity'];
    foreach ($comparison['uploads'] as $upload) {
        $header[] = trim((string) ($upload['company_name'] ?: $upload['name']));
    }
    $rows = [$header];
    foreach ($comparison['rows'] as $row) {
        $line = [$row['line']['material_code'], $row['line']['description'], $row['line']['unit'], (int) $row['line']['quantity']];
        foreach ($comparison['uploads'] as $upload) {
            $price = $row['cells'][(int) $upload['id']] ?? null;
            $line[] = $price === null ? 'No quote' : $price;
        }
        $rows[] = $line;
    }
    $total = ['', '', '', 'Total'];
    foreach ($comparison['uploads'] as $upload) {
        $total[] = $comparison['totals'][(int) $upload['id']] ?? 0;
    }
    $rows[] = $total;
    $tmp = tempnam(sys_get_temp_dir(), 'cmp');
    runehaWriteXlsx($tmp, $rows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="price-comparison-' . (int) $requestId . '.xlsx"');
    readfile($tmp);
    unlink($tmp);
    exit();
}

if (isset($_GET['pdf']) && $requestId > 0) {
    $comparison = procurementComparison($pdo, $requestId);
    $head = '<tr><th>Material</th><th>Qty</th>';
    foreach ($comparison['uploads'] as $upload) {
        $head .= '<th>' . htmlspecialchars(trim((string) ($upload['company_name'] ?: $upload['name']))) . '</th>';
    }
    $head .= '</tr>';
    $body = '';
    foreach ($comparison['rows'] as $row) {
        $body .= '<tr><td>' . htmlspecialchars($row['line']['material_code'] . ' — ' . $row['line']['description'] . ' (' . $row['line']['unit'] . ')') . '</td><td>' . (int) $row['line']['quantity'] . '</td>';
        foreach ($comparison['uploads'] as $upload) {
            $price = $row['cells'][(int) $upload['id']] ?? null;
            $body .= '<td>' . ($price === null ? 'No quote' : number_format($price, 2)) . '</td>';
        }
        $body .= '</tr>';
    }
    runehaStreamPdf('price-comparison-' . $requestId . '.pdf', '<h1>Price comparison</h1><table border="1" cellpadding="4" cellspacing="0">' . $head . $body . '</table>', 'landscape');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!canWriteDepartmentData('procurement')) {
            throw new RuntimeException('You cannot change procurement records.');
        }
        assertMonthOpen('procurement', date('Y-m-d'));
        if (isset($_POST['upload_boq']) || isset($_POST['confirm_boq']) || isset($_POST['upload_prices'])) {
            throw new RuntimeException('Materials, quantity, and price come from the project Excel and cannot be edited. Download the Excel to view them.');
        } elseif (isset($_POST['choose_supplier'])) {
            procurementChooseSupplier($pdo, $requestId, (int) ($_POST['supplier_id'] ?? 0), (int) $_SESSION['user_id']);
            $_SESSION['success'] = 'Supplier selected. The Excel materials, quantity, and price stay the same.';
        } elseif (isset($_POST['select_supplier'])) {
            procurementSelectSupplier($pdo, $requestId, (int) ($_POST['upload_id'] ?? 0), (int) $_SESSION['user_id']);
            $_SESSION['success'] = 'Supplier selected for the whole request.';
        } elseif (isset($_POST['create_po'])) {
            $pdo->beginTransaction();
            $created = procurementCreateOrderFromExcel($pdo, $requestId, (int) $_SESSION['user_id']);
            $pdo->commit();
            $_SESSION['success'] = $created['po_number'] . ' was created with Cash terms and sent to Accounting for ₱' . number_format($created['total'], 2) . '.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: buying.php?request=' . $requestId);
    exit();
}

$requests = $pdo->query("
    SELECT pr.id, pr.pr_number, pr.status, pr.project_id, p.project_code, p.name, p.status AS project_status
    FROM purchase_requests pr
    LEFT JOIN projects p ON p.id = pr.project_id
    WHERE pr.project_id IS NOT NULL
    ORDER BY pr.id DESC
")->fetchAll();
$current = null;
foreach ($requests as $request) {
    if ((int) $request['id'] === $requestId) {
        $current = $request;
    }
}
$requestLines = [];
$supplierChoice = null;
if ($requestId > 0) {
    $lineStmt = $pdo->prepare("SELECT pri.quantity, pri.unit, pri.estimated_cost, m.material_code, m.name
        FROM purchase_request_items pri
        LEFT JOIN materials m ON m.id = pri.material_id
        WHERE pri.purchase_request_id = ?
        ORDER BY pri.id");
    $lineStmt->execute([$requestId]);
    $requestLines = $lineStmt->fetchAll();
    $choiceStmt = $pdo->prepare("SELECT c.supplier_id, s.company_name, s.name
        FROM procurement_supplier_choice c
        LEFT JOIN suppliers s ON s.id = c.supplier_id
        WHERE c.purchase_request_id = ?");
    $choiceStmt->execute([$requestId]);
    $supplierChoice = $choiceStmt->fetch() ?: null;
}
$suppliers = $pdo->query("SELECT id, name, company_name, phone, email, address, tin_number FROM suppliers WHERE status = 'active' ORDER BY company_name, name")->fetchAll();
$canWrite = canWriteDepartmentData('procurement');
$hasPo = $requestId > 0 && procurementRequestHasPo($pdo, $requestId);

include '../../includes/header.php';
?>
<div class="page-header"><h1>Buying</h1></div>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<ol class="buy-steps">
    <li>Receive request</li>
    <li>Download the project Excel</li>
    <li>Select supplier</li>
    <li>Create PO and submit to Accounting</li>
</ol>
<div class="card">
    <form method="get">
        <label class="required">Material request</label>
        <select name="request" onchange="this.form.submit()">
            <option value="">Select a request</option>
            <?php foreach ($requests as $request): ?>
                <option value="<?php echo (int) $request['id']; ?>" <?php echo $requestId === (int) $request['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($request['pr_number'] . ' — ' . ($request['project_code'] ?? 'No project') . ' — ' . ($request['name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php if ($current): ?>
<?php $excelFile = projectQuotationAttachment($pdo, (int) $current['project_id']); ?>
<div class="card">
    <h3>Materials from Excel</h3>
    <p>Materials, quantity, and price come from the project Excel. They cannot be edited. Download the file to view them.</p>
    <p>
        <?php if ($excelFile): ?>
            <a class="btn btn-outline" href="<?php echo htmlspecialchars(projectQuotationDownloadUrl((int) $current['project_id'])); ?>"><i class="fas fa-file-excel"></i> Download Excel</a>
        <?php else: ?>
            <span class="text-muted">No Excel file is attached to this project.</span>
        <?php endif; ?>
    </p>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Material</th><th>Quantity</th><th>Unit</th><th>Price</th></tr></thead>
            <tbody>
            <?php if (!$requestLines): ?>
                <tr><td colspan="4">This request has no Excel materials.</td></tr>
            <?php endif; ?>
            <?php foreach ($requestLines as $line): ?>
                <tr>
                    <td><?php echo htmlspecialchars(trim(($line['material_code'] ? $line['material_code'] . ' — ' : '') . ($line['name'] ?? 'Material'))); ?></td>
                    <td><?php echo number_format((float) $line['quantity'], 2); ?></td>
                    <td><?php echo htmlspecialchars($line['unit'] ?: 'pcs'); ?></td>
                    <td>₱<?php echo number_format((float) $line['estimated_cost'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($canWrite && !$hasPo): ?>
<div class="card">
    <h3>Select supplier</h3>
    <p>Choosing a supplier does not change the Excel materials, quantity, or price.</p>
    <?php if ($supplierChoice): ?>
        <p>Selected supplier: <?php echo htmlspecialchars(trim((string) (($supplierChoice['company_name'] ?: '') !== '' ? $supplierChoice['company_name'] : $supplierChoice['name']))); ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
        <input type="hidden" name="choose_supplier" value="1">
        <div class="form-group">
            <label class="required">Active supplier</label>
            <select name="supplier_id" id="supplierPick" required>
                <option value="">Select a supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo (int) $supplier['id']; ?>" <?php echo (int) ($supplierChoice['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : ''; ?>
                        data-company="<?php echo htmlspecialchars($supplier['company_name'] ?: 'Company name not set'); ?>"
                        data-phone="<?php echo htmlspecialchars((string) $supplier['phone']); ?>"
                        data-email="<?php echo htmlspecialchars((string) $supplier['email']); ?>"
                        data-address="<?php echo htmlspecialchars((string) $supplier['address']); ?>"
                        data-tin="<?php echo htmlspecialchars((string) $supplier['tin_number']); ?>">
                        <?php echo htmlspecialchars(($supplier['company_name'] ?: 'Company name not set') . ' — ' . $supplier['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="supplierCard" class="detail-row">Company, phone, email, address, and TIN appear here and cannot be edited.</div>
        <button class="btn btn-primary" type="submit">Use this supplier</button>
    </form>
</div>
<div class="card">
    <h3>Create purchase order</h3>
    <p>Payment terms are Cash. Materials, quantity, and price stay the Excel values.</p>
    <form method="post">
        <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
        <input type="hidden" name="create_po" value="1">
        <label>Payment terms</label>
        <input type="text" value="Cash" readonly>
        <button class="btn btn-primary" type="submit" <?php echo $supplierChoice && $requestLines ? '' : 'disabled'; ?>>Create Purchase Order and submit to Accounting</button>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>
<style>
.buy-steps { display:flex; gap:0.6rem; flex-wrap:wrap; padding-left:1.2rem; font-size:14px; }
.compare-scroll { overflow:auto; max-height:70vh; }
.compare-table { font-size:14px; border-collapse:separate; }
.compare-table th, .compare-table td { min-width:140px; }
.compare-table thead th { position:sticky; top:0; background:#fff; z-index:2; }
.compare-table tbody th { position:sticky; left:0; background:#fff; z-index:1; min-width:240px; }
.lowest-cell { background:#dcfce7; font-weight:700; }
body.buy-full .main-content { padding:0.4rem; }
body.buy-full #sidebar { display:none; }
</style>
<script>
document.getElementById('supplierPick')?.addEventListener('change', function () {
    const option = this.selectedOptions[0];
    document.getElementById('supplierCard').textContent = this.value === ''
        ? 'Company, phone, email, address, and TIN appear here and cannot be edited.'
        : [option.dataset.company, option.dataset.phone, option.dataset.email, option.dataset.address, option.dataset.tin].filter(Boolean).join(' · ');
});
document.getElementById('fullScreen')?.addEventListener('click', function () {
    document.body.classList.toggle('buy-full');
    this.textContent = document.body.classList.contains('buy-full') ? 'Exit full screen' : 'Full screen';
});
</script>
<?php include '../../includes/footer.php'; ?>
