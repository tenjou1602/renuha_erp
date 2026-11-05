<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/adjustment_refs.php';

$adjustmentDepartment = $_GET['department'] ?? ($_SESSION['department'] ?? '');
$allowed = ['accounting', 'engineering', 'procurement', 'warehouse', 'admin'];
if (!in_array($adjustmentDepartment, $allowed, true)) {
    $adjustmentDepartment = 'admin';
}
if ($adjustmentDepartment === 'admin') {
    requireAdmin();
} elseif ($adjustmentDepartment === 'engineering') {
    requireDepartment(['engineering']);
} else {
    requireDepartment([$adjustmentDepartment]);
}

$page_title = 'Monthly Adjustments';
$canApprove = isAdmin() || (isManager() && (($_SESSION['department'] ?? '') === $adjustmentDepartment || $adjustmentDepartment === 'admin'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_adjustment'])) {
            if (!canWriteDepartmentData($adjustmentDepartment === 'engineering' ? 'projects' : ($adjustmentDepartment === 'admin' ? null : $adjustmentDepartment)) && !isAdmin()) {
                throw new RuntimeException('You cannot record an adjustment for this department.');
            }
            $reason = trim($_POST['reason'] ?? '');
            $period = trim($_POST['period'] ?? date('Y-m'));
            $type = trim($_POST['type'] ?? '');
            $referenceType = trim($_POST['reference_type'] ?? '');
            $referenceId = (int) ($_POST['reference_id'] ?? 0);
            $projectId = (int) ($_POST['project_id'] ?? 0);
            if (mb_strlen($reason) < 5 || !preg_match('/^\d{4}-\d{2}$/', $period)) {
                throw new RuntimeException('Choose a month and enter a reason of at least 5 characters.');
            }
            if (!in_array($type, adjustmentTypeOptions(), true)) {
                throw new RuntimeException('Choose an adjustment type.');
            }
            $allowedTypes = adjustmentReferenceTypesFor($adjustmentDepartment, isAdmin());
            if (!isset($allowedTypes[$referenceType])) {
                throw new RuntimeException('You cannot adjust that record type.');
            }
            $reference = adjustmentLoadReference($pdo, $referenceType, $referenceId);
            if (!$reference) {
                throw new RuntimeException('That reference does not exist. Choose a record from the list.');
            }
            if ($projectId > 0) {
                $projectCheck = $pdo->prepare("SELECT id, status FROM projects WHERE id = ?");
                $projectCheck->execute([$projectId]);
                $projectRow = $projectCheck->fetch();
                if (!$projectRow) {
                    throw new RuntimeException('That project does not exist.');
                }
                if ($projectRow['status'] === 'completed') {
                    throw new RuntimeException('Project completed, no further adjustments.');
                }
            } else {
                $projectId = (int) $reference['project_id'];
            }
            if ($projectId > 0) {
                $completedCheck = $pdo->prepare("SELECT status FROM projects WHERE id = ?");
                $completedCheck->execute([$projectId]);
                if ($completedCheck->fetchColumn() === 'completed') {
                    throw new RuntimeException('Project completed, no further adjustments.');
                }
            }
            $amountAfterRaw = trim((string) ($_POST['amount_after'] ?? ''));
            $qtyAfterRaw = trim((string) ($_POST['qty_after'] ?? ''));
            if ($amountAfterRaw !== '' && !is_numeric($amountAfterRaw)) {
                throw new RuntimeException('Amount after must be a number.');
            }
            if ($qtyAfterRaw !== '' && !is_numeric($qtyAfterRaw)) {
                throw new RuntimeException('Qty after must be a number.');
            }
            $needsAmount = in_array($type, ['Amount correction', 'Budget change'], true);
            $needsQty = in_array($type, ['Quantity correction', 'Stock count'], true);
            if ($needsAmount && $amountAfterRaw === '') {
                throw new RuntimeException('Enter the amount after the correction.');
            }
            if ($needsQty && $qtyAfterRaw === '') {
                throw new RuntimeException('Enter the quantity after the correction.');
            }
            if ($type === 'Other' && $amountAfterRaw === '' && $qtyAfterRaw === '') {
                throw new RuntimeException('Enter an amount after or a quantity after.');
            }
            [$period, $sourcePeriod] = resolveAdjustmentPeriod($adjustmentDepartment, $period);
            $before = $reference['amount'];
            $after = $amountAfterRaw === '' ? null : (float) $amountAfterRaw;
            $qtyBefore = $reference['qty'];
            $qtyAfter = $qtyAfterRaw === '' ? null : (float) $qtyAfterRaw;
            $difference = ($before !== null && $after !== null) ? $after - $before : (($qtyBefore !== null && $qtyAfter !== null) ? $qtyAfter - $qtyBefore : null);
            $pdo->prepare("INSERT INTO adjustments (department, period, source_period, type, reference_type, reference_id, project_id, amount_before, amount_after, qty_before, qty_after, difference, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $adjustmentDepartment,
                    $period,
                    $sourcePeriod,
                    $type,
                    $referenceType,
                    $referenceId,
                    $projectId > 0 ? $projectId : null,
                    $before,
                    $after,
                    $qtyBefore,
                    $qtyAfter,
                    $difference,
                    mb_substr($reason, 0, 500),
                    $_SESSION['user_id'],
                ]);
            writeAudit('Create adjustment', ucfirst($adjustmentDepartment), 'adjustments', (int) $pdo->lastInsertId(), null, ['period' => $period, 'reason' => $reason], $reason);
            $_SESSION['success'] = 'Adjustment submitted for approval. The original record was not changed.';
        }
        if (isset($_POST['decide_adjustment'])) {
            $id = (int) ($_POST['adjustment_id'] ?? 0);
            $decision = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
            $row = $pdo->prepare("SELECT * FROM adjustments WHERE id = ? AND department = ?");
            $row->execute([$id, $adjustmentDepartment]);
            $item = $row->fetch();
            if (!$item || $item['status'] !== 'pending') {
                throw new RuntimeException('That adjustment is not pending.');
            }
            if (!$canApprove) {
                throw new RuntimeException('Only a department manager or Admin can decide an adjustment.');
            }
            assertNotOwnApproval($item['created_by']);
            $pdo->prepare("UPDATE adjustments SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'")
                ->execute([$decision, $_SESSION['user_id'], $id]);
            logStatusChange('adjustments', $id, 'pending', $decision);
            writeAudit(ucfirst($decision) . ' adjustment', ucfirst($adjustmentDepartment), 'adjustments', $id, ['status' => 'pending'], ['status' => $decision], $item['reason']);
            $_SESSION['success'] = 'Adjustment ' . $decision . '. Reported figures use this record. The original transaction stays as it was.';
        }
        if (isset($_POST['close_month'])) {
            if (!$canApprove) {
                throw new RuntimeException('Only a department manager or Admin can close a month.');
            }
            $period = trim($_POST['period'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                throw new RuntimeException('Choose a month in YYYY-MM form.');
            }
            $pdo->prepare("INSERT INTO month_closures (department, period, closed_by) VALUES (?, ?, ?)")
                ->execute([$adjustmentDepartment, $period, $_SESSION['user_id']]);
            writeAudit('Close month', ucfirst($adjustmentDepartment), 'month_closures', (int) $pdo->lastInsertId(), null, ['period' => $period], null);
            $_SESSION['success'] = $period . ' is closed for ' . $adjustmentDepartment . '.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: index.php?department=' . urlencode($adjustmentDepartment));
    exit();
}

