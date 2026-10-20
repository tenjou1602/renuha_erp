<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'runeha_erp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'RUNEHA INC. ERP System');
define('APP_URL', 'http://localhost/runeha_erp/');
define('TIMEZONE', 'Asia/Manila');

// Set timezone
date_default_timezone_set(TIMEZONE);

function runehaProjectCostsSelectSql(): string
{
    return "SELECT
  p.id AS project_id,
  p.project_code AS project_code,
  p.name AS name,
  p.estimated_budget AS estimated_budget,
  p.status AS status,
  COALESCE(paid.paid_total, 0) AS expense_total,
  COALESCE(pend.pending_total, 0) AS pending_cost,
  COALESCE(po.purchase_total, 0) AS purchase_total,
  COALESCE(paid.paid_total, 0) + COALESCE(po.purchase_total, 0) AS actual_cost,
  p.estimated_budget - (COALESCE(paid.paid_total, 0) + COALESCE(po.purchase_total, 0)) AS budget_variance
FROM projects p
LEFT JOIN (
  SELECT project_id, SUM(amount) AS paid_total
  FROM expenses
  WHERE status = 'paid'
  GROUP BY project_id
) paid ON paid.project_id = p.id
LEFT JOIN (
  SELECT project_id, SUM(amount) AS pending_total
  FROM expenses
  WHERE status = 'pending'
  GROUP BY project_id
) pend ON pend.project_id = p.id
LEFT JOIN (
  SELECT po.project_id, SUM(po.total_amount) AS purchase_total
  FROM purchase_orders po
  WHERE po.project_id IS NOT NULL
    AND po.status IN ('confirmed', 'ready_for_warehouse', 'delivered')
    AND NOT EXISTS (
      SELECT 1 FROM expenses e WHERE e.purchase_order_id = po.id
    )
  GROUP BY po.project_id
) po ON po.project_id = p.id";
}

function runehaRewriteProjectCostsSql(string $sql): string
{
    $derived = '(' . runehaProjectCostsSelectSql() . ')';
    $keywords = 'WHERE|LEFT|RIGHT|INNER|CROSS|JOIN|ON|GROUP|ORDER|LIMIT|UNION|HAVING|SET|AND|OR|ASC|DESC';
    $rewritten = preg_replace_callback(
        '/\bv_project_costs\b(?:\s+(?:AS\s+)?(?!(?:' . $keywords . ')\b)([A-Za-z_][A-Za-z0-9_]*))?/i',
        static function (array $match) use ($derived): string {
            return $derived . ' ' . ($match[1] ?? 'v_project_costs');
        },
        $sql
    );
    return $rewritten ?? $sql;
}

class RunehaPdo extends PDO
{
    private bool $rewriteProjectCosts = false;

    public function enableProjectCostRewrite(bool $enabled): void
    {
        $this->rewriteProjectCosts = $enabled;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = [])
    {
        if ($this->rewriteProjectCosts) {
            $query = runehaRewriteProjectCostsSql($query);
        }
        return parent::prepare($query, $options);
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)
    {
        if ($this->rewriteProjectCosts) {
            $query = runehaRewriteProjectCostsSql($query);
        }
        if ($fetchMode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    #[\ReturnTypeWillChange]
    public function exec(string $statement)
    {
        if ($this->rewriteProjectCosts) {
            $statement = runehaRewriteProjectCostsSql($statement);
        }
        return parent::exec($statement);
    }
}

try {
    $pdo = new RunehaPdo("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $costView = $pdo->query("SHOW FULL TABLES LIKE 'v_project_costs'", PDO::FETCH_NUM)->fetch();
    $pdo->enableProjectCostRewrite(!($costView && strcasecmp((string) ($costView[1] ?? ''), 'VIEW') === 0));
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

ensureRunehaWorkflowSchema();

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . 'login.php');
        exit();
    }
}

function isAdmin() {
    if (!isLoggedIn()) return false;
    return $_SESSION['department'] === 'admin' && $_SESSION['role'] === 'admin';
}

function isManager() {
    if (!isLoggedIn()) return false;
    // Admin is view-only across departments — not a department manager.
    if (isAdmin()) return false;
    return $_SESSION['role'] === 'manager';
}

function hasDepartment($department) {
    if (!isLoggedIn()) return false;
    return $_SESSION['department'] === $department || isAdmin();
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, department, role, status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function logActivity($user_id, $action, $module, $details = null) {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, module, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $module, $details, $ip]);
    } catch(PDOException $e) {
        // Silent fail for logging
    }
}

