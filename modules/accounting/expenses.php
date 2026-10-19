<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Expenses';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

function generateExpenseNumber() {
    return generateNumber('EXP', 'expenses', 'expense_number');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_expense']) || isset($_POST['update_expense'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: ' . basename(__FILE__));
        exit();
    }
    if (isset($_POST['add_expense']) || isset($_POST['update_expense'])) {
        $expense_number = $_POST['expense_number'] ?? generateExpenseNumber();
        $project_id = (int)($_POST['project_id'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
        $payment_method = trim($_POST['payment_method'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');
        
        $errors = [];
        if ($project_id <= 0) $errors[] = 'Project is required.';
        if (empty($category)) $errors[] = 'Category is required.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_expense'])) {
                    $stmt = $pdo->prepare("INSERT INTO expenses (expense_number, project_id, category, description, amount, expense_date, payment_method, reference_number, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$expense_number, $project_id, $category, $description, $amount, $expense_date, $payment_method, $reference_number, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Created expense', 'Accounting', "EXP: $expense_number");
                    $_SESSION['success'] = "Expense created successfully!";
                } else {
                    $stmt = $pdo->prepare("UPDATE expenses SET project_id = ?, category = ?, description = ?, amount = ?, expense_date = ?, payment_method = ?, reference_number = ? WHERE id = ?");
                    $stmt->execute([$project_id, $category, $description, $amount, $expense_date, $payment_method, $reference_number, $id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated expense', 'Accounting', "EXP: $expense_number");
                    $_SESSION['success'] = "Expense updated successfully!";
                }
                header('Location: expenses.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: expenses.php');
        exit();
    }
    $delete_id = (int) $_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$delete_id]);
        logActivity($_SESSION['user_id'], 'Deleted expense', 'Accounting', "Expense ID: $delete_id");
        $_SESSION['success'] = 'Expense deleted.';
    } catch (PDOException $e) {
        $_SESSION['error'] = userDatabaseError($e);
    }
    header('Location: expenses.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    if (!canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Only Accounting can mark an expense as paid.';
        header('Location: expenses.php');
        exit();
    }
    $paidId = (int) ($_POST['expense_id'] ?? 0);
    $method = trim($_POST['payment_method'] ?? '');
    $reference = trim($_POST['reference_number'] ?? '');
    $stmt = $pdo->prepare("UPDATE expenses SET status = 'paid', paid_by = ?, paid_at = NOW(), payment_method = COALESCE(NULLIF(?, ''), payment_method), reference_number = COALESCE(NULLIF(?, ''), reference_number) WHERE id = ? AND status = 'pending'");
    $stmt->execute([(int) $_SESSION['user_id'], $method, $reference, $paidId]);
    if ($stmt->rowCount() === 1) {
        releasePaidOrderToWarehouse($pdo, $paidId);
        $_SESSION['success'] = 'Expense marked as paid. Warehouse can now receive the materials on this purchase order.';
    } else {
        $_SESSION['error'] = 'Only a pending expense can be marked as paid.';
    }
    header('Location: expenses.php?action=view&id=' . $paidId);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen_expense'])) {
    if (!isManager() || ($_SESSION['department'] ?? '') !== 'accounting') {
        $_SESSION['error'] = 'Only an Accounting manager can return a paid expense to pending.';
        header('Location: expenses.php');
        exit();
    }
    $paidId = (int) ($_POST['expense_id'] ?? 0);
    $reason = trim($_POST['status_note'] ?? '');
    if ($reason === '') {
        $_SESSION['error'] = 'A reason is required to return a paid expense to pending.';
        header('Location: expenses.php?action=view&id=' . $paidId);
        exit();
    }
    $stmt = $pdo->prepare("UPDATE expenses SET status = 'pending', status_note = ?, paid_by = NULL, paid_at = NULL WHERE id = ? AND status = 'paid'");
    $stmt->execute([mb_substr($reason, 0, 500), $paidId]);
    if ($stmt->rowCount() === 1) {
        holdUnreceivedOrderForPayment($pdo, $paidId);
    }
    logActivity($_SESSION['user_id'], 'Reopened expense', 'Accounting', 'Expense ' . $paidId . ': ' . $reason);
    $_SESSION['success'] = $stmt->rowCount() === 1 ? 'Expense returned to pending.' : 'That expense is not paid.';
    header('Location: expenses.php?action=view&id=' . $paidId);
    exit();
}

