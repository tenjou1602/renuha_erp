<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

function laborRoleCatalog(): array
{
    return [
        'engineer' => 'Engineer',
        'architect' => 'Architect',
        'foreman' => 'Foreman',
        'skilled' => 'Skilled Workers',
        'helper' => 'Construction Helpers',
        'other' => 'Other Personnel',
    ];
}

function laborSaveLines(PDO $pdo, int $budgetId, array $lines): void
{
    $pdo->prepare("DELETE FROM labor_budget_lines WHERE labor_budget_id = ?")->execute([$budgetId]);
    $stmt = $pdo->prepare("INSERT INTO labor_budget_lines (labor_budget_id, role_key, role_name, headcount, wage, assigned_count, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($lines as $line) {
        $stmt->execute([$budgetId, $line['role_key'], $line['role_name'], $line['headcount'], $line['wage'], $line['assigned_count'], $line['line_total']]);
    }
}

function laborFundMessage(PDO $pdo, int $projectId, float $payroll, float $laborLimit, bool $updated): string
{
    $fundStmt = $pdo->prepare("SELECT fund_name, amount, allocated_amount FROM funds WHERE project_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $fundStmt->execute([$projectId]);
    $fund = $fundStmt->fetch();
    $base = $updated ? 'Labor budget updated.' : 'Labor budget created.';
    if (!$fund) {
        return $base . ' Create a fund in Funds & Budget Management to connect the wages.';
    }
    $available = (float) $fund['amount'] - (float) $fund['allocated_amount'];
    $text = $base . ' Allocated ₱' . number_format($payroll, 2) . ' to fund ' . $fund['fund_name'] . '. Available funds: ₱' . number_format($available, 2) . '.';
    if ($payroll > $laborLimit) {
        $text .= ' Over the quotation labor allowance by ₱' . number_format($payroll - $laborLimit, 2) . '. The client needs an additional payment.';
    }
    return $text;
}

$page_title = 'Labor/Salary Budget';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_budget']) || isset($_POST['update_budget'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: labor_budget.php');
        exit();
    }
    
    if (isset($_POST['add_budget']) || isset($_POST['update_budget'])) {
        $budget_name = trim($_POST['budget_name'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $period_start = $_POST['period_start'] ?? null;
        $period_end = $_POST['period_end'] ?? null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $roles = laborRoleCatalog();
        $lines = [];
        $errors = [];
        if (empty($budget_name)) $errors[] = 'Budget name is required.';
        foreach ($roles as $key => $label) {
            $row = $_POST['crew'][$key] ?? [];
            $headcount = max(0, (int) ($row['headcount'] ?? 0));
            $wage = max(0, (float) ($row['wage'] ?? 0));
            $assigned = max(0, (int) ($row['assigned'] ?? 0));
            $roleName = $key === 'other' ? trim((string) ($row['name'] ?? '')) : $label;
            if ($headcount === 0 && $wage <= 0 && $assigned === 0) {
                continue;
            }
            if ($key === 'other' && $roleName === '') {
                $roleName = 'Other Personnel';
            }
            if ($headcount <= 0) {
                $errors[] = $label . ': enter how many to hire.';
            }
            if ($wage <= 0) {
                $errors[] = $label . ': enter the wage.';
            }
            if ($assigned <= 0) {
                $errors[] = $label . ': enter how many to assign.';
            } elseif ($assigned > $headcount) {
                $errors[] = $label . ': assigned cannot be more than the number to hire.';
            }
            $lines[] = [
                'role_key' => $key,
                'role_name' => $roleName,
                'headcount' => $headcount,
                'wage' => round($wage, 2),
                'assigned_count' => $assigned,
                'line_total' => round($assigned * $wage, 2),
            ];
        }
        $payroll_total = round(array_sum(array_column($lines, 'line_total')), 2);
        $allocated_amount = $payroll_total;
        if ($project_id <= 0) {
            $errors[] = 'Select the project for this labor budget.';
        }
        if ($payroll_total <= 0) {
            $errors[] = 'Enter at least one role with a wage and how many to assign.';
        }
        $position = implode(', ', array_map(static function ($line) {
            return $line['role_name'] . ' x' . $line['assigned_count'];
        }, $lines));
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_budget'])) {
                    $stmt = $pdo->prepare("INSERT INTO labor_budgets (budget_name, project_id, user_id, position, allocated_amount, used_amount, period_start, period_end, description, status, created_by) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$budget_name, $project_id ?: null, $position, $allocated_amount, $payroll_total, $period_start ?: null, $period_end ?: null, $description, $status, $_SESSION['user_id']]);
                    $budgetId = (int) $pdo->lastInsertId();
                    laborSaveLines($pdo, $budgetId, $lines);
                    syncProjectFundsFromLabor($pdo, $project_id);
                    logActivity($_SESSION['user_id'], 'Created labor budget', 'Accounting', "Budget: $budget_name");
                    $_SESSION['success'] = laborFundMessage($pdo, $project_id, $payroll_total, $allocated_amount, false);
                } else {
                    $oldProjectStmt = $pdo->prepare("SELECT project_id FROM labor_budgets WHERE id = ?");
                    $oldProjectStmt->execute([$id]);
                    $oldProjectId = (int) $oldProjectStmt->fetchColumn();
                    $stmt = $pdo->prepare("UPDATE labor_budgets SET budget_name = ?, project_id = ?, position = ?, allocated_amount = ?, used_amount = ?, period_start = ?, period_end = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$budget_name, $project_id ?: null, $position, $allocated_amount, $payroll_total, $period_start ?: null, $period_end ?: null, $description, $status, $id]);
                    laborSaveLines($pdo, $id, $lines);
                    syncProjectFundsFromLabor($pdo, $project_id);
                    if ($oldProjectId > 0 && $oldProjectId !== $project_id) {
                        syncProjectFundsFromLabor($pdo, $oldProjectId);
                    }
                    logActivity($_SESSION['user_id'], 'Updated labor budget', 'Accounting', "Budget: $budget_name");
                    $_SESSION['success'] = laborFundMessage($pdo, $project_id, $payroll_total, $allocated_amount, true);
                }
                header('Location: labor_budget.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        } else {
            $error = implode(' ', $errors);
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canDelete('accounting')) {
        $_SESSION['error'] = 'Administrators cannot delete labor budget records.';
        header('Location: labor_budget.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $projectStmt = $pdo->prepare("SELECT project_id FROM labor_budgets WHERE id = ?");
        $projectStmt->execute([$delete_id]);
        $deletedProjectId = (int) $projectStmt->fetchColumn();
        $stmt = $pdo->prepare("DELETE FROM labor_budgets WHERE id = ?");
        $stmt->execute([$delete_id]);
        if ($deletedProjectId > 0) {
            syncProjectFundsFromLabor($pdo, $deletedProjectId);
        }
        $_SESSION['success'] = "Labor budget deleted successfully!";
        header('Location: labor_budget.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Check if labor_budgets table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM labor_budgets LIMIT 1");
} catch (PDOException $e) {
    // Create labor_budgets table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS labor_budgets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            budget_name VARCHAR(200) NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            position VARCHAR(150) NULL,
            allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            used_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            period_start DATE NULL,
            period_end DATE NULL,
            description TEXT NULL,
            status ENUM('active','inactive','completed') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_labor_budgets_project (project_id),
            KEY idx_labor_budgets_user (user_id),
            KEY idx_labor_budgets_creator (created_by),
            CONSTRAINT fk_labor_budgets_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_labor_budgets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_labor_budgets_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS labor_budget_lines (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        labor_budget_id BIGINT UNSIGNED NOT NULL,
        role_key VARCHAR(40) NOT NULL,
        role_name VARCHAR(150) NOT NULL,
        headcount INT UNSIGNED NOT NULL DEFAULT 0,
        wage DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        assigned_count INT UNSIGNED NOT NULL DEFAULT 0,
        line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        KEY idx_lbl_budget (labor_budget_id),
        CONSTRAINT fk_lbl_budget FOREIGN KEY (labor_budget_id) REFERENCES labor_budgets (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    error_log('[Runeha ERP] labor_budget_lines: ' . $e->getMessage());
}

// Get labor budgets
$labor_budgets = [];
try {
    $query = "
        SELECT lb.*, p.project_code, p.name as project_name, u.full_name as user_name, u.department as user_department,
               COALESCE(wages.planned_wages, 0) AS planned_wages,
               ub.full_name as created_by_name
        FROM labor_budgets lb
        LEFT JOIN projects p ON lb.project_id = p.id
        LEFT JOIN users u ON lb.user_id = u.id
        LEFT JOIN users ub ON lb.created_by = ub.id
        LEFT JOIN (
            SELECT labor_budget_id, COALESCE(SUM(line_total), 0) AS planned_wages
            FROM labor_budget_lines
            GROUP BY labor_budget_id
        ) wages ON wages.labor_budget_id = lb.id
        ORDER BY lb.created_at DESC
    ";
    $labor_budgets = $pdo->query($query)->fetchAll();
    foreach ($labor_budgets as &$budgetRow) {
        $planned = (float) ($budgetRow['planned_wages'] ?? 0);
        $budgetRow['used_amount'] = $planned;
        $budgetRow['remaining_amount'] = (float) $budgetRow['allocated_amount'] - $planned;
    }
    unset($budgetRow);
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name, estimated_budget FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();
$project_funds = [];
try {
    foreach ($pdo->query("SELECT id, fund_name, amount, project_id FROM funds WHERE status = 'active' AND project_id IS NOT NULL ORDER BY id") as $fundRow) {
        $project_funds[(int) $fundRow['project_id']] = $fundRow;
    }
} catch (PDOException $e) {
    $project_funds = [];
}
$prefill_project = (int) ($_GET['project'] ?? 0);
$from_project = null;
foreach ($projects as $project) {
    if ((int) $project['id'] === $prefill_project) {
        $from_project = $project;
        break;
    }
}

// Get users for dropdown
$users = $pdo->query("SELECT id, full_name, department FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();

// Get single budget for edit/view
$budget_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT lb.*, p.project_code, p.name as project_name, p.estimated_budget, u.full_name as user_name, u.department as user_department,
               (lb.allocated_amount - lb.used_amount) as remaining_amount,
               ub.full_name as created_by_name
        FROM labor_budgets lb
        LEFT JOIN projects p ON lb.project_id = p.id
        LEFT JOIN users u ON lb.user_id = u.id
        LEFT JOIN users ub ON lb.created_by = ub.id
        WHERE lb.id = ?
    ");
    $stmt->execute([$id]);
    $budget_details = $stmt->fetch();
    
    if (!$budget_details) {
        header('Location: labor_budget.php');
        exit();
    }
}
$crew_lines = [];
if ($id > 0) {
    try {
        $lineStmt = $pdo->prepare("SELECT * FROM labor_budget_lines WHERE labor_budget_id = ?");
        $lineStmt->execute([$id]);
        foreach ($lineStmt->fetchAll() as $line) {
            $crew_lines[$line['role_key']] = $line;
        }
    } catch (PDOException $e) {
        $crew_lines = [];
    }
}
$quote_labor = (float) (is_array($budget_details) ? ($budget_details['allocated_amount'] ?? 0) : 0);

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: labor_budget.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<style>
.labor-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.labor-stats .card {
    border-left: 4px solid #4e73df;
}
.budget-remaining { color: #1cc88a; font-weight: 700; }
.budget-used { color: #f6c23e; font-weight: 700; }
.budget-over { color: #e74a3b; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-users"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Labor Budget</a><?php endif; ?>
        </div>
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

<!-- Quick Stats -->
<div class="labor-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Budgets</div>
        <div style="font-size:1.5rem;font-weight:700;"><?php echo count($labor_budgets); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Allocated</div>
        <div class="stat-amount">₱<?php echo number_format(array_sum(array_column($labor_budgets, 'allocated_amount')), 2); ?></div>
        <div style="font-size:0.7rem;color:#94a3b8;">Quotation labor</div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Planned Wages</div>
        <div class="stat-amount">₱<?php echo number_format(array_sum(array_column($labor_budgets, 'used_amount')), 2); ?></div>
        <div style="font-size:0.7rem;color:#94a3b8;">Assigned × wage</div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Remaining</div>
        <div class="stat-amount">₱<?php echo number_format(array_sum(array_column($labor_budgets, 'remaining_amount')), 2); ?></div>
        <div style="font-size:0.7rem;color:#94a3b8;">Quotation labor minus planned wages</div>
    </div>
</div>

<?php if ($action === 'list'): ?>
<div class="card">
    <h3><i class="fas fa-chart-bar"></i> Labor / Salary Budget</h3>
    <canvas id="laborChart" height="90"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('laborChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(static fn($b) => $b['budget_name'], array_slice($labor_budgets, 0, 8))); ?>,
        datasets: [
            { label: 'Allocated', data: <?php echo json_encode(array_map(static fn($b) => (float) $b['allocated_amount'], array_slice($labor_budgets, 0, 8))); ?>, backgroundColor: '#4e73df' },
            { label: 'Planned wages', data: <?php echo json_encode(array_map(static fn($b) => (float) $b['used_amount'], array_slice($labor_budgets, 0, 8))); ?>, backgroundColor: '#f6c23e' }
        ]
    },
    options: { scales: { y: { beginAtZero: true } } }
});
</script>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Labor Budget</h3>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_budget" value="1">
        <?php else: ?>
            <input type="hidden" name="add_budget" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Budget Name</label>
                <input type="text" name="budget_name" required value="<?php echo htmlspecialchars($budget_details['budget_name'] ?? ($from_project ? ($from_project['name'] . ' labor') : '')); ?>" placeholder="Enter budget name">
            </div>
            <div class="form-group">
                <label>Project (Optional)</label>
                <select name="project_id" id="laborProject">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>"
                            data-budget="<?php echo htmlspecialchars($project['estimated_budget'] ?? 0); ?>"
                            data-name="<?php echo htmlspecialchars($project['name'] ?? ''); ?>"
                            data-fund="<?php echo htmlspecialchars($project_funds[(int) $project['id']]['fund_name'] ?? ''); ?>"
                            data-fund-amount="<?php echo htmlspecialchars($project_funds[(int) $project['id']]['amount'] ?? 0); ?>"
                            data-fund-id="<?php echo (int) ($project_funds[(int) $project['id']]['id'] ?? 0); ?>"
                            <?php echo (int) ($budget_details['project_id'] ?? $prefill_project) === (int) $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>The project estimated budget stays on the project. This page only plans wages.</small>
                <small id="laborFundNote" style="display:block;margin-top:0.35rem;"></small>
            </div>
        </div>

        <h4 style="margin:1rem 0 0.5rem;">Personnel wages</h4>
        <p style="color:#64748b;margin-top:0;">Enter how many people to hire, the wage, and how many to assign. Planned wages are the labor budget. They are not included in actual cost.</p>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>To hire</th>
                        <th>Wage (₱)</th>
                        <th>To assign</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (laborRoleCatalog() as $key => $label):
                        $saved = $crew_lines[$key] ?? [];
                    ?>
                    <tr class="crew-row">
                        <td>
                            <?php if ($key === 'other'): ?>
                                <input type="text" name="crew[other][name]" value="<?php echo htmlspecialchars($saved['role_name'] ?? ''); ?>" placeholder="Other personnel">
                            <?php else: ?>
                                <strong><?php echo htmlspecialchars($label); ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><input type="number" min="0" step="1" name="crew[<?php echo $key; ?>][headcount]" data-field="headcount" value="<?php echo (int) ($saved['headcount'] ?? 0); ?>" style="width:90px;"></td>
                        <td><input type="number" min="0" step="0.01" name="crew[<?php echo $key; ?>][wage]" data-field="wage" value="<?php echo htmlspecialchars($saved['wage'] ?? 0); ?>" style="width:120px;"></td>
                        <td><input type="number" min="0" step="1" name="crew[<?php echo $key; ?>][assigned]" data-field="assigned" value="<?php echo (int) ($saved['assigned_count'] ?? 0); ?>" style="width:90px;"></td>
                        <td class="line-total">₱<?php echo number_format((float) ($saved['line_total'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Allocated Amount (₱)</label>
                <input type="number" step="0.01" id="allocatedAmount" name="allocated_amount" readonly value="<?php echo htmlspecialchars($quote_labor); ?>" style="background:#eff6ff;font-weight:700;color:#1d4ed8;">
                <small>This is the planned wages total. It is not taken from the project and is not actual cost.</small>
            </div>
            <div class="form-group">
                <label>Planned wages (₱)</label>
                <input type="text" id="plannedWages" readonly value="0.00" style="background:#f8fafc;font-weight:700;">
                <small id="laborLimitNote">Compare this with the planned wage total.</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Period Start</label>
                <input type="date" name="period_start" value="<?php echo htmlspecialchars($budget_details['period_start'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Period End</label>
                <input type="date" name="period_end" value="<?php echo htmlspecialchars($budget_details['period_end'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($budget_details['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($budget_details['status'] ?? 'active') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="completed" <?php echo ($budget_details['status'] ?? 'active') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Budget description"><?php echo htmlspecialchars($budget_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_budget' : 'update_budget'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Budget</button>
            <a href="labor_budget.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $budget_details): ?>
<!-- View Budget -->
<div class="card">
    <h3>Labor Budget Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Budget Name:</span> <strong><?php echo htmlspecialchars($budget_details['budget_name']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($budget_details['project_name'] ?? 'N/A'); ?></div>
        <?php $viewFund = $project_funds[(int) ($budget_details['project_id'] ?? 0)] ?? null; ?>
        <?php if ($viewFund): ?>
        <div class="detail-row"><span>Fund:</span> <a href="funds_budget.php?action=view&id=<?php echo (int) $viewFund['id']; ?>"><?php echo htmlspecialchars($viewFund['fund_name']); ?></a></div>
        <?php endif; ?>
        <div class="detail-row"><span>Personnel:</span> <?php echo htmlspecialchars($budget_details['position'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Allocated Amount:</span> <strong>₱<?php echo number_format((float) ($budget_details['allocated_amount'] ?? 0), 2); ?></strong></div>
        <?php
            $plannedWages = 0.0;
            foreach ($crew_lines as $line) {
                $plannedWages += (float) $line['line_total'];
            }
            $laborLimit = (float) ($budget_details['allocated_amount'] ?? 0);
            $laborGap = $plannedWages - $laborLimit;
        ?>
        <?php if ($crew_lines): ?>
        <?php if ($laborGap > 0.009): ?>
        <div class="alert alert-danger">Planned wages exceed the allocated amount by ₱<?php echo number_format($laborGap, 2); ?>.</div>
        <?php endif; ?>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>Role</th><th>To hire</th><th>Wage</th><th>To assign</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($crew_lines as $line): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($line['role_name']); ?></td>
                        <td><?php echo (int) $line['headcount']; ?></td>
                        <td>₱<?php echo number_format((float) $line['wage'], 2); ?></td>
                        <td><?php echo (int) $line['assigned_count']; ?></td>
                        <td>₱<?php echo number_format((float) $line['line_total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="detail-row"><span>Planned wages:</span> <strong class="budget-used">₱<?php echo number_format($plannedWages, 2); ?></strong></div>
        <div class="detail-row"><span>Remaining:</span> <strong class="<?php echo ($laborLimit - $plannedWages) >= 0 ? 'budget-remaining' : 'budget-over'; ?>">₱<?php echo number_format($laborLimit - $plannedWages, 2); ?></strong></div>
        <div class="detail-row"><span>Period Start:</span> <?php echo $budget_details['period_start'] ? date('M d, Y', strtotime($budget_details['period_start'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Period End:</span> <?php echo $budget_details['period_end'] ? date('M d, Y', strtotime($budget_details['period_end'])) : 'N/A'; ?></div>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($budget_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $budget_details['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($budget_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($budget_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($budget_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $budget_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $budget_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this labor budget?')"><i class="fas fa-trash"></i> Delete</a><?php endif; ?>
        <a href="labor_budget.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Labor Budgets (<?php echo count($labor_budgets); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchBudget" placeholder="Search budgets..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterStatus" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="completed">Completed</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="budgetTable">
            <thead>
                <tr>
                    <th onclick="sortTable('budgetTable', 0)" style="cursor:pointer;">Budget Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 1)" style="cursor:pointer;">Project <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 2)" style="cursor:pointer;">Employee <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 3)" style="cursor:pointer;">Position <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 4)" style="cursor:pointer;">Allocated <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 5)" style="cursor:pointer;">Planned Wages <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 6)" style="cursor:pointer;">Remaining <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('budgetTable', 7)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($labor_budgets)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No labor budgets found. Click "New Labor Budget" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($labor_budgets as $budget): ?>
                    <tr data-status="<?php echo htmlspecialchars($budget['status']); ?>">
                        <td><strong><?php echo htmlspecialchars($budget['budget_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($budget['project_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($budget['user_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($budget['position'] ?? 'N/A'); ?></td>
                        <td>₱<?php echo number_format($budget['allocated_amount'], 2); ?></td>
                        <td class="budget-used">₱<?php echo number_format($budget['used_amount'], 2); ?></td>
                        <td class="<?php echo $budget['remaining_amount'] >= 0 ? 'budget-remaining' : 'budget-over'; ?>">₱<?php echo number_format($budget['remaining_amount'], 2); ?></td>
                        <td><span class="badge badge-<?php echo $budget['status'] == 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($budget['status']); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $budget['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this labor budget?')"><i class="fas fa-trash"></i></a><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchBudget')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('#budgetTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.getAttribute('data-status') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = statusFilter === '' || status === statusFilter;
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
});

// Status filter
document.getElementById('filterStatus')?.addEventListener('change', function() {
    const statusFilter = this.value;
    const searchTerm = document.getElementById('searchBudget').value.toLowerCase();
    const rows = document.querySelectorAll('#budgetTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const status = row.getAttribute('data-status') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesStatus = statusFilter === '' || status === statusFilter;
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
<script>
document.getElementById('laborProject')?.addEventListener('change', function () {
    const option = this.selectedOptions[0];
    const amount = document.getElementById('allocatedAmount');
    const note = document.getElementById('laborFundNote');
    if (!option || !option.value) {
        if (amount) amount.value = '0.00';
        if (note) note.textContent = '';
        refreshLaborTotal();
        return;
    }
    if (option.dataset.name && !this.form.elements.budget_name.value) {
        this.form.elements.budget_name.value = option.dataset.name + ' labor';
    }
    if (amount && !parseFloat(amount.value)) amount.value = '0.00';
    if (note) {
        const money = (value) => '₱' + Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        note.textContent = '';
        if (option.dataset.fundId && option.dataset.fundId !== '0') {
            note.append('Connected to fund ');
            const link = document.createElement('a');
            link.href = 'funds_budget.php?action=view&id=' + option.dataset.fundId;
            link.textContent = option.dataset.fund || 'fund';
            note.append(link);
            note.append('. Total ' + money(option.dataset.fundAmount) + '. Planned wages stay on the labor budget and are not actual cost.');
        } else {
            note.append('This project does not have a fund yet. ');
            const link = document.createElement('a');
            link.href = 'funds_budget.php?action=add&project=' + option.value;
            link.textContent = 'Create a fund';
            note.append(link);
            note.append(' to connect the wages.');
        }
    }
    refreshLaborTotal();
});
if (document.getElementById('laborProject')?.value) {
    document.getElementById('laborProject').dispatchEvent(new Event('change'));
}
function refreshLaborTotal() {
    let total = 0;
    document.querySelectorAll('.crew-row').forEach((row) => {
        const wage = parseFloat(row.querySelector('[data-field="wage"]').value) || 0;
        const assigned = parseInt(row.querySelector('[data-field="assigned"]').value, 10) || 0;
        const line = wage * assigned;
        total += line;
        const cell = row.querySelector('.line-total');
        if (cell) cell.textContent = '₱' + line.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    });
    const planned = document.getElementById('plannedWages');
    const amount = document.getElementById('allocatedAmount');
    const note = document.getElementById('laborLimitNote');
    if (planned) planned.value = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (amount) amount.value = total.toFixed(2);
    if (!note) return;
    note.textContent = 'Planned wages are saved as the allocated amount. They are not added to project actual cost.';
    note.style.color = '#047857';
}
document.querySelectorAll('.crew-row input').forEach((input) => input.addEventListener('input', refreshLaborTotal));
refreshLaborTotal();
</script>
