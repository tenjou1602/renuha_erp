<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin_project_queue.php';

requireDepartment(['accounting']);

$page_title = 'Quotations';
$action = $_GET['action'] ?? 'list';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quotation'])) {
    $supplier = trim($_POST['supplier'] ?? '');
    $supplier_contact = trim($_POST['supplier_contact'] ?? '');
    $project_id = (int) ($_POST['project_id'] ?? 0) ?: null;
    $valid_until = trim($_POST['valid_until'] ?? '') ?: null;
    $status = $_POST['status'] ?? 'requested';
    $notes = trim($_POST['notes'] ?? '');
    $allowed = ['requested', 'received', 'accepted', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        $status = 'requested';
    }

    if ($supplier === '') {
        $error = 'Supplier is required.';
    } else {
        try {
            $quotation_number = generateNumber('QT', 'quotations', 'quotation_number');
            $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, project_id, supplier, supplier_contact, valid_until, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$quotation_number, $project_id, $supplier, $supplier_contact, $valid_until, $status, $notes, $_SESSION['user_id']]);
            logActivity($_SESSION['user_id'], 'Created quotation', 'Procurement', "QT: $quotation_number");
            $_SESSION['success'] = 'Quotation saved.';
            header('Location: project_quotations.php');
            exit();
        } catch (PDOException $e) {
            $error = userDatabaseError($e, 'Procurement');
        }
    }
}

$projects = [];
$quotations = [];
$client_quotations = [];
try {
    $projects = adminProjectRows($pdo);
    $quotations = $pdo->query("
        SELECT q.*, p.name AS project_name, p.project_code,
               (
                   SELECT pr.pr_number
                   FROM purchase_requests pr
                   WHERE pr.project_id = q.project_id
                   ORDER BY pr.id DESC
                   LIMIT 1
               ) AS pr_number
        FROM quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        ORDER BY q.created_at DESC
    ")->fetchAll();
    $client_quotations = $pdo->query("
        SELECT q.*, p.name AS project_name, p.project_code
        FROM client_quotations q
        LEFT JOIN projects p ON q.project_id = p.id
        ORDER BY q.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $error = userDatabaseError($e, 'Procurement');
}

$quotation_details = null;
if ($action === 'view' && $id > 0) {
    foreach ($quotations as $quote) {
        if ((int) $quote['id'] === $id) {
            $quotation_details = $quote;
            break;
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-file-signature"></i> Quotations</h1>
    <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
    <?php endif; ?>
</div>

<?php adminRenderWorkQueue($projects, APP_URL . 'modules/admin/project_management.php?action=add', 'Create / enroll project'); ?>

<div class="aq-pill"><i class="fas fa-handshake"></i> Workflow Stage: Negotiate &amp; Purchase (Quotations)</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="card">
    <h3>New supplier quotation</h3>
    <form method="POST">
        <input type="hidden" name="add_quotation" value="1">
        <div class="form-row">
            <div class="form-group">
                <label class="required">Supplier</label>
                <input type="text" name="supplier" required value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Supplier Contact</label>
                <input type="text" name="supplier_contact" value="<?php echo htmlspecialchars($_POST['supplier_contact'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Project</label>
                <select name="project_id">
                    <option value="">No project</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo (int) $project['id']; ?>"><?php echo htmlspecialchars(displayDocumentCode($project['project_code']) . ' - ' . $project['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Valid Until</label>
                <input type="date" name="valid_until">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="requested">Requested</option>
                    <option value="received">Received</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"></textarea>
        </div>
        <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save quotation</button>
            <a href="project_quotations.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($action === 'view' && $quotation_details): ?>
<div class="card">
    <h3><?php echo htmlspecialchars($quotation_details['quotation_number']); ?></h3>
    <div class="view-details">
        <div class="detail-row"><span>Project:</span> <?php echo htmlspecialchars($quotation_details['project_code'] ? displayDocumentCode($quotation_details['project_code']) . ' · ' . $quotation_details['project_name'] : 'N/A'); ?></div>
        <div class="detail-row"><span>Purchase request:</span> <?php echo htmlspecialchars($quotation_details['pr_number'] ?: 'N/A'); ?></div>
        <div class="detail-row"><span>Supplier:</span> <?php echo htmlspecialchars($quotation_details['supplier']); ?></div>
        <div class="detail-row"><span>Status:</span> <?php echo htmlspecialchars(ucfirst($quotation_details['status'])); ?></div>
        <div class="detail-row"><span>Valid until:</span> <?php echo htmlspecialchars(adminFormatDate($quotation_details['valid_until']) ?: 'N/A'); ?></div>
        <div class="detail-row"><span>Amount:</span> ₱<?php echo number_format((float) ($quotation_details['total_amount'] ?? 0), 2); ?></div>
    </div>
    <div style="margin-top:1rem;"><a href="project_quotations.php" class="btn btn-secondary">Back</a></div>
</div>

<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>QT #</th>
                    <th>PR #</th>
                    <th>Project</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Valid Until</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr><td colspan="7"><div class="aq-empty"><i class="fas fa-file-signature"></i>No quotations found.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($quotations as $quote): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($quote['quotation_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($quote['pr_number'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($quote['project_name'] ?: 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($quote['supplier']); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($quote['status']); ?>"><?php echo htmlspecialchars(ucfirst($quote['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars(adminFormatDate($quote['valid_until']) ?: '—'); ?></td>
                        <td><a href="?action=view&id=<?php echo (int) $quote['id']; ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h3><i class="fas fa-file-invoice-dollar"></i> quotations</h3>
    <p style="color:#64748b;margin-top:0;">These come from accounting proposals. They are not supplier purchase quotations.</p>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Client</th>
                    <th>Project</th>
                    <th>Grand Total</th>
                    <th>Status</th>
                    <th>Valid Until</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($client_quotations)): ?>
                    <tr><td colspan="6"><div class="aq-empty"><i class="fas fa-file-invoice"></i>No client quotations found.</div></td></tr>
                <?php else: ?>
                    <?php foreach ($client_quotations as $quote): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($quote['quotation_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($quote['client_name']); ?></td>
                        <td><?php echo htmlspecialchars($quote['project_code'] ? displayDocumentCode($quote['project_code']) . ' · ' . ($quote['project_name'] ?? '') : 'N/A'); ?></td>
                        <td>₱<?php echo number_format((float) $quote['grand_total'], 2); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($quote['status']); ?>"><?php echo htmlspecialchars(ucfirst($quote['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars(adminFormatDate($quote['validity_date'] ?? '') ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
