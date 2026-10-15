<?php

function notify($roleOrUser, string $type, string $title, string $message, ?string $link = null, ?int $projectId = null, ?string $referenceType = null, ?int $referenceId = null): void
{
    global $pdo;
    if (!isset($pdo)) {
        return;
    }
    $userId = null;
    $role = null;
    if (is_int($roleOrUser) || (is_string($roleOrUser) && ctype_digit($roleOrUser))) {
        $userId = (int) $roleOrUser;
        if ($userId <= 0) {
            return;
        }
    } else {
        $role = strtolower(trim((string) $roleOrUser));
        if ($role === '') {
            return;
        }
    }
    try {
        $dup = $pdo->prepare("
            SELECT notification_id FROM notifications
            WHERE type = ? AND is_read = 0
              AND ((? IS NULL AND user_id IS NULL) OR user_id = ?)
              AND ((? IS NULL AND role IS NULL) OR role = ?)
              AND ((? IS NULL AND project_id IS NULL) OR project_id = ?)
              AND ((? IS NULL AND reference_id IS NULL) OR reference_id = ?)
            LIMIT 1
        ");
        $dup->execute([$type, $userId, $userId, $role, $role, $projectId, $projectId, $referenceId, $referenceId]);
        if ($dup->fetch()) {
            return;
        }
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, role, type, title, message, project_id, reference_type, reference_id, link_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $role,
            $type,
            mb_substr($title, 0, 200),
            mb_substr($message, 0, 500),
            $projectId,
            $referenceType,
            $referenceId,
            $link,
        ]);
    } catch (PDOException $e) {
        error_log('[Runeha ERP] notify: ' . $e->getMessage());
    }
}

function notificationScopeSql(): string
{
    return '((user_id IS NOT NULL AND user_id = :uid) OR (role IS NOT NULL AND role = :dept))';
}

function notifyProjectCode(int $projectId): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT project_code, in_charge_id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    return $row ?: ['project_code' => 'PRJ', 'in_charge_id' => 0];
}

function notifyAccountingReceived(int $projectId): void
{
    $project = notifyProjectCode($projectId);
    $code = $project['project_code'];
    $message = 'Accounting has received ' . $code . '.';
    notify('admin', 'project_received', 'Project received', $message, 'modules/admin/project_monitoring.php', $projectId, 'project', $projectId);
    if ((int) $project['in_charge_id'] > 0) {
        notify((int) $project['in_charge_id'], 'project_received', 'Project received', $message, 'modules/projects/project_details.php?id=' . $projectId, $projectId, 'project', $projectId);
    }
    notify('warehouse', 'sent_to_warehouse', 'Sent to Warehouse', 'Accounting has sent ' . $code . ' to the Warehouse.', 'modules/warehouse/material_requests.php', $projectId, 'project', $projectId);
}

function notifyProjectRejected(int $projectId): void
{
    $project = notifyProjectCode($projectId);
    $message = 'Accounting rejected the financial record for ' . $project['project_code'] . '.';
    notify('admin', 'project_rejected', 'Project rejected', $message, 'modules/admin/project_monitoring.php', $projectId, 'project', $projectId);
    notify('engineering', 'project_rejected', 'Project rejected', $message, 'modules/projects/project_details.php?id=' . $projectId, $projectId, 'project', $projectId);
}

function scanOperationalAlerts(): void
{
    global $pdo;
    if (!isset($pdo) || !function_exists('isLoggedIn') || !isLoggedIn()) {
        return;
    }
    try {
        $low = $pdo->query("SELECT id, name, current_stock, min_stock, unit FROM materials WHERE min_stock > 0 AND current_stock <= min_stock");
        foreach ($low as $row) {
            $message = $row['name'] . ' is at ' . $row['current_stock'] . ' ' . ($row['unit'] ?: 'pcs') . ' (reorder level ' . $row['min_stock'] . ').';
            notify('warehouse', 'low_stock', 'Low stock', $message, 'modules/warehouse/stock.php', null, 'material', (int) $row['id']);
            notify('procurement', 'low_stock', 'Low stock', $message, 'modules/procurement/purchase_requests.php', null, 'material', (int) $row['id']);
        }
        $over = $pdo->query("SELECT project_id, project_code, actual_cost, estimated_budget FROM v_project_costs WHERE actual_cost > estimated_budget");
        foreach ($over as $row) {
            $message = $row['project_code'] . ' actual cost exceeds the estimated budget.';
            notify('admin', 'budget_over', 'Budget exceeded', $message, 'modules/admin/project_monitoring.php', (int) $row['project_id'], 'project', (int) $row['project_id']);
            notify('accounting', 'budget_over', 'Budget exceeded', $message, 'modules/accounting/project_financials.php', (int) $row['project_id'], 'project', (int) $row['project_id']);
        }
        $late = $pdo->query("SELECT id, project_code, in_charge_id FROM projects WHERE end_date IS NOT NULL AND end_date < CURDATE() AND status <> 'completed'");
        foreach ($late as $row) {
            $message = $row['project_code'] . ' is past its end date and is not Completed.';
            notify('admin', 'overdue', 'Project overdue', $message, 'modules/admin/project_monitoring.php', (int) $row['id'], 'project', (int) $row['id']);
            if ((int) $row['in_charge_id'] > 0) {
                notify((int) $row['in_charge_id'], 'overdue', 'Project overdue', $message, 'modules/projects/project_details.php?id=' . (int) $row['id'], (int) $row['id'], 'project', (int) $row['id']);
            }
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] alert scan: ' . $e->getMessage());
    }
}