/** Log the technical SQL error; return a safe message for the UI. */
function userDatabaseError(Throwable $e, $module = 'System') {
    error_log('[Runeha ERP] ' . $e->getMessage());
    logActivity($_SESSION['user_id'] ?? null, 'Database error', $module, $e->getMessage());
    return "We couldn't load this data right now. Please try again or contact support.";
}

function generateNumber($prefix, $table, $column) {
    global $pdo;
    $year = date('Y');
    $month = date('m');
    $seq = nextHyphenSequence($table, $column, $prefix . '-' . $year . $month . '-%', 9999);
    $new_num = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    return "$prefix-$year$month-$new_num";
}

/**
 * Next integer after the last hyphen in codes like PRJ-2026-001 / PO-202609-0001.
 * Never CAST a leading hyphen (which becomes UNSIGNED 18446744073709551615).
 */
function nextHyphenSequence($table, $column, $like, $max_sane = 999999) {
    global $pdo;
    $allowed_tables = [
        'purchase_requests' => 'pr_number',
        'purchase_orders' => 'po_number',
        'invoices' => 'invoice_number',
        'expenses' => 'expense_number',
        'payments' => 'payment_number',
        'quotations' => 'quotation_number',
        'client_quotations' => 'quotation_number',
        'projects' => 'project_code',
        'contracts' => 'contract_number',
        'materials' => 'material_code',
    ];
    if (!isset($allowed_tables[$table]) || $allowed_tables[$table] !== $column) {
        return 1;
    }
    try {
        $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(`$column`, '-', -1) AS UNSIGNED)) AS last_num
                FROM `$table`
                WHERE `$column` LIKE ?
                  AND `$column` NOT LIKE '%E+%'
                  AND `$column` NOT LIKE '%e+%'
                  AND SUBSTRING_INDEX(`$column`, '-', -1) REGEXP '^[0-9]+$'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$like]);
        $last = (int) ($stmt->fetchColumn() ?: 0);
        if ($last < 0 || $last > $max_sane) {
            $last = 0;
        }
        return $last + 1;
    } catch (PDOException $e) {
        return 1;
    }
}