syncPurchasedMaterialsToAccounting($pdo);
$status_filter = $_GET['status'] ?? '';
if (!in_array($status_filter, ['pending', 'paid', 'cancelled'], true)) {
    $status_filter = '';
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, name, project_code FROM projects ORDER BY name")->fetchAll();

// Get expenses
$expenses = [];
try {
    $query = "
        SELECT e.*, p.name as project_name, u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN projects p ON e.project_id = p.id
        LEFT JOIN users u ON e.created_by = u.id
        " . ($status_filter !== '' ? "WHERE e.status = " . $pdo->quote($status_filter) : '') . "
        ORDER BY e.created_at DESC
    ";
    $expenses = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single expense
$expense_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT e.*, p.name as project_name, u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN projects p ON e.project_id = p.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $expense_details = $stmt->fetch();
}


if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: ' . basename(__FILE__) . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-coins"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Expense</a><?php endif; ?>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Expense</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_expense" value="1">
            <input type="hidden" name="expense_number" value="<?php echo htmlspecialchars($expense_details['expense_number']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_expense" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Expense Number</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($expense_details['expense_number']) : generateExpenseNumber(); ?>" disabled class="readonly-field">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="expense_number" value="<?php echo generateExpenseNumber(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" <?php echo ($expense_details['project_id'] ?? 0) == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <?php 
                    $categories = ['Office Supplies', 'Utilities', 'Salaries', 'Transportation', 'Maintenance', 'Insurance', 'Taxes', 'Marketing', 'Rent', 'Other'];
                    foreach ($categories as $cat):
                    ?>
                        <option value="<?php echo $cat; ?>" <?php echo ($expense_details['category'] ?? '') == $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="required">Amount (₱)</label>
                <input type="number" step="0.01" name="amount" required value="<?php echo $expense_details['amount'] ?? 0; ?>" min="0">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Expense Date</label>
                <input type="date" name="expense_date" value="<?php echo $expense_details['expense_date'] ?? date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="">Select</option>
                    <option value="Cash" <?php echo ($expense_details['payment_method'] ?? '') == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank Transfer" <?php echo ($expense_details['payment_method'] ?? '') == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="Credit Card" <?php echo ($expense_details['payment_method'] ?? '') == 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="Check" <?php echo ($expense_details['payment_method'] ?? '') == 'Check' ? 'selected' : ''; ?>>Check</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reference #</label>
                <input type="text" name="reference_number" value="<?php echo htmlspecialchars($expense_details['reference_number'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"><?php echo htmlspecialchars($expense_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Expense</button>
            <a href="expenses.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $expense_details): ?>
<!-- View Expense -->
<div class="card">
    <h3>Expense Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Expense #:</span> <strong><?php echo htmlspecialchars($expense_details['expense_number']); ?></strong></div>
        <div class="detail-row"><span>Category:</span> <?php echo htmlspecialchars($expense_details['category']); ?></div>
        <div class="detail-row"><span>Amount:</span> <strong>₱<?php echo number_format($expense_details['amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($expense_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $expense_details['status'] === 'paid' ? 'success' : ($expense_details['status'] === 'pending' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($expense_details['status']); ?></span></div>
        <?php if (!empty($expense_details['paid_at'])): ?>
        <div class="detail-row"><span>Paid at:</span> <?php echo date('M d, Y h:i A', strtotime($expense_details['paid_at'])); ?></div>
        <?php endif; ?>
        <?php if (!empty($expense_details['status_note'])): ?>
        <div class="detail-row"><span>Status note:</span> <?php echo htmlspecialchars($expense_details['status_note']); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Expense Date:</span> <?php echo date('M d, Y', strtotime($expense_details['expense_date'])); ?></div>
        <div class="detail-row"><span>Payment Method:</span> <?php echo htmlspecialchars($expense_details['payment_method'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Reference #:</span> <?php echo htmlspecialchars($expense_details['reference_number'] ?? 'N/A'); ?></div>
        <?php if (!empty($expense_details['purchase_order_id'])): ?>
            <?php $payLines = purchaseOrderMaterialsByOrder($pdo)[(int) $expense_details['purchase_order_id']] ?? []; ?>
            <?php if ($payLines): ?>
            <div class="table-responsive" style="margin-top:1rem;">
                <table class="table">
                    <thead><tr><th>Material on <?php echo htmlspecialchars($expense_details['reference_number'] ?: 'this PO'); ?></th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($payLines as $line): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($line['material_name'] ?: 'Material'); ?></td>
                            <td><?php echo (int) $line['quantity']; ?> <?php echo htmlspecialchars($line['unit'] ?? ''); ?></td>
                            <td>₱<?php echo number_format((float) $line['unit_price'], 2); ?></td>
                            <td>₱<?php echo number_format((float) $line['quantity'] * (float) $line['unit_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($expense_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($expense_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($expense_details['created_at'])); ?></div>
    </div>
    <div class="table-actions">
        <?php if ($expense_details['status'] === 'pending' && canWriteDepartmentData('accounting')): ?>
            <a href="?action=edit&id=<?php echo $expense_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this expense as paid? This records you as the person who paid it.');">
                <input type="hidden" name="mark_paid" value="1">
                <input type="hidden" name="expense_id" value="<?php echo (int) $expense_details['id']; ?>">
                <button type="submit" class="btn btn-success">Mark as Paid</button>
            </form>
        <?php elseif ($expense_details['status'] === 'paid' && isManager() && ($_SESSION['department'] ?? '') === 'accounting'): ?>
            <form method="POST" style="display:inline-flex;gap:.4rem;align-items:center;" onsubmit="return confirm('Return this paid expense to pending?');">
                <input type="hidden" name="reopen_expense" value="1">
                <input type="hidden" name="expense_id" value="<?php echo (int) $expense_details['id']; ?>">
                <input type="text" name="status_note" required placeholder="Reason" style="max-width:220px;">
                <button type="submit" class="btn btn-warning">Return to Pending</button>
            </form>
        <?php endif; ?>
        <a href="expenses.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <form method="GET" style="margin-bottom:.8rem;display:flex;gap:.5rem;align-items:center;">
        <label for="statusFilter">Status</label>
        <select id="statusFilter" name="status" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach (['pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label): ?>
            <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="table-responsive">
        <table class="table" id="expenseTable">
            <thead>
                <tr>
                    <th data-sort-type="string" onclick="sortTable('expenseTable', 0)">Expense #</th>
                    <th onclick="sortTable('expenseTable', 1)">Category</th>
                    <th onclick="sortTable('expenseTable', 2)">Project</th>
                    <th onclick="sortTable('expenseTable', 3)">Amount</th>
                    <th onclick="sortTable('expenseTable', 4)">Status</th>
                    <th onclick="sortTable('expenseTable', 5)">Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="7" class="table-empty">No expenses found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($exp['expense_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($exp['category']); ?></td>
                        <td><?php echo htmlspecialchars($exp['project_name'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($exp['amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $exp['status'] === 'paid' ? 'success' : ($exp['status'] === 'pending' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($exp['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($exp['created_at'])); ?></td>
                        <td>
                            <a href="?action=view&id=<?php echo $exp['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if ($exp['status'] === 'pending'): ?>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $exp['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
                            <?php endif; ?>
                            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=delete&delete_id=<?php echo (int) $exp['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?')"><i class="fas fa-trash"></i></a><?php endif; ?>
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