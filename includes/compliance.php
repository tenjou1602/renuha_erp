<?php
/**
 * Shared adviser controls: audit, reminders, invoice status, adjustments, month close.
 * New tables only. Existing rows are not updated here.
 */

if (!function_exists('ensureComplianceSchema')) {
    function ensureComplianceSchema(): void {
        global $pdo;
        static $done = false;
        if ($done || !isset($pdo)) {
            return;
        }
        $done = true;
        $statements = [
            "CREATE TABLE IF NOT EXISTS email_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient VARCHAR(200) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                reference_key VARCHAR(190) NOT NULL,
                period_key VARCHAR(40) NOT NULL,
                status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
                error_text VARCHAR(500) NULL,
                sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_email_once (reference_key, period_key, recipient)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                role VARCHAR(40) NULL,
                acting_as_admin TINYINT(1) NOT NULL DEFAULT 0,
                action VARCHAR(80) NOT NULL,
                module VARCHAR(40) NOT NULL,
                table_name VARCHAR(80) NULL,
                record_id BIGINT UNSIGNED NULL,
                old_values LONGTEXT NULL,
                new_values LONGTEXT NULL,
                reason VARCHAR(500) NULL,
                ip_address VARCHAR(45) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_audit_module (module, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS status_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                table_name VARCHAR(80) NOT NULL,
                record_id BIGINT UNSIGNED NOT NULL,
                old_status VARCHAR(40) NULL,
                new_status VARCHAR(40) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_status_record (table_name, record_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS adjustments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                department VARCHAR(40) NOT NULL,
                period CHAR(7) NOT NULL,
                type VARCHAR(80) NOT NULL,
                reference_type VARCHAR(80) NULL,
                reference_id BIGINT UNSIGNED NULL,
                project_id BIGINT UNSIGNED NULL,
                amount_before DECIMAL(15,2) NULL,
                amount_after DECIMAL(15,2) NULL,
                qty_before DECIMAL(15,2) NULL,
                qty_after DECIMAL(15,2) NULL,
                difference DECIMAL(15,2) NULL,
                reason VARCHAR(500) NOT NULL,
                status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
                created_by BIGINT UNSIGNED NULL,
                approved_by BIGINT UNSIGNED NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_adj_dept (department, period, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS month_closures (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                department VARCHAR(40) NOT NULL,
                period CHAR(7) NOT NULL,
                closed_by BIGINT UNSIGNED NULL,
                closed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_month_close (department, period)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS invoice_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                invoice_id BIGINT UNSIGNED NOT NULL,
                description VARCHAR(255) NOT NULL,
                qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
                unit VARCHAR(50) NULL,
                unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                PRIMARY KEY (id),
                KEY idx_invoice_items (invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "ALTER TABLE projects ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL",
            "ALTER TABLE invoices ADD COLUMN IF NOT EXISTS tax DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "ALTER TABLE invoices ADD COLUMN IF NOT EXISTS description TEXT NULL",
            "ALTER TABLE invoices ADD COLUMN IF NOT EXISTS billing_date DATE NULL",
            "ALTER TABLE adjustments ADD COLUMN IF NOT EXISTS source_period CHAR(7) NULL",
        ];
        foreach ($statements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                error_log('[Runeha ERP] compliance schema: ' . $e->getMessage());
            }
        }
    }

    function assertNotOwnApproval($ownerId): void {
        if (function_exists('ownsRecord') && ownsRecord($ownerId)) {
            throw new RuntimeException('You cannot approve or mark as paid an item you submitted.');
        }
    }

    function writeAudit(string $action, string $module, ?string $table, ?int $recordId, $old, $new, ?string $reason = null): void {
        global $pdo;
        if (!isset($pdo)) {
            return;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, role, acting_as_admin, action, module, table_name, record_id, old_values, new_values, reason, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $_SESSION['role'] ?? null,
                (function_exists('isAdmin') && isAdmin()) ? 1 : 0,
                $action,
                $module,
                $table,
                $recordId,
                $old === null ? null : json_encode($old),
                $new === null ? null : json_encode($new),
                $reason,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[Runeha ERP] audit: ' . $e->getMessage());
        }
    }

    function logStatusChange(string $table, int $recordId, ?string $old, string $new): void {
        global $pdo;
        if (!isset($pdo) || $old === $new) {
            return;
        }
        try {
            $pdo->prepare("INSERT INTO status_history (table_name, record_id, old_status, new_status, user_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$table, $recordId, $old, $new, $_SESSION['user_id'] ?? null]);
        } catch (Throwable $e) {
            error_log('[Runeha ERP] status history: ' . $e->getMessage());
        }
    }

    function monthIsClosed(string $department, string $period): bool {
        global $pdo;
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT id FROM month_closures WHERE department = ? AND period = ?");
            $stmt->execute([$department, $period]);
            return (bool) $stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }

    function assertMonthOpen(string $department, ?string $date): void {
        $date = $date ?: date('Y-m-d');
        $period = substr($date, 0, 7);
        if (monthIsClosed($department, $period)) {
            throw new RuntimeException('That month is closed. Record an approved adjustment in the open month instead of editing it directly.');
        }
    }

    function invoiceCollectedTotal(PDO $pdo): float {
        try {
            return (float) $pdo->query("SELECT COALESCE(SUM(p.amount), 0) FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE i.status <> 'cancelled'")->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    function invoiceDisplayStatus(array $invoice): string {
        $status = (string) ($invoice['status'] ?? 'draft');
        if (in_array($status, ['sent', 'partial'], true) && !empty($invoice['due_date']) && $invoice['due_date'] < date('Y-m-d')) {
            return 'overdue';
        }
        return $status === 'partial' ? 'partially_paid' : $status;
    }

    function invoiceStatusLabel(string $status): string {
        $map = [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'partial' => 'Partially Paid',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];
        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    function adjustmentFigures(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        global $pdo;
        $cache = ['by_dept' => [], 'count_by_dept' => [], 'by_project' => [], 'qty_by_dept' => []];
        if (!isset($pdo)) {
            return $cache;
        }
        try {
            $rows = $pdo->query("SELECT department, project_id, amount_before, amount_after, qty_before, qty_after, difference FROM adjustments WHERE status = 'approved'")->fetchAll();
            foreach ($rows as $row) {
                $dept = (string) $row['department'];
                $money = ($row['amount_before'] !== null && $row['amount_after'] !== null) ? (float) $row['difference'] : 0.0;
                $qty = ($row['qty_before'] !== null && $row['qty_after'] !== null) ? ((float) $row['qty_after'] - (float) $row['qty_before']) : 0.0;
                $cache['by_dept'][$dept] = ($cache['by_dept'][$dept] ?? 0) + $money;
                $cache['qty_by_dept'][$dept] = ($cache['qty_by_dept'][$dept] ?? 0) + $qty;
                $cache['count_by_dept'][$dept] = ($cache['count_by_dept'][$dept] ?? 0) + 1;
                if (!empty($row['project_id'])) {
                    $key = $dept . ':' . (int) $row['project_id'];
                    $cache['by_project'][$key] = ($cache['by_project'][$key] ?? 0) + $money;
                }
            }
        } catch (Throwable $e) {
            error_log('[Runeha ERP] adjustments: ' . $e->getMessage());
        }
        return $cache;
    }

    function reportedDepartmentMoney(float $original, string $department): float {
        $figures = adjustmentFigures();
        return $original + (float) ($figures['by_dept'][$department] ?? 0);
    }

    function reportedActualCost(float $original, ?int $projectId = null): float {
        $figures = adjustmentFigures();
        if ($projectId) {
            return $original
                + (float) ($figures['by_project']['accounting:' . $projectId] ?? 0)
                + (float) ($figures['by_project']['procurement:' . $projectId] ?? 0);
        }
        return $original
            + (float) ($figures['by_dept']['accounting'] ?? 0)
            + (float) ($figures['by_dept']['procurement'] ?? 0);
    }

    function adjustmentIncludedLabel(string $department): string {
        $count = (int) (adjustmentFigures()['count_by_dept'][$department] ?? 0);
        if ($count < 1 || !defined('APP_URL')) {
            return '';
        }
        $href = APP_URL . 'modules/adjustments/index.php?department=' . urlencode($department);
        return ' <a class="badge badge-pending" href="' . htmlspecialchars($href) . '">includes ' . $count . ' adjustments</a>';
    }

    function resolveAdjustmentPeriod(string $department, string $requested): array {
        if (!monthIsClosed($department, $requested)) {
            return [$requested, null];
        }
        $open = date('Y-m');
        if ($open === $requested || monthIsClosed($department, $open)) {
            throw new RuntimeException('That month is closed. Record the adjustment in an open month.');
        }
        return [$open, $requested];
    }

    function pendingWaitBadge(?string $since): string {
        if (!$since) {
            return '';
        }
        $stamp = strtotime($since);
        if (!$stamp) {
            return '';
        }
        $hours = (int) floor((time() - $stamp) / 3600);
        if ($hours < 24) {
            $label = $hours < 1 ? 'Pending less than 1 hour' : ('Pending ' . $hours . ' hour' . ($hours === 1 ? '' : 's'));
            return ' <span class="badge badge-pending">' . htmlspecialchars($label) . '</span>';
        }
        $days = max(1, (int) floor($hours / 24));
        $label = 'Pending ' . $days . ' day' . ($days === 1 ? '' : 's');
        return ' <span class="badge badge-waiting">' . htmlspecialchars($label) . '</span>';
    }

    function statusHistoryRows(string $table, int $recordId): array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT h.*, u.full_name FROM status_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.table_name = ? AND h.record_id = ? ORDER BY h.id DESC");
            $stmt->execute([$table, $recordId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

ensureComplianceSchema();