$rows = $pdo->prepare("SELECT a.*, u.full_name AS creator_name FROM adjustments a LEFT JOIN users u ON u.id = a.created_by WHERE a.department = ? ORDER BY a.id DESC");
$rows->execute([$adjustmentDepartment]);
$list = $rows->fetchAll();
$net = 0.0;
foreach ($list as $row) {
    if ($row['status'] === 'approved') {
        $net += (float) $row['difference'];
    }
}
$projectOptions = $pdo->query("SELECT id, project_code, name, status FROM projects WHERE archived_at IS NULL ORDER BY project_code")->fetchAll();
$referenceTypes = adjustmentReferenceTypesFor($adjustmentDepartment, isAdmin());

include '../../includes/header.php';
?>
<div class="page-header"><h1>Monthly Adjustments — <?php echo htmlspecialchars(ucfirst($adjustmentDepartment)); ?></h1></div>
<?php if (!empty($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<div class="card">
    <h3>New adjustment</h3>
    <p class="help-text">The original transaction stays unchanged. An approved adjustment is the correction that reports use.</p>
    <form method="post" class="adj-form" id="adjustmentForm">
        <div class="form-group">
            <label for="adjType">Type</label>
            <select name="type" id="adjType" required>
                <?php foreach (adjustmentTypeOptions() as $typeOption): ?>
                    <option value="<?php echo htmlspecialchars($typeOption); ?>"><?php echo htmlspecialchars($typeOption); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="adjRefType">Reference type</label>
            <select name="reference_type" id="adjRefType" required>
                <?php foreach ($referenceTypes as $typeKey => $typeLabel): ?>
                    <option value="<?php echo htmlspecialchars($typeKey); ?>"><?php echo htmlspecialchars($typeLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="adjProject">Project</label>
            <select name="project_id" id="adjProject">
                <option value="">No project</option>
                <?php foreach ($projectOptions as $projectOption): ?>
                    <?php if (($projectOption['status'] ?? '') === 'completed'): ?>
                        <option value="" disabled><?php echo htmlspecialchars($projectOption['project_code'] . ' — ' . $projectOption['name'] . ' — Project completed, no further adjustments'); ?></option>
                    <?php else: ?>
                        <option value="<?php echo (int) $projectOption['id']; ?>"><?php echo htmlspecialchars($projectOption['project_code'] . ' — ' . $projectOption['name']); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group span-2">
            <label for="adjRefSearch">Reference</label>
            <input type="search" id="adjRefSearch" placeholder="Search existing records" autocomplete="off">
            <div id="adjRefList" class="adj-ref-list" role="listbox" aria-label="Matching records"></div>
        </div>
        <div class="form-group">
            <label for="adjPeriod">Month</label>
            <input type="month" name="period" id="adjPeriod" value="<?php echo date('Y-m'); ?>" required>
        </div>
        <div class="form-group span-3">
            <label for="adjReference">Matching records</label>
            <select name="reference_id" id="adjReference" required>
                <option value="">Select a record</option>
            </select>
        </div>
        <div class="form-group">
            <label for="amountBefore">Amount before</label>
            <input type="text" id="amountBefore" readonly value="" placeholder="Filled from the record">
        </div>
        <div class="form-group">
            <label for="amountAfter">Amount after</label>
            <input type="number" step="0.01" name="amount_after" id="amountAfter">
        </div>
        <div class="form-group">
            <label for="qtyBefore">Qty before</label>
            <input type="text" id="qtyBefore" readonly value="" placeholder="Filled from the record">
        </div>
        <div class="form-group">
            <label for="qtyAfter">Qty after</label>
            <input type="number" step="0.01" name="qty_after" id="qtyAfter">
        </div>
        <div class="form-group span-3">
            <label for="adjReason">Reason</label>
            <textarea name="reason" id="adjReason" required minlength="5" rows="3" placeholder="At least 5 characters"></textarea>
            <span class="help-text">Explain what was wrong and what the corrected figure should be.</span>
        </div>
        <div class="span-3">
            <button class="btn btn-primary" name="add_adjustment" value="1" type="submit">Submit</button>
        </div>
    </form>
</div>
<div class="card">
    <h3>This department</h3>
    <p>Approved net difference: <strong><?php echo number_format($net, 2); ?></strong></p>
    <div class="adj-toolbar">
        <form method="post" class="adj-toolbar">
            <div class="form-group">
                <label for="closePeriod">Month to close</label>
                <input type="month" name="period" id="closePeriod" value="<?php echo date('Y-m'); ?>" required>
            </div>
            <button class="btn btn-warning" name="close_month" value="1">Close month</button>
        </form>
        <a class="btn btn-outline" href="export.php?department=<?php echo urlencode($adjustmentDepartment); ?>&format=excel">Excel</a>
        <a class="btn btn-outline" href="export.php?department=<?php echo urlencode($adjustmentDepartment); ?>&format=pdf">PDF</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Period</th><th>Type</th><th>Before</th><th>After</th><th>Difference</th><th>Reason</th><th>Status</th><th>By</th><th></th></tr></thead>
            <tbody>
            <?php if (!$list): ?>
                <tr><td colspan="9">No adjustments yet for <?php echo date('F Y'); ?>.</td></tr>
            <?php endif; ?>
            <?php foreach ($list as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['period']); ?></td>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td><?php echo htmlspecialchars((string) $row['amount_before']); ?></td>
                    <td><?php echo htmlspecialchars((string) $row['amount_after']); ?></td>
                    <td><?php echo htmlspecialchars((string) $row['difference']); ?></td>
                    <td><?php echo htmlspecialchars($row['reason']); ?></td>
                    <td><span class="badge badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($row['creator_name'] ?? ''); ?></td>
                    <td>
                        <?php if ($row['status'] === 'pending' && $canApprove && !ownsRecord($row['created_by'])): ?>
                        <form method="post" class="action-row">
                            <input type="hidden" name="adjustment_id" value="<?php echo (int) $row['id']; ?>">
                            <button class="btn btn-sm btn-success" name="decide_adjustment" value="1" onclick="this.form.decision.value='approved'">Approve</button>
                            <button class="btn btn-sm btn-danger" name="decide_adjustment" value="1" onclick="this.form.decision.value='rejected'">Reject</button>
                            <input type="hidden" name="decision" value="approved">
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var typeSelect = document.getElementById('adjRefType');
    var search = document.getElementById('adjRefSearch');
    var reference = document.getElementById('adjReference');
    var project = document.getElementById('adjProject');
    var amountBefore = document.getElementById('amountBefore');
    var qtyBefore = document.getElementById('qtyBefore');
    var resultList = document.getElementById('adjRefList');
    var records = [];
    var timer = null;
    function applySelected() {
        var selected = records.find(function (row) { return String(row.id) === reference.value; });
        amountBefore.value = selected && selected.amount !== null && selected.amount !== undefined ? selected.amount : '';
        qtyBefore.value = selected && selected.qty !== null && selected.qty !== undefined ? selected.qty : '';
        if (selected && selected.project_id) {
            project.value = String(selected.project_id);
        }
        Array.prototype.forEach.call(resultList.querySelectorAll('button'), function (button) {
            button.classList.toggle('active', button.getAttribute('data-id') === reference.value);
        });
    }
    function paint(rows) {
        records = Array.isArray(rows) ? rows : [];
        var previous = reference.value;
        reference.innerHTML = '<option value="">Select a record</option>';
        resultList.innerHTML = '';
        if (!records.length) {
            var empty = document.createElement('div');
            empty.className = 'adj-ref-empty';
            empty.textContent = 'No matching records.';
            resultList.appendChild(empty);
        }
        records.forEach(function (row) {
            var label = row.label || ('Record ' + row.id);
            var option = document.createElement('option');
            option.value = row.id;
            option.textContent = label;
            reference.appendChild(option);
            var button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('data-id', String(row.id));
            button.textContent = label;
            button.addEventListener('click', function () {
                reference.value = String(row.id);
                search.value = label;
                applySelected();
                resultList.classList.remove('open');
            });
            resultList.appendChild(button);
        });
        if (previous && records.some(function (row) { return String(row.id) === previous; })) {
            reference.value = previous;
        } else {
            reference.value = '';
            amountBefore.value = '';
            qtyBefore.value = '';
        }
        applySelected();
    }
    function load() {
        var params = new URLSearchParams({
            department: <?php echo json_encode($adjustmentDepartment); ?>,
            reference_type: typeSelect.value,
            q: search.value || ''
        });
        fetch('lookup.php?' + params.toString(), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (rows) {
                if (rows && rows.error) {
                    paint([]);
                    return;
                }
                paint(rows);
            })
            .catch(function () { paint([]); });
    }
    function openList() {
        resultList.classList.add('open');
    }
    typeSelect.addEventListener('change', function () {
        search.value = '';
        load();
    });
    search.addEventListener('click', openList);
    search.addEventListener('focus', openList);
    search.addEventListener('input', function () {
        openList();
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });
    document.addEventListener('click', function (event) {
        if (event.target === search || resultList.contains(event.target)) {
            return;
        }
        resultList.classList.remove('open');
    });
    reference.addEventListener('change', applySelected);
    project.addEventListener('change', function () {
        if (typeSelect.value === 'project' && project.value) {
            reference.value = project.value;
            applySelected();
            var chosen = resultList.querySelector('button.active');
            if (chosen) {
                chosen.scrollIntoView({ block: 'nearest' });
            }
        }
    });
    load();
})();
</script>
<?php include '../../includes/footer.php'; ?>
