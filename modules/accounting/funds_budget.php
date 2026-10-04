<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireDepartment(['accounting']);

$page_title = 'Funds & Budget Management';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((isset($_POST['add_fund']) || isset($_POST['update_fund'])) && !canWriteDepartmentData('accounting')) {
        $_SESSION['error'] = 'Administrators have view-only access.';
        header('Location: funds_budget.php');
        exit();
    }
    
    if (isset($_POST['add_fund']) || isset($_POST['update_fund'])) {
        $fund_name = trim($_POST['fund_name'] ?? '');
        $fund_type = $_POST['fund_type'] ?? 'general';
        $amount = (float)($_POST['amount'] ?? 0);
        $project_id = (int)($_POST['project_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if ($project_id > 0) {
            $projectStmt = $pdo->prepare("SELECT project_code, name, estimated_budget FROM projects WHERE id = ?");
            $projectStmt->execute([$project_id]);
            $linkedProject = $projectStmt->fetch();
            if ($linkedProject) {
                if ($fund_name === '') {
                    $fund_name = $linkedProject['project_code'] . ' — ' . $linkedProject['name'];
                }
                if ((float) $linkedProject['estimated_budget'] > 0) {
                    $amount = round((float) $linkedProject['estimated_budget'], 2);
                }
                if ($fund_type === 'general') {
                    $fund_type = 'project';
                }
            }
        }
        $errors = [];
        if (empty($fund_name)) $errors[] = 'Fund name is required.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than 0.';
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_fund'])) {
                    $stmt = $pdo->prepare("INSERT INTO funds (fund_name, fund_type, amount, project_id, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$fund_name, $fund_type, $amount, $project_id, $description, $status, $_SESSION['user_id']]);
                    if ($project_id > 0) {
                        syncProjectFundsFromLabor($pdo, $project_id);
                    }
                    logActivity($_SESSION['user_id'], 'Created fund', 'Accounting', "Fund: $fund_name");
                    $_SESSION['success'] = "Fund created successfully!";
                } else {
                    $oldFundStmt = $pdo->prepare("SELECT project_id FROM funds WHERE id = ?");
                    $oldFundStmt->execute([$id]);
                    $oldFundProject = (int) $oldFundStmt->fetchColumn();
                    $stmt = $pdo->prepare("UPDATE funds SET fund_name = ?, fund_type = ?, amount = ?, project_id = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$fund_name, $fund_type, $amount, $project_id, $description, $status, $id]);
                    if ($project_id > 0) {
                        syncProjectFundsFromLabor($pdo, $project_id);
                    }
                    if ($oldFundProject > 0 && $oldFundProject !== $project_id) {
                        syncProjectFundsFromLabor($pdo, $oldFundProject);
                    }
                    logActivity($_SESSION['user_id'], 'Updated fund', 'Accounting', "Fund: $fund_name");
                    $_SESSION['success'] = "Fund updated successfully!";
                }
                header('Location: funds_budget.php');
                exit();
            } catch (PDOException $e) {
                $error = userDatabaseError($e);
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canDelete('accounting')) {
        $_SESSION['error'] = 'Administrators cannot delete fund records.';
        header('Location: funds_budget.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM funds WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "Fund deleted successfully!";
        header('Location: funds_budget.php');
        exit();
    } catch (PDOException $e) {
        $error = userDatabaseError($e);
    }
}

// Check if funds table exists, if not create it
try {
    $pdo->query("SELECT 1 FROM funds LIMIT 1");
} catch (PDOException $e) {
    // Create funds table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS funds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fund_name VARCHAR(200) NOT NULL,
            fund_type ENUM('general','project','reserve','emergency') NOT NULL DEFAULT 'general',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            project_id BIGINT UNSIGNED NULL,
            description TEXT NULL,
            status ENUM('active','inactive','frozen') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_funds_project (project_id),
            KEY idx_funds_creator (created_by),
            CONSTRAINT fk_funds_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_funds_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// Get funds
syncPurchasedMaterialsToAccounting($pdo);
$funds = [];
try {
    $query = "
        SELECT f.*, p.project_code, p.name as project_name, u.full_name as created_by_name
        FROM funds f
        LEFT JOIN projects p ON f.project_id = p.id
        LEFT JOIN users u ON f.created_by = u.id
        ORDER BY f.created_at DESC
    ";
    $funds = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get projects for dropdown
$projects = $pdo->query("SELECT id, project_code, name, estimated_budget, material_cost, labor_cost FROM projects WHERE status != 'completed' ORDER BY name")->fetchAll();
$prefill_project = (int) ($_GET['project'] ?? 0);

// Get single fund for edit/view
$fund_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("
        SELECT f.*, p.project_code, p.name as project_name, u.full_name as created_by_name
        FROM funds f
        LEFT JOIN projects p ON f.project_id = p.id
        LEFT JOIN users u ON f.created_by = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id]);
    $fund_details = $stmt->fetch();
    
    if (!$fund_details) {
        header('Location: funds_budget.php');
        exit();
    }
}
$linked_project = null;
$linked_project_id = (int) (is_array($fund_details) ? ($fund_details['project_id'] ?? 0) : $prefill_project);
foreach ($projects as $project) {
    if ((int) $project['id'] === $linked_project_id) {
        $linked_project = $project;
        break;
    }
}
$fund_labor = [];
if ($linked_project_id > 0) {
    try {
        $laborStmt = $pdo->prepare("SELECT b.id, b.budget_name, b.status, COALESCE(SUM(l.line_total), 0) AS payroll
            FROM labor_budgets b
            LEFT JOIN labor_budget_lines l ON l.labor_budget_id = b.id
            WHERE b.project_id = ?
            GROUP BY b.id, b.budget_name, b.status
            ORDER BY b.id DESC");
        $laborStmt->execute([$linked_project_id]);
        $fund_labor = $laborStmt->fetchAll();
    } catch (PDOException $e) {
        $fund_labor = [];
    }
}

if (($action === 'add' || $action === 'edit') && !canWriteDepartmentData('accounting')) {
    $_SESSION['error'] = 'Administrators have view-only access.';
    header('Location: funds_budget.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

include '../../includes/header.php';
?>

<style>
.funds-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.funds-stats .card {
    border-left: 4px solid #4e73df;
    min-width: 0;
    overflow: hidden;
}
.fund-available { color: #1cc88a; font-weight: 700; }
.fund-allocated { color: #f6c23e; font-weight: 700; }
.budget-over { color: #e74a3b; font-weight: 700; }
</style>

<div class="page-header">
    <h1><i class="fas fa-wallet"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Fund</a><?php endif; ?>
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
<div class="funds-stats">
    <div class="card" style="border-left-color:#4e73df;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Total Funds</div>
        <div class="stat-amount">₱<?php echo number_format(array_sum(array_column($funds, 'amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#f6c23e;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Allocated (labor + materials)</div>
        <div class="stat-amount">₱<?php echo number_format(array_sum(array_column($funds, 'allocated_amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#1cc88a;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Available Funds</div>
        <div class="stat-amount">₱<?php echo number_format(array_sum(array_column($funds, 'amount')) - array_sum(array_column($funds, 'allocated_amount')), 2); ?></div>
    </div>
    <div class="card" style="border-left-color:#36b9cc;margin:0;">
        <div style="font-size:0.75rem;color:#64748b;">Active Funds</div>
        <div style="font-size:1.5rem;font-weight:700;">
            <?php 
            $active = array_filter($funds, function($f) { return ($f['status'] ?? 'active') === 'active'; });
            echo count($active);
            ?>
        </div>
    </div>
</div>
<p style="color:#64748b;margin-top:-0.5rem;">Ang allocated ay planned wages plus ang materials na nabili at natanggap na, sa presyo ng supplier. Available Funds = quotation total minus iyon.</p>

<?php if ($action === 'list'): ?>
<div class="card">
    <h3><i class="fas fa-chart-pie"></i> Funds Monitoring</h3>
    <canvas id="fundsChart" height="90"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('fundsChart'), {
    type: 'doughnut',
    data: {
        labels: ['Available', 'Allocated'],
        datasets: [{
            data: [
                <?php echo json_encode(max(0, array_sum(array_column($funds, 'amount')) - array_sum(array_column($funds, 'allocated_amount')))); ?>,
                <?php echo json_encode((float) array_sum(array_column($funds, 'allocated_amount'))); ?>
            ],
            backgroundColor: ['#1cc88a', '#f6c23e']
        }]
    }
});
</script>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> Fund</h3>
    <p style="color:#64748b;margin-top:0;">Ito ang pondo ng project mula sa quotation. Ang Amount ang limit ng client. Ang Allocated Amount ay galing sa planned wages ng Labor/Salary Budget. Kapag lumampas, kailangan ng additional payment sa client.</p>
    <form method="POST">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_fund" value="1">
        <?php else: ?>
            <input type="hidden" name="add_fund" value="1">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Fund Name</label>
                <input type="text" name="fund_name" required value="<?php echo htmlspecialchars($fund_details['fund_name'] ?? ($linked_project ? ($linked_project['project_code'] . ' — ' . $linked_project['name']) : '')); ?>" placeholder="Hal. PRJ-2026-008 — Fire Protection System">
                <small>Pangalan ng pondo. Kapag may napiling project, kusang galing ito sa project. Puwede mong baguhin.</small>
            </div>
            <div class="form-group">
                <label>Fund Type</label>
                <select name="fund_type">
                    <option value="general" <?php echo ($fund_details['fund_type'] ?? ($linked_project ? 'project' : 'general')) == 'general' ? 'selected' : ''; ?>>General</option>
                    <option value="project" <?php echo ($fund_details['fund_type'] ?? ($linked_project ? 'project' : 'general')) == 'project' ? 'selected' : ''; ?>>Project</option>
                    <option value="reserve" <?php echo ($fund_details['fund_type'] ?? 'general') == 'reserve' ? 'selected' : ''; ?>>Reserve</option>
                    <option value="emergency" <?php echo ($fund_details['fund_type'] ?? 'general') == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Amount (₱)</label>
                <?php $fundAmount = $fund_details['amount'] ?? ($linked_project['estimated_budget'] ?? 0); ?>
                <input type="number" step="0.01" name="amount" id="fundAmount" required value="<?php echo htmlspecialchars($fundAmount); ?>" min="0" <?php echo $linked_project && (float) $linked_project['estimated_budget'] > 0 ? 'readonly' : ''; ?> style="<?php echo $linked_project && (float) $linked_project['estimated_budget'] > 0 ? 'background:#eff6ff;font-weight:700;color:#1d4ed8;' : ''; ?>">
                <small id="fundAmountNote">Kapag may project, ito ang total ng quotation. Hindi ito binabago nang mano-mano.</small>
            </div>
            <div class="form-group">
                <label>Project (Optional)</label>
                <select name="project_id" id="fundProject">
                    <option value="">No Project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>"
                            data-amount="<?php echo htmlspecialchars($project['estimated_budget'] ?? 0); ?>"
                            data-material="<?php echo htmlspecialchars($project['material_cost'] ?? 0); ?>"
                            data-labor="<?php echo htmlspecialchars($project['labor_cost'] ?? 0); ?>"
                            <?php echo (int) ($fund_details['project_id'] ?? $prefill_project) === (int) $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>The quotation total can be used as the fund amount. Material and labor stay visible from the project.</small>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Fund description"><?php echo htmlspecialchars($fund_details['description'] ?? ($linked_project ? ('Quotation total for this project. Material ₱' . number_format((float) $linked_project['material_cost'], 2) . '. Labor ₱' . number_format((float) $linked_project['labor_cost'], 2) . '.') : '')); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?php echo ($fund_details['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($fund_details['status'] ?? 'active') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="frozen" <?php echo ($fund_details['status'] ?? 'active') == 'frozen' ? 'selected' : ''; ?>>Frozen</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" name="<?php echo $action === 'add' ? 'add_fund' : 'update_fund'; ?>" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Fund</button>
            <a href="funds_budget.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $fund_details): ?>
<!-- View Fund -->
<div class="card">
    <h3>Fund Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Fund Name:</span> <strong><?php echo htmlspecialchars($fund_details['fund_name']); ?></strong></div>
        <div class="detail-row"><span>Fund Type:</span> <?php echo ucfirst($fund_details['fund_type']); ?></div>
        <div class="detail-row"><span>Total Amount:</span> <strong>₱<?php echo number_format($fund_details['amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Allocated (labor + materials):</span> <strong class="fund-allocated">₱<?php echo number_format($fund_details['allocated_amount'], 2); ?></strong></div>
        <div class="detail-row"><span>Available:</span> <strong class="<?php echo ($fund_details['amount'] - $fund_details['allocated_amount']) < 0 ? 'budget-over' : 'fund-available'; ?>">₱<?php echo number_format($fund_details['amount'] - $fund_details['allocated_amount'], 2); ?></strong></div>
        <?php if (($fund_details['amount'] - $fund_details['allocated_amount']) < 0): ?>
        <div class="alert alert-danger">Ang labor at purchased materials ay lampas sa fund ng ₱<?php echo number_format($fund_details['allocated_amount'] - $fund_details['amount'], 2); ?>. Kailangan ng additional payment sa client.</div>
        <?php endif; ?>
        <?php if ($fund_labor): ?>
        <div class="table-responsive" style="margin-top:1rem;">
            <table class="table">
                <thead><tr><th>Labor / Salary Budget</th><th>Planned wages</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($fund_labor as $labor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($labor['budget_name']); ?></td>
                        <td>₱<?php echo number_format((float) $labor['payroll'], 2); ?></td>
                        <td><?php echo ucfirst($labor['status']); ?></td>
                        <td><a href="labor_budget.php?action=view&id=<?php echo (int) $labor['id']; ?>">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif (!empty($fund_details['project_id'])): ?>
        <p><a href="labor_budget.php?action=add&project=<?php echo (int) $fund_details['project_id']; ?>">Gumawa ng Labor / Salary Budget</a> para mailagay ang pasahod sa fund na ito.</p>
        <?php endif; ?>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($fund_details['project_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($fund_details['description'] ?? '')); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $fund_details['status'] == 'active' ? 'success' : ($fund_details['status'] == 'frozen' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($fund_details['status']); ?></span></div>
        <div class="detail-row"><span>Created By:</span> <?php echo htmlspecialchars($fund_details['created_by_name'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($fund_details['created_at'])); ?></div>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $fund_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a><?php endif; ?>
        <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $fund_details['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this fund?')"><i class="fas fa-trash"></i> Delete</a><?php endif; ?>
        <a href="funds_budget.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Funds (<?php echo count($funds); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchFund" placeholder="Search funds..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
            <select id="filterType" style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
                <option value="">All Types</option>
                <option value="general">General</option>
                <option value="project">Project</option>
                <option value="reserve">Reserve</option>
                <option value="emergency">Emergency</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="fundTable">
            <thead>
                <tr>
                    <th onclick="sortTable('fundTable', 0)" style="cursor:pointer;">Fund Name <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 1)" style="cursor:pointer;">Type <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 2)" style="cursor:pointer;">Total Amount <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 3)" style="cursor:pointer;">Allocated <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 4)" style="cursor:pointer;">Available <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 5)" style="cursor:pointer;">Project <i class="fas fa-sort"></i></th>
                    <th onclick="sortTable('fundTable', 6)" style="cursor:pointer;">Status <i class="fas fa-sort"></i></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($funds)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-wallet" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No funds found. Click "New Fund" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($funds as $fund): ?>
                    <tr data-type="<?php echo htmlspecialchars($fund['fund_type']); ?>">
                        <td><strong><?php echo htmlspecialchars($fund['fund_name']); ?></strong></td>
                        <td><?php echo ucfirst($fund['fund_type']); ?></td>
                        <td>₱<?php echo number_format($fund['amount'], 2); ?></td>
                        <td class="fund-allocated">₱<?php echo number_format($fund['allocated_amount'], 2); ?></td>
                        <td class="fund-available">₱<?php echo number_format($fund['amount'] - $fund['allocated_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($fund['project_name'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-<?php echo $fund['status'] == 'active' ? 'success' : ($fund['status'] == 'frozen' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($fund['status']); ?></span></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $fund['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canWriteDepartmentData('accounting')): ?><a href="?action=edit&id=<?php echo $fund['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a><?php endif; ?>
                                <?php if (canDelete('accounting')): ?><a href="?action=delete&delete_id=<?php echo $fund['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this fund?')"><i class="fas fa-trash"></i></a><?php endif; ?>
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
document.getElementById('searchFund')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const typeFilter = document.getElementById('filterType').value;
    const rows = document.querySelectorAll('#fundTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const type = row.getAttribute('data-type') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesType = typeFilter === '' || type === typeFilter;
        row.style.display = matchesSearch && matchesType ? '' : 'none';
    });
});

// Type filter
document.getElementById('filterType')?.addEventListener('change', function() {
    const typeFilter = this.value;
    const searchTerm = document.getElementById('searchFund').value.toLowerCase();
    const rows = document.querySelectorAll('#fundTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const type = row.getAttribute('data-type') || '';
        const matchesSearch = text.includes(searchTerm);
        const matchesType = typeFilter === '' || type === typeFilter;
        row.style.display = matchesSearch && matchesType ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
<script>
document.getElementById('fundProject')?.addEventListener('change', function () {
    const option = this.selectedOptions[0];
    const amount = this.form.elements.amount;
    const note = document.getElementById('fundAmountNote');
    if (!option || !option.value) {
        amount.readOnly = false;
        amount.style.background = '';
        amount.style.fontWeight = '';
        amount.style.color = '';
        if (note) note.textContent = 'Walang project, kaya puwedeng i-type ang amount para sa general, reserve, o emergency fund.';
        return;
    }
    const total = Number(option.dataset.amount || 0);
    const material = Number(option.dataset.material || 0);
    const labor = Number(option.dataset.labor || 0);
    const money = (value) => '₱' + value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    if (!this.form.elements.fund_name.value) {
        this.form.elements.fund_name.value = option.textContent.trim();
    }
    if (this.form.elements.fund_type.value === 'general') {
        this.form.elements.fund_type.value = 'project';
    }
    if (total > 0) {
        amount.value = total;
        amount.readOnly = true;
        amount.style.background = '#eff6ff';
        amount.style.fontWeight = '700';
        amount.style.color = '#1d4ed8';
    }
    this.form.elements.description.value = 'Quotation total for this project. Material ' + money(material) + '. Labor ' + money(labor) + '.';
    if (note) note.textContent = 'Quotation total ' + money(total) + '. Material ' + money(material) + '. Labor ' + money(labor) + '. Ito ang limit ng client.';
});
</script>