function generateYearlyNumber($prefix, $table, $column) {
    $year = date('Y');
    $seq = nextHyphenSequence($table, $column, $prefix . '-' . $year . '-%', 9999);
    return $prefix . '-' . $year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

function generateProjectCode() {
    $year = date('Y');
    $seq = nextHyphenSequence('projects', 'project_code', "PRJ-$year-%", 999);
    return 'PRJ-' . $year . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

/** Render document codes as plain text; never number-format them. */
function displayDocumentCode($code) {
    $code = trim((string) $code);
    if ($code === '') {
        return 'N/A';
    }
    if (preg_match('/e\+/i', $code)) {
        return preg_replace('/\d+\.?\d*e\+\d+/i', 'invalid', $code);
    }
    return $code;
}

// ============================================
// DEPARTMENT HELPER FUNCTIONS (ONLY HERE)
// ============================================

function getDepartmentName($dept) {
    $departments = [
        'procurement' => 'Procurement Department',
        'engineering' => 'Engineering Department',
        'accounting' => 'Accounting Department',
        'warehouse' => 'Warehouse Department',
        'admin' => 'Administrator'
    ];
    return $departments[$dept] ?? ucfirst($dept);
}

function getDepartmentIcon($dept) {
    $icons = [
        'procurement' => 'fa-shopping-cart',
        'engineering' => 'fa-hard-hat',
        'accounting' => 'fa-calculator',
        'warehouse' => 'fa-warehouse',
        'admin' => 'fa-crown'
    ];
    return $icons[$dept] ?? 'fa-user';
}

function getDepartmentColor($dept) {
    $colors = [
        'procurement' => '#4e73df',
        'engineering' => '#f59e0b',
        'accounting' => '#1cc88a',
        'warehouse' => '#36b9cc',
        'admin' => '#e74a3b'
    ];
    return $colors[$dept] ?? '#858796';
}

function getDepartmentBadge($dept) {
    $badges = [
        'procurement' => 'badge-procurement',
        'engineering' => 'badge-engineering',
        'accounting' => 'badge-accounting',
        'warehouse' => 'badge-warehouse',
        'admin' => 'badge-admin'
    ];
    return $badges[$dept] ?? 'badge-secondary';
}

function ensureRunehaWorkflowSchema() {
    global $pdo;
    static $done = false;
    if ($done || !isset($pdo)) {
        return;
    }
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'in_charge_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN in_charge_id BIGINT UNSIGNED NULL AFTER created_by");
            $pdo->exec("ALTER TABLE projects ADD KEY idx_projects_pic (in_charge_id)");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema in_charge_id: ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE purchase_orders MODIFY status ENUM('draft','sent','confirmed','ready_for_warehouse','delivered','cancelled') NOT NULL DEFAULT 'draft'");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema PO status: ' . $e->getMessage());
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM projects LIKE 'assigned_to'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN assigned_to BIGINT UNSIGNED NULL AFTER created_by");
            $pdo->exec("ALTER TABLE projects ADD KEY idx_projects_assigned_to (assigned_to)");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema assigned_to: ' . $e->getMessage());
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS project_financials (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            contract_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            additional_budget DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            notes TEXT NULL,
            recorded_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_project_financials_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema project_financials: ' . $e->getMessage());
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            purchase_order_id BIGINT UNSIGNED NOT NULL,
            material_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            received_quantity INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_poi_po (purchase_order_id),
            KEY idx_poi_material (material_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema purchase_order_items: ' . $e->getMessage());
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'received_at'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN received_at DATETIME NULL");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema received_at: ' . $e->getMessage());
    }
    require_once __DIR__ . '/../includes/accounting_ledger.php';
    ensureClientAccountingSchema();
    foreach ([
        'source_quotation_id' => 'source_quotation_id BIGINT UNSIGNED NULL',
        'source_contract_id' => 'source_contract_id BIGINT UNSIGNED NULL',
        'quotation_path' => 'quotation_path VARCHAR(255) NULL',
        'quotation_name' => 'quotation_name VARCHAR(255) NULL',
        'subject' => 'subject TEXT NULL',
        'accounting_received_at' => 'accounting_received_at DATETIME NULL',
        'engineering_received_at' => 'engineering_received_at DATETIME NULL',
        'accounting_status' => "accounting_status ENUM('pending','received','quoted','rejected') NOT NULL DEFAULT 'pending'",
    ] as $name => $ddl) {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM projects LIKE " . $pdo->quote($name))->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE projects ADD COLUMN $ddl");
            }
        } catch (PDOException $e) {
            error_log('[Runeha ERP] workflow schema ' . $name . ': ' . $e->getMessage());
        }
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM quotations LIKE 'purchase_request_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE quotations ADD COLUMN purchase_request_id BIGINT UNSIGNED NULL AFTER project_id");
            $pdo->exec("ALTER TABLE quotations ADD KEY idx_quotations_pr (purchase_request_id)");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema quotations.purchase_request_id: ' . $e->getMessage());
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS quotation_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quotation_id BIGINT UNSIGNED NOT NULL,
            purchase_request_item_id BIGINT UNSIGNED NOT NULL,
            material_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY idx_qi_quotation (quotation_id),
            KEY idx_qi_pr_item (purchase_request_item_id),
            KEY idx_qi_material (material_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema quotation_items: ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE purchase_requests MODIFY status ENUM('draft','pending','approved','confirmed','rejected','ordered','received','completed') NOT NULL DEFAULT 'pending'");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema purchase request completed: ' . $e->getMessage());
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS project_quotation_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            unit VARCHAR(50) NULL,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            line_type ENUM('material','labor','other') NOT NULL DEFAULT 'other',
            PRIMARY KEY (id),
            KEY idx_pqi_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] workflow schema project_quotation_items: ' . $e->getMessage());
    }
    syncProjectActualCosts();
}

function nullableDate($value): ?string {
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return null;
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return null;
    }
    return $value;
}

