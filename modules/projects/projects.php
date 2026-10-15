<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/quotation_file.php';

requireDepartment(['engineering']);

$page_title = (($_GET['action'] ?? 'list') === 'add') ? 'Encode Project' : 'Project Status';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['extract_project_quotation'])) {
        header('Content-Type: application/json');
        try {
            if (!canEncodeApprovedProject()) {
                throw new RuntimeException('Only Executive Admin can attach a quotation file.');
            }
            echo json_encode(extractQuotationUpload($_FILES['quotation_file'] ?? []));
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }
    if ((isset($_POST['add_project']) || isset($_POST['update_project'])) && !canSaveProjectRecord()) {
        $_SESSION['error'] = 'You cannot save project records.';
        header('Location: projects.php');
        exit();
    }
    if (isset($_POST['add_project']) && !canEncodeApprovedProject()) {
        $_SESSION['error'] = 'Only Executive Admin can encode an approved project.';
        header('Location: projects.php');
        exit();
    }
    if (isset($_POST['add_project']) || isset($_POST['update_project'])) {
        $project_code = $_POST['project_code'] ?? generateProjectCode();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $client = trim($_POST['client'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $start_date = nullableDate($_POST['start_date'] ?? '');
        $end_date = nullableDate($_POST['end_date'] ?? '');
        $status = $_POST['status'] ?? 'planning';
        $estimated_budget = (float)($_POST['estimated_budget'] ?? 0);
        $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $source = trim($_POST['project_source'] ?? '');
        $source_quotation_id = null;
        $source_contract_id = null;
        $uploaded = $_FILES['quotation_file'] ?? [];
        $hasQuotationFile = ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($uploaded['name'] ?? '') !== '';
        $errors = [];
        $extracted = null;
        if ($hasQuotationFile && !canEncodeApprovedProject()) {
            $errors[] = 'Only Executive Admin can attach a quotation file.';
            $hasQuotationFile = false;
        }
        if ($hasQuotationFile) {
            try {
                $extracted = extractQuotationUpload($uploaded);
                if (isset($_POST['add_project'])) {
                    if ($name === '' && ($extracted['project'] ?? '') !== '') {
                        $name = $extracted['project'];
                    }
                    if ($location === '' && ($extracted['location'] ?? '') !== '') {
                        $location = $extracted['location'];
                    }
                    if ($client === '' && ($extracted['client'] ?? '') !== '') {
                        $client = $extracted['client'];
                    }
                    if ($subject === '' && ($extracted['subject'] ?? '') !== '') {
                        $subject = $extracted['subject'];
                    }
                    if ($estimated_budget <= 0 && (float) ($extracted['grand_total'] ?? 0) > 0) {
                        $estimated_budget = (float) $extracted['grand_total'];
                    }
                }
            } catch (Throwable $e) {
                $hasQuotationFile = false;
                $errors[] = $e->getMessage();
            }
        }
        
        if (empty($name)) $errors[] = 'Project name is required.';
        if ($estimated_budget <= 0) $errors[] = 'Estimated budget must be greater than 0.';
        if ($start_date !== null && $end_date !== null && $end_date < $start_date) {
            $errors[] = 'End date must be on or after the start date.';
        }
        if (isset($_POST['add_project'])) {
            if ($assigned_to === null) {
                $errors[] = 'Assign an Engineering lead.';
            }
            if ($source !== '' && !preg_match('/^(quotation|contract):(\d+)$/', $source, $source_match)) {
                $errors[] = 'Select an approved client quotation or a signed contract.';
            } elseif ($source !== '') {
                $source_id = (int) $source_match[2];
                if ($source_match[1] === 'quotation') {
                    $check = $pdo->prepare("SELECT id FROM client_quotations WHERE id = ? AND status = 'approved' AND (project_id IS NULL OR project_id = 0)");
                    $check->execute([$source_id]);
                    if (!$check->fetch()) {
                        $errors[] = 'That quotation is not approved, or it is already linked to a project.';
                    } else {
                        $source_quotation_id = $source_id;
                    }
                } else {
                    $check = $pdo->prepare("SELECT id FROM contracts WHERE id = ? AND status = 'active' AND (project_id IS NULL OR project_id = 0)");
                    $check->execute([$source_id]);
                    if (!$check->fetch()) {
                        $errors[] = 'That contract is not signed, or it is already linked to a project.';
                    } else {
                        $source_contract_id = $source_id;
                    }
                }
            }
        }
        
        if (empty($errors)) {
            try {
                if (isset($_POST['add_project'])) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO projects (project_code, name, description, location, client, subject, start_date, end_date, status, estimated_budget, created_by, assigned_to, in_charge_id, source_quotation_id, source_contract_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$project_code, $name, $description, $location, $client, $subject, $start_date, $end_date, $status, $estimated_budget, $_SESSION['user_id'], $assigned_to, $assigned_to, $source_quotation_id, $source_contract_id]);
                    $new_project_id = (int) $pdo->lastInsertId();
                    $attachment = storeProjectQuotation($uploaded, $new_project_id);
                    if ($attachment) {
                        $pdo->prepare("UPDATE projects SET quotation_path = ?, quotation_name = ? WHERE id = ?")->execute([$attachment['path'], $attachment['name'], $new_project_id]);
                    }
                    if (!empty($extracted['items'])) {
                        $itemStmt = $pdo->prepare("INSERT INTO project_quotation_items (project_id, item_name, description, quantity, unit, unit_price, line_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        foreach ($extracted['items'] as $item) {
                            $itemName = trim((string) ($item['item_name'] ?? ''));
                            if ($itemName === '' || preg_match('/\bvat\b/i', $itemName)) {
                                continue;
                            }
                            $itemStmt->execute([
                                $new_project_id,
                                $itemName,
                                trim((string) ($item['description'] ?? '')),
                                (float) ($item['quantity'] ?? 1),
                                trim((string) ($item['unit'] ?? '')) ?: 'lot',
                                (float) ($item['unit_price'] ?? 0),
                                in_array($item['line_type'] ?? '', ['material', 'labor', 'other'], true) ? $item['line_type'] : 'other',
                            ]);
                        }
                    }
                    if ($source_quotation_id) {
                        $link = $pdo->prepare("UPDATE client_quotations SET project_id = ? WHERE id = ? AND (project_id IS NULL OR project_id = 0)");
                        $link->execute([$new_project_id, $source_quotation_id]);
                        if ($link->rowCount() !== 1) {
                            throw new RuntimeException('The quotation was already used for another project.');
                        }
                    } elseif ($source_contract_id) {
                        $link = $pdo->prepare("UPDATE contracts SET project_id = ? WHERE id = ? AND (project_id IS NULL OR project_id = 0)");
                        $link->execute([$new_project_id, $source_contract_id]);
                        if ($link->rowCount() !== 1) {
                            throw new RuntimeException('The contract was already used for another project.');
                        }
                    }
                    $pdo->commit();
                    
                    logActivity($_SESSION['user_id'], 'Created project', 'Projects', "Project: $project_code");
                    notify('accounting', 'project_created', 'New project', 'New project ' . $project_code . ' is waiting in Received Projects.', 'modules/accounting/received_projects.php', $new_project_id, 'project', $new_project_id);
                    if ((int) $assigned_to > 0) {
                        notify((int) $assigned_to, 'pic_assigned', 'Project assignment', 'You have been assigned to ' . $project_code . '.', 'modules/projects/project_details.php?id=' . $new_project_id, $new_project_id, 'project', $new_project_id);
                    }
                    $_SESSION['success'] = "Project created. Accounting and Engineering can open it under Received Projects.";
                    header('Location: projects.php');
                    exit();
                } else {
                    $prevPic = 0;
                    $prevStmt = $pdo->prepare("SELECT in_charge_id FROM projects WHERE id = ?");
                    $prevStmt->execute([$id]);
                    $prevPic = (int) $prevStmt->fetchColumn();
                    $stmt = $pdo->prepare("UPDATE projects SET name = ?, description = ?, location = ?, client = ?, subject = ?, start_date = ?, end_date = ?, status = ?, estimated_budget = ?, assigned_to = ?, in_charge_id = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $location, $client, $subject, $start_date, $end_date, $status, $estimated_budget, $assigned_to, $assigned_to, $id]);
                    if ((int) $assigned_to > 0 && (int) $assigned_to !== $prevPic) {
                        notify((int) $assigned_to, 'pic_assigned', 'Project assignment', 'You have been assigned to ' . $project_code . '.', 'modules/projects/project_details.php?id=' . $id, $id, 'project', $id);
                    }
                    if ($hasQuotationFile && $id > 0) {
                        $attachment = storeProjectQuotation($uploaded, $id);
                        if ($attachment) {
                            $pdo->prepare("UPDATE projects SET quotation_path = ?, quotation_name = ? WHERE id = ?")->execute([$attachment['path'], $attachment['name'], $id]);
                        }
                        if (!empty($extracted['items'])) {
                            $pdo->prepare("DELETE FROM project_quotation_items WHERE project_id = ?")->execute([$id]);
                            $itemStmt = $pdo->prepare("INSERT INTO project_quotation_items (project_id, item_name, description, quantity, unit, unit_price, line_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            foreach ($extracted['items'] as $item) {
                                $itemName = trim((string) ($item['item_name'] ?? ''));
                                if ($itemName === '' || preg_match('/\bvat\b/i', $itemName)) {
                                    continue;
                                }
                                $itemStmt->execute([
                                    $id,
                                    $itemName,
                                    trim((string) ($item['description'] ?? '')),
                                    (float) ($item['quantity'] ?? 1),
                                    trim((string) ($item['unit'] ?? '')) ?: 'lot',
                                    (float) ($item['unit_price'] ?? 0),
                                    in_array($item['line_type'] ?? '', ['material', 'labor', 'other'], true) ? $item['line_type'] : 'other',
                                ]);
                            }
                        }
                    }
                    
                    logActivity($_SESSION['user_id'], 'Updated project', 'Projects', "Project: $project_code");
                    $_SESSION['success'] = "Project updated successfully!";
                    header('Location: projects.php');
                    exit();
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof PDOException ? userDatabaseError($e) : $e->getMessage();
            }
        } else {
            $error = implode(' ', $errors);
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id']) && $action === 'delete') {
    if (!canEncodeApprovedProject() && !canDelete('projects')) {
        $_SESSION['error'] = 'You cannot delete this project.';
        header('Location: projects.php');
        exit();
    }
    $delete_id = (int)$_GET['delete_id'];
    try {
        $pdo->beginTransaction();
        $fileStmt = $pdo->prepare("SELECT quotation_path FROM projects WHERE id = ?");
        $fileStmt->execute([$delete_id]);
        $projectFile = (string) ($fileStmt->fetchColumn() ?: '');

        $quoteFiles = [];
        $clientIds = [];
        try {
            $clientStmt = $pdo->prepare("SELECT id, attachment_path FROM client_quotations WHERE project_id = ?");
            $clientStmt->execute([$delete_id]);
            foreach ($clientStmt->fetchAll() as $quote) {
                $clientIds[] = (int) $quote['id'];
                if (!empty($quote['attachment_path'])) {
                    $quoteFiles[] = $quote['attachment_path'];
                }
            }
        } catch (PDOException $e) {
        }
        if ($projectFile !== '') {
            $quoteFiles[] = $projectFile;
        }
        if ($clientIds) {
            $marks = implode(',', array_fill(0, count($clientIds), '?'));
            try {
                $pdo->prepare("UPDATE invoices SET quotation_id = NULL WHERE quotation_id IN ($marks)")->execute($clientIds);
            } catch (PDOException $e) {
            }
            try {
                $pdo->prepare("DELETE FROM client_quotation_items WHERE quotation_id IN ($marks)")->execute($clientIds);
            } catch (PDOException $e) {
            }
            $pdo->prepare("DELETE FROM client_quotations WHERE project_id = ?")->execute([$delete_id]);
        }
        try {
            $pdo->prepare("DELETE FROM client_quotations WHERE project_id = ?")->execute([$delete_id]);
        } catch (PDOException $e) {
        }
        $pdo->prepare("DELETE FROM quotations WHERE project_id = ?")->execute([$delete_id]);
        try {
            $pdo->prepare("DELETE FROM project_quotation_items WHERE project_id = ?")->execute([$delete_id]);
        } catch (PDOException $e) {
        }
        try {
            $laborStmt = $pdo->prepare("SELECT id FROM labor_budgets WHERE project_id = ?");
            $laborStmt->execute([$delete_id]);
            $laborIds = $laborStmt->fetchAll(PDO::FETCH_COLUMN);
            if ($laborIds) {
                $laborMarks = implode(',', array_fill(0, count($laborIds), '?'));
                $pdo->prepare("DELETE FROM labor_budget_lines WHERE labor_budget_id IN ($laborMarks)")->execute($laborIds);
            }
            $pdo->prepare("DELETE FROM labor_budgets WHERE project_id = ?")->execute([$delete_id]);
        } catch (PDOException $e) {
        }
        foreach (['contracts', 'funds', 'expenses', 'invoices'] as $linkedTable) {
            try {
                $pdo->prepare("DELETE FROM {$linkedTable} WHERE project_id = ?")->execute([$delete_id]);
            } catch (PDOException $e) {
            }
        }
        $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$delete_id]);
        $pdo->commit();

        $uploadRoot = realpath(__DIR__ . '/../../uploads');
        foreach ($quoteFiles as $relative) {
            $relative = str_replace('\\', '/', (string) $relative);
            if (!preg_match('#^uploads/(project_quotations|quotations)/#', $relative)) {
                continue;
            }
            $full = realpath(__DIR__ . '/../../' . $relative);
            if ($uploadRoot && $full && str_starts_with($full, $uploadRoot) && is_file($full)) {
                @unlink($full);
            }
        }

        logActivity($_SESSION['user_id'], 'Deleted project', 'Projects', "Project ID: $delete_id");
        $_SESSION['success'] = 'Project deleted. The quotation and its links in the other departments were removed.';
        header('Location: projects.php');
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = userDatabaseError($e);
        header('Location: projects.php');
        exit();
    }
}

// Get projects - FIXED: Removed archived column reference
$projects = [];
try {
    $query = "SELECT p.*, vpc.actual_cost, vpc.pending_cost, vpc.budget_variance, vpc.expense_total, vpc.purchase_total, COALESCE(ua.full_name, ui.full_name) AS in_charge_name,
                     (SELECT COUNT(*) FROM quotations q WHERE q.project_id = p.id)
                     + (SELECT COUNT(*) FROM client_quotations cq WHERE cq.project_id = p.id) AS quotation_count
              FROM projects p
              LEFT JOIN v_project_costs vpc ON vpc.project_id = p.id
              LEFT JOIN users ua ON p.assigned_to = ua.id
              LEFT JOIN users ui ON p.in_charge_id = ui.id
              ORDER BY p.created_at DESC";
    $projects = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

// Get single project
$project_details = null;
if ($action === 'edit' || $action === 'view') {
    $stmt = $pdo->prepare("SELECT p.*, vpc.actual_cost, vpc.pending_cost, vpc.budget_variance, vpc.expense_total, vpc.purchase_total, COALESCE(ua.full_name, ui.full_name) AS in_charge_name
        FROM projects p
        LEFT JOIN v_project_costs vpc ON vpc.project_id = p.id
        LEFT JOIN users ua ON p.assigned_to = ua.id
        LEFT JOIN users ui ON p.in_charge_id = ui.id
        WHERE p.id = ?");
    $stmt->execute([$id]);
    $project_details = $stmt->fetch();
    
    if (!$project_details) {
        header('Location: projects.php');
        exit();
    }
}

if ($action === 'add' && !canEncodeApprovedProject()) {
    $_SESSION['error'] = 'Only Executive Admin can encode an approved project.';
    header('Location: projects.php');
    exit();
}
if ($action === 'edit' && !canSaveProjectRecord()) {
    $_SESSION['error'] = 'You cannot edit this project.';
    header('Location: projects.php' . ($id ? '?action=view&id=' . $id : ''));
    exit();
}

$engineering_users = [];
$project_sources = [];
$selected_source = '';
try {
    $engineering_users = $pdo->query("SELECT id, full_name FROM users WHERE department = 'engineering' AND status = 'active' ORDER BY full_name")->fetchAll();
    $quotes = $pdo->query("SELECT id, quotation_number, client_name, grand_total, scope_of_work FROM client_quotations WHERE status = 'approved' AND (project_id IS NULL OR project_id = 0) ORDER BY created_at DESC")->fetchAll();
    foreach ($quotes as $quote) {
        $project_sources[] = [
            'value' => 'quotation:' . (int) $quote['id'],
            'label' => 'Approved quotation ' . $quote['quotation_number'] . ' — ' . $quote['client_name'],
            'name' => $quote['client_name'] . ' Project',
            'client' => $quote['client_name'],
            'location' => '',
            'budget' => (float) $quote['grand_total'],
            'start' => '',
            'end' => '',
            'description' => (string) ($quote['scope_of_work'] ?? ''),
        ];
    }
    $contracts = $pdo->query("SELECT id, contract_number, contract_name, client, contract_value, start_date, end_date, description FROM contracts WHERE status = 'active' AND (project_id IS NULL OR project_id = 0) ORDER BY created_at DESC")->fetchAll();
    foreach ($contracts as $contract) {
        $project_sources[] = [
            'value' => 'contract:' . (int) $contract['id'],
            'label' => 'Signed contract ' . $contract['contract_number'] . ' — ' . $contract['contract_name'],
            'name' => $contract['contract_name'],
            'client' => $contract['client'],
            'location' => '',
            'budget' => (float) $contract['contract_value'],
            'start' => $contract['start_date'] ?? '',
            'end' => $contract['end_date'] ?? '',
            'description' => (string) ($contract['description'] ?? ''),
        ];
    }
    $requested_source = trim($_GET['source'] ?? '');
    foreach ($project_sources as $source_row) {
        if ($source_row['value'] === $requested_source) {
            $selected_source = $requested_source;
            break;
        }
    }
} catch (PDOException $e) {
    $engineering_users = $engineering_users ?: [];
    $project_sources = [];
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-building"></i> <?php echo $page_title; ?></h1>
    <?php if ($action === 'list'): ?>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php if (canEncodeApprovedProject()): ?>
            <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Encode Approved Project</a>
            <?php endif; ?>
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

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Form -->
<div class="card">
    <h3><?php echo $action === 'add' ? 'Encode Approved Project' : 'Update Project'; ?></h3>
    <?php if ($action === 'add'): ?>
    <p style="color:#64748b;margin-top:0;">Attach the quotation file. Project, Location, Client, and Subject are filled from it and saved on the project. Accounting can view that file and create the client quotation from it.</p>
    <?php endif; ?>
    <form method="POST" id="projectForm" enctype="multipart/form-data">
        <?php if ($action === 'edit'): ?>
            <input type="hidden" name="update_project" value="1">
            <input type="hidden" name="project_code" value="<?php echo htmlspecialchars($project_details['project_code']); ?>">
        <?php else: ?>
            <input type="hidden" name="add_project" value="1">
        <?php endif; ?>

        <?php if ($action === 'add'): ?>
        <div class="form-group">
            <label>Start from</label>
            <select name="project_source" id="projectSource">
                <option value="">No quotation or contract yet</option>
                <?php foreach ($project_sources as $source): ?>
                    <option value="<?php echo htmlspecialchars($source['value']); ?>" <?php echo $selected_source === $source['value'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($source['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($project_sources)): ?>
                <small>None yet. After Accounting approves a client quotation or marks a contract active, it appears in this list and links to the new project.</small>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($action === 'add' || ($action === 'edit' && canEncodeApprovedProject())): ?>
        <div class="form-group">
            <label>Quotation file</label>
            <input type="file" name="quotation_file" id="projectQuoteFile" accept=".csv,.txt,.xlsx,.pdf">
            <small id="projectQuoteStatus" style="display:block;margin-top:0.35rem;">Attach the quotation. Project, Location, Client, and Subject are filled from the file. Accounting receives this file with the project.</small>
            <?php if ($action === 'edit' && !empty($project_details['quotation_path'])): ?>
                <small style="display:block;margin-top:0.35rem;">Current file: <a href="<?php echo APP_URL . htmlspecialchars($project_details['quotation_path']); ?>" target="_blank"><?php echo htmlspecialchars($project_details['quotation_name'] ?: 'View file'); ?></a></small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Project Code</label>
                <input type="text" value="<?php echo $action === 'edit' ? htmlspecialchars($project_details['project_code']) : generateProjectCode(); ?>" disabled style="background:#f1f5f9;">
                <?php if ($action === 'add'): ?>
                    <input type="hidden" name="project_code" value="<?php echo generateProjectCode(); ?>">
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="required">Project Name</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($project_details['name'] ?? ''); ?>" placeholder="Enter project name">
                <?php if ($action === 'edit'): ?>
                <small>You can rename this project. Update Project saves the new name.</small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($project_details['location'] ?? ''); ?>" placeholder="Project location">
            </div>
            <div class="form-group">
                <label>Client</label>
                <input type="text" name="client" value="<?php echo htmlspecialchars($project_details['client'] ?? ''); ?>" placeholder="Client name">
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" value="<?php echo htmlspecialchars($project_details['subject'] ?? ''); ?>" placeholder="Subject from the quotation">
            </div>
            <div class="form-group">
                <label class="required">Assigned To (Engineering Lead)</label>
                <select name="assigned_to" <?php echo $action === 'add' ? 'required' : ''; ?>>
                    <option value="">Unassigned</option>
                    <?php
                    $current_assigned = (int)($project_details['assigned_to'] ?? $project_details['in_charge_id'] ?? 0);
                    foreach ($engineering_users as $eng):
                    ?>
                        <option value="<?php echo (int)$eng['id']; ?>" <?php echo $current_assigned === (int)$eng['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($eng['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" id="projectStartDate" value="<?php echo htmlspecialchars(nullableDate($project_details['start_date'] ?? '') ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" id="projectEndDate" value="<?php echo htmlspecialchars(nullableDate($project_details['end_date'] ?? '') ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="assigned" <?php echo ($project_details['status'] ?? ($action === 'add' ? 'assigned' : '')) == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                    <option value="planning" <?php echo ($project_details['status'] ?? '') == 'planning' ? 'selected' : ''; ?>>Planning</option>
                    <option value="ongoing" <?php echo ($project_details['status'] ?? '') == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                    <option value="completed" <?php echo ($project_details['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="on_hold" <?php echo ($project_details['status'] ?? '') == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="required">Estimated Budget (₱)</label>
                <input type="number" step="0.01" name="estimated_budget" required value="<?php echo htmlspecialchars((string) ($project_details['estimated_budget'] ?? '')); ?>" min="0.01" placeholder="0.00">
                <small>The quotation file fills this with its total. You can change it before and after the project is created.</small>
            </div>
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Project description"><?php echo htmlspecialchars($project_details['description'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Project</button>
            <a href="projects.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
(function () {
    const form = document.getElementById('projectForm');
    const start = document.getElementById('projectStartDate');
    const end = document.getElementById('projectEndDate');
    function syncEndMin() {
        if (!start || !end) return;
        end.min = start.value || '';
        if (start.value && end.value && end.value < start.value) end.value = start.value;
    }
    start?.addEventListener('change', syncEndMin);
    form?.addEventListener('submit', function () {
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
    });
    syncEndMin();
})();
</script>
<script>
const projectSources = <?php echo json_encode($project_sources, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
document.getElementById('projectSource')?.addEventListener('change', function () {
    const source = projectSources.find((item) => item.value === this.value);
    if (!source) return;
    const form = document.getElementById('projectForm');
    form.elements.name.value = source.name || '';
    form.elements.client.value = source.client || '';
    form.elements.location.value = source.location || '';
    form.elements.estimated_budget.value = source.budget || '';
    form.elements.start_date.value = source.start || '';
    form.elements.end_date.value = source.end || '';
    form.elements.description.value = source.description || '';
});
document.getElementById('projectSource')?.dispatchEvent(new Event('change'));
document.getElementById('projectQuoteFile')?.addEventListener('change', async () => {
    const input = document.getElementById('projectQuoteFile');
    const status = document.getElementById('projectQuoteStatus');
    const saveBtn = document.querySelector('#projectForm button[type="submit"]');
    if (!input.files[0]) return;
    status.textContent = 'Reading the quotation file...';
    if (saveBtn) saveBtn.disabled = true;
    const body = new FormData();
    body.append('extract_project_quotation', '1');
    body.append('quotation_file', input.files[0]);
    try {
        const response = await fetch('projects.php', { method: 'POST', body });
        const data = await response.json();
        if (!response.ok || data.error) {
            status.textContent = data.error || 'Could not read the quotation file.';
            return;
        }
        const form = document.getElementById('projectForm');
        if (data.project) form.elements.name.value = data.project;
        if (data.location) form.elements.location.value = data.location;
        if (data.client) form.elements.client.value = data.client;
        if (data.subject) form.elements.subject.value = data.subject;
        if (data.grand_total) form.elements.estimated_budget.value = data.grand_total;
        status.textContent = 'Project, Location, Client, Subject, and Estimated Budget were filled from the quotation total. You can edit the budget before saving.';
    } catch (error) {
        status.textContent = 'Could not read the quotation file.';
    } finally {
        if (saveBtn) saveBtn.disabled = false;
    }
});
</script>

<?php elseif ($action === 'view' && $project_details): ?>
<!-- View Project -->
<div class="card">
    <h3>Project Details</h3>
    <div class="view-details">
        <div class="detail-row"><span>Project Code:</span> <strong><?php echo htmlspecialchars($project_details['project_code']); ?></strong></div>
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($project_details['name']); ?></div>
        <div class="detail-row"><span>Location:</span> <?php echo htmlspecialchars($project_details['location'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Client:</span> <?php echo htmlspecialchars($project_details['client'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Subject:</span> <?php echo htmlspecialchars($project_details['subject'] ?? 'N/A'); ?></div>
        <div class="detail-row"><span>Quotation file:</span> <?php if (!empty($project_details['quotation_path'])): ?><a href="<?php echo APP_URL . htmlspecialchars($project_details['quotation_path']); ?>" target="_blank"><?php echo htmlspecialchars($project_details['quotation_name'] ?: 'View file'); ?></a><?php else: ?>No file attached<?php endif; ?></div>
        <div class="detail-row"><span>Accounting:</span> <?php echo (($project_details['accounting_status'] ?? '') === 'received' || !empty($project_details['accounting_received_at'])) ? 'Received' . (!empty($project_details['accounting_received_at']) ? ' ' . date('M d, Y h:i A', strtotime($project_details['accounting_received_at'])) : '') : 'Waiting in Received Projects'; ?></div>
        <div class="detail-row"><span>Engineering:</span> <?php echo !empty($project_details['engineering_received_at']) ? 'Received ' . date('M d, Y h:i A', strtotime($project_details['engineering_received_at'])) : 'Waiting in Received Projects'; ?></div>
        <div class="detail-row"><span>Project In-Charge:</span> <?php echo htmlspecialchars($project_details['in_charge_name'] ?? 'Not assigned (Accounting)'); ?></div>
        <div class="detail-row"><span>Status:</span> <span class="badge badge-<?php echo $project_details['status']; ?>"><?php echo ucfirst($project_details['status']); ?></span></div>
        <div class="detail-row"><span>Start Date:</span> <?php echo formatDisplayDate($project_details['start_date'] ?? null); ?></div>
        <div class="detail-row"><span>End Date:</span> <?php echo formatDisplayDate($project_details['end_date'] ?? null); ?></div>
        <div class="detail-row"><span>Estimated Budget:</span> <strong>₱<?php echo number_format((float) $project_details['estimated_budget'], 2); ?></strong></div>
        <div class="detail-row" <?php echo ((float) ($project_details['actual_cost'] ?? 0) > (float) $project_details['estimated_budget']) ? 'style="background:#fee2e2;"' : ''; ?>><span>Actual Cost:</span> <strong>₱<?php echo number_format((float) ($project_details['actual_cost'] ?? 0), 2); ?></strong></div>
        <div class="detail-row"><span>Pending/Committed:</span> <strong>₱<?php echo number_format((float) ($project_details['pending_cost'] ?? 0), 2); ?></strong></div>
        <div class="detail-row"><span>Budget Variance:</span> <strong>₱<?php echo number_format((float) ($project_details['budget_variance'] ?? ((float) $project_details['estimated_budget'] - (float) ($project_details['actual_cost'] ?? 0))), 2); ?></strong></div>
        <?php if ($project_details['description']): ?>
            <div class="detail-row"><span>Description:</span> <?php echo nl2br(htmlspecialchars($project_details['description'])); ?></div>
        <?php endif; ?>
        <div class="detail-row"><span>Created:</span> <?php echo date('M d, Y h:i A', strtotime($project_details['created_at'])); ?></div>
        <?php if ($project_details['updated_at'] && $project_details['updated_at'] != $project_details['created_at']): ?>
            <div class="detail-row"><span>Updated:</span> <?php echo date('M d, Y h:i A', strtotime($project_details['updated_at'])); ?></div>
        <?php endif; ?>
    </div>
    <div style="margin-top:1.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
        <?php if (canWriteDepartmentData('projects')): ?>
        <a href="?action=edit&id=<?php echo $project_details['id']; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
        <?php endif; ?>
        <a href="project_pdf.php?id=<?php echo (int)$project_details['id']; ?>" class="btn btn-outline"><i class="fas fa-download"></i> Download PDF</a>
        <a href="project_details.php?id=<?php echo $project_details['id']; ?>" class="btn btn-info"><i class="fas fa-chart-bar"></i> Full Details</a>
        <?php if (canEncodeApprovedProject() || canDelete('projects')): ?>
        <?php
        $view_quote_count = 0;
        try {
            $quoteCountStmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM quotations WHERE project_id = ?) + (SELECT COUNT(*) FROM client_quotations WHERE project_id = ?)");
            $quoteCountStmt->execute([(int) $project_details['id'], (int) $project_details['id']]);
            $view_quote_count = (int) $quoteCountStmt->fetchColumn();
        } catch (PDOException $e) {
            $view_quote_count = 0;
        }
        $delete_confirm = $view_quote_count > 0
            ? 'This project has ' . $view_quote_count . ' quotation(s). Proceed? The project, the quotation, and its links in the other departments will be removed.'
            : 'Delete this project? Its quotation and department links will be removed. This cannot be undone.';
        ?>
        <a href="?action=delete&delete_id=<?php echo $project_details['id']; ?>" class="btn btn-danger" onclick="return confirm(<?php echo json_encode($delete_confirm); ?>)"><i class="fas fa-trash"></i> Delete</a>
        <?php endif; ?>
        <a href="projects.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php else: ?>
<!-- List View -->
<div class="card">
    <p style="color:#64748b;margin-top:0;">Approved projects encoded by Executive Admin. The attached quotation is included when Accounting opens the project under Received Projects.</p>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> Centralized Project Status (<?php echo count($projects); ?>)</h3>
        <div style="display:flex; gap:0.5rem;">
            <input type="text" id="searchProject" placeholder="Search projects..." style="padding:0.4rem 0.8rem; border:1px solid var(--border-color); border-radius:6px; font-size:0.85rem;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="projectTable">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Project</th>
                    <th>Client</th>
                    <th>Quotation</th>
                    <th>Accounting</th>
                    <th>Engineering</th>
                    <th>Status</th>
                    <th>Estimated Budget</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">
                            <i class="fas fa-building" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.3;"></i>
                            No projects encoded yet. Encode an approved project and attach the quotation.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                    <tr<?php echo ((float) ($project['actual_cost'] ?? 0) > (float) $project['estimated_budget']) ? ' style="background:#fee2e2;"' : ''; ?>>
                        <td><strong><?php echo htmlspecialchars(displayDocumentCode($project['project_code'])); ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($project['name']); ?><br>
                            <small><?php echo htmlspecialchars($project['location'] ?? ''); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($project['client'] ?? '—'); ?></td>
                        <td>
                            <?php if (!empty($project['quotation_path'])): ?>
                                <a href="<?php echo APP_URL . htmlspecialchars($project['quotation_path']); ?>" target="_blank"><?php echo htmlspecialchars($project['quotation_name'] ?: 'View file'); ?></a>
                            <?php else: ?>
                                No file
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($project['accounting_received_at'])): ?>
                                <span class="badge badge-approved">Received</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Waiting</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($project['engineering_received_at'])): ?>
                                <span class="badge badge-approved">Received</span>
                            <?php else: ?>
                                <span class="badge badge-pending">Waiting</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $project['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?></span></td>
                        <td>₱<?php echo number_format((float) ($project['estimated_budget'] ?? 0), 2); ?></td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="?action=view&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (canSaveProjectRecord()): ?>
                                <a href="?action=edit&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if (canEncodeApprovedProject() || canDelete('projects')): ?>
                                <?php
                                $row_quote_count = (int) ($project['quotation_count'] ?? 0);
                                $row_delete_confirm = $row_quote_count > 0
                                    ? 'This project has ' . $row_quote_count . ' quotation(s). Proceed? The project, the quotation, and its links in the other departments will be removed.'
                                    : 'Delete this project? Its quotation and department links will be removed. This cannot be undone.';
                                ?>
                                <a href="?action=delete&delete_id=<?php echo $project['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm(<?php echo json_encode($row_delete_confirm); ?>)"><i class="fas fa-trash"></i> Delete</a>
                                <?php endif; ?>
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
document.getElementById('searchProject')?.addEventListener('keyup', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#projectTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>