function formatCompactMoney($amount): string
{
    $amount = (float) $amount;
    $sign = $amount < 0 ? '-' : '';
    $abs = abs($amount);
    if ($abs >= 1000000000) {
        return $sign . '₱' . rtrim(rtrim(number_format($abs / 1000000000, 1), '0'), '.') . 'B';
    }
    if ($abs >= 1000000) {
        return $sign . '₱' . rtrim(rtrim(number_format($abs / 1000000, 1), '0'), '.') . 'M';
    }
    return $sign . '₱' . number_format($abs, 2);
}

function formatDisplayDate($value): string {
    $value = nullableDate($value);
    if ($value === null) {
        return 'Not set';
    }
    return date('M d, Y', strtotime($value));
}

function projectPurchaseCostSql(string $projectRef = 'p.id'): string {
    return "COALESCE((SELECT purchase_total FROM v_project_costs WHERE project_id = {$projectRef}), 0)";
}

function projectExpenseCostSql(string $projectRef = 'p.id'): string {
    return "COALESCE((SELECT expense_total FROM v_project_costs WHERE project_id = {$projectRef}), 0)";
}

function projectActualCostSql(string $projectRef = 'p.id'): string {
    return "COALESCE((SELECT actual_cost FROM v_project_costs WHERE project_id = {$projectRef}), 0)";
}

function syncProjectActualCosts(): void {
    // Actual cost is calculated by v_project_costs. It is not written back to projects.
}

function budgetPosition(float $budget, float $actual, float $pending = 0): array
{
    $variance = $budget - $actual;
    return [
        'variance' => $variance,
        'remaining_after_committed' => $budget - $actual - $pending,
        'percent' => $budget > 0 ? ($variance / $budget) * 100 : null,
        'label' => $variance < 0 ? 'Over budget' : 'Under budget',
        'over' => $variance < 0,
    ];
}

function projectCompletionBlocker(PDO $pdo, int $projectId): ?string
{
    $projectStmt = $pdo->prepare("SELECT accounting_status, engineering_received_at FROM projects WHERE id = ?");
    $projectStmt->execute([$projectId]);
    $project = $projectStmt->fetch();
    if (!$project) {
        return 'This project could not be found.';
    }
    if (!in_array($project['accounting_status'], ['received', 'quoted'], true)) {
        return 'This project cannot be completed yet. Accounting has not approved it.';
    }
    if (empty($project['engineering_received_at'])) {
        return 'This project cannot be completed yet. Engineering has not received it.';
    }
    $requestStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requests WHERE project_id = ?");
    $requestStmt->execute([$projectId]);
    if ((int) $requestStmt->fetchColumn() < 1) {
        return 'This project cannot be completed yet. Engineering has not sent a material request to Procurement.';
    }
    $orderStmt = $pdo->prepare("
        SELECT po.id, po.status, po.received_at,
               (SELECT e.status FROM expenses e WHERE e.purchase_order_id = po.id ORDER BY e.id DESC LIMIT 1) AS expense_status
        FROM purchase_orders po
        LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        WHERE COALESCE(po.project_id, pr.project_id) = ?
          AND po.status NOT IN ('draft', 'cancelled')
    ");
    $orderStmt->execute([$projectId]);
    $orders = $orderStmt->fetchAll();
    if (!$orders) {
        return 'This project cannot be completed yet. Procurement has not created a purchase order.';
    }
    foreach ($orders as $order) {
        if (($order['expense_status'] ?? '') !== 'paid') {
            return 'This project cannot be completed yet. Accounting has not marked the materials as paid.';
        }
    }
    foreach ($orders as $order) {
        if ($order['status'] !== 'delivered' || empty($order['received_at'])) {
            return 'This project cannot be completed yet. Warehouse has not received the materials.';
        }
    }
    $releaseStmt = $pdo->prepare("SELECT COUNT(*) FROM material_requests WHERE project_id = ? AND flow_status IN ('released', 'complete')");
    $releaseStmt->execute([$projectId]);
    if ((int) $releaseStmt->fetchColumn() < 1) {
        return 'This project cannot be completed yet. Warehouse has not released the materials to Engineering.';
    }
    return null;
}

function accountingDecisionLabel(?string $status): string
{
    if ($status === 'rejected') {
        return 'rejected';
    }
    if (in_array($status, ['received', 'quoted'], true)) {
        return 'approved';
    }
    return 'pending';
}

function accountingDecisionView(string $decision): string
{
    $html = '<span style="display:inline-flex;gap:0.25rem;flex-wrap:wrap;">';
    foreach (['pending' => ['Pending', 'btn-warning'], 'approved' => ['Approved', 'btn-success'], 'rejected' => ['Rejected', 'btn-danger']] as $value => $item) {
        $on = $decision === $value;
        $html .= '<span class="btn btn-sm ' . ($on ? $item[1] : 'btn-outline') . '"' . ($on ? '' : ' style="opacity:.45;"') . '>' . $item[0] . '</span>';
    }
    return $html . '</span>';
}

function recallUnreleasedAccountingHandoff(PDO $pdo, int $projectId): void
{
    $pdo->prepare("UPDATE material_requests SET flow_status = 'accounting' WHERE project_id = ? AND flow_status = 'warehouse' AND status = 'pending'")
        ->execute([$projectId]);
    $pdo->prepare("
        UPDATE purchase_orders po
        INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        SET po.status = 'sent'
        WHERE pr.project_id = ? AND po.status = 'ready_for_warehouse' AND po.received_at IS NULL
    ")->execute([$projectId]);
}

function setAccountingProjectDecision(PDO $pdo, int $projectId, string $decision): string
{
    if ($projectId <= 0 || !in_array($decision, ['pending', 'approved', 'rejected'], true)) {
        throw new RuntimeException('Choose Pending, Approved, or Rejected.');
    }

    if ($decision === 'approved') {
        $stmt = $pdo->prepare("UPDATE projects SET accounting_received_at = COALESCE(accounting_received_at, NOW()), accounting_status = 'received' WHERE id = ? AND accounting_status <> 'received'");
        $stmt->execute([$projectId]);
        if ($stmt->rowCount() < 1) {
            return 'This project is already approved.';
        }
        $pdo->prepare("UPDATE material_requests SET flow_status = 'warehouse' WHERE project_id = ? AND flow_status = 'accounting'")->execute([$projectId]);
        $pdo->prepare("UPDATE purchase_orders po INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id SET po.status = 'ready_for_warehouse' WHERE pr.project_id = ? AND po.status IN ('sent', 'confirmed')")->execute([$projectId]);
        notifyAccountingReceived($projectId);
        return 'Project approved. Warehouse can release materials that are already in stock.';
    }

    if ($decision === 'rejected') {
        $stmt = $pdo->prepare("UPDATE projects SET accounting_status = 'rejected' WHERE id = ? AND accounting_status <> 'rejected'");
        $stmt->execute([$projectId]);
        recallUnreleasedAccountingHandoff($pdo, $projectId);
        if ($stmt->rowCount() < 1) {
            return 'This project is already rejected.';
        }
        notifyProjectRejected($projectId);
        return 'Project rejected. Unreleased materials were returned to Accounting.';
    }

    $stmt = $pdo->prepare("UPDATE projects SET accounting_status = 'pending', accounting_received_at = NULL WHERE id = ?");
    $stmt->execute([$projectId]);
    $check = $pdo->prepare("SELECT accounting_status FROM projects WHERE id = ?");
    $check->execute([$projectId]);
    if ((string) $check->fetchColumn() !== 'pending') {
        throw new RuntimeException('The project could not be set back to pending.');
    }
    recallUnreleasedAccountingHandoff($pdo, $projectId);
    return 'Project set back to pending.';
}

function syncDepartmentWorkflow(PDO $pdo): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;
    $root = dirname(__DIR__);
    if (!function_exists('syncPurchasedMaterialsToAccounting')) {
        require_once $root . '/includes/accounting_ledger.php';
    }
    if (!function_exists('syncReceivedOrdersForRelease')) {
        require_once $root . '/includes/warehouse_release.php';
    }
    syncPurchasedMaterialsToAccounting($pdo);
    try {
        syncReceivedOrdersForRelease($pdo);
    } catch (Throwable $e) {
        error_log('[Runeha ERP] release sync: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/notifications.php';