<?php

function fundRequestAge(string $requestedAt, string $status = 'pending'): string {
    $days = (int) floor((time() - strtotime($requestedAt)) / 86400);
    $label = ucfirst($status);
    if ($status !== 'pending') {
        return $label;
    }
    if ($days <= 0) {
        return 'Pending today';
    }
    return 'Pending ' . $days . ' day' . ($days === 1 ? '' : 's');
}

function fundRequestCommitted(PDO $pdo, int $projectId, int $exceptId = 0): float {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM fund_requests WHERE project_id = ? AND status IN ('pending', 'approved') AND id <> ?");
    $stmt->execute([$projectId, $exceptId]);
    return round((float) $stmt->fetchColumn(), 2);
}

function fundRequestProject(PDO $pdo, int $projectId): ?array {
    $stmt = $pdo->prepare("SELECT id, project_code, name, status, estimated_budget, archived_at FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function assertFundRequestAllowed(PDO $pdo, int $projectId, string $amountRaw, string $purpose, int $exceptId = 0): array {
    $purpose = trim($purpose);
    $amountRaw = trim($amountRaw);
    if (mb_strlen($purpose) < 5) {
        throw new RuntimeException('Purpose is required and must be at least 5 characters.');
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw) || (float) $amountRaw <= 0) {
        throw new RuntimeException('Amount must be greater than 0 and use at most 2 decimal places.');
    }
    $amount = round((float) $amountRaw, 2);
    $project = fundRequestProject($pdo, $projectId);
    if (!$project || $project['archived_at']) {
        throw new RuntimeException('Choose a project that is not archived.');
    }
    if ($project['status'] === 'completed') {
        throw new RuntimeException('This project is completed. Funds can no longer be requested.');
    }
    $budget = round((float) $project['estimated_budget'], 2);
    $used = fundRequestCommitted($pdo, $projectId, $exceptId);
    $remaining = round($budget - $used, 2);
    if ($amount > $remaining) {
        throw new RuntimeException('This request exceeds the remaining requestable amount of ₱' . number_format($remaining, 2) . '. Pending and approved requests cannot exceed the estimated budget.');
    }
    return [$project, $amount, $purpose, $remaining];
}

function cancelPendingFundRequests(PDO $pdo, int $projectId): int {
    $rows = $pdo->prepare("SELECT id, request_number, project_id FROM fund_requests WHERE project_id = ? AND status = 'pending'");
    $rows->execute([$projectId]);
    $pending = $rows->fetchAll();
    if (!$pending) {
        return 0;
    }
    $pdo->prepare("UPDATE fund_requests SET status = 'cancelled', decision_reason = ?, decided_at = NOW() WHERE project_id = ? AND status = 'pending'")
        ->execute(['Cancelled because the project was completed.', $projectId]);
    $project = fundRequestProject($pdo, $projectId);
    $code = $project['project_code'] ?? ('project ' . $projectId);
    foreach ($pending as $row) {
        $message = $row['request_number'] . ' for ' . $code . ' was cancelled because the project was completed.';
        notify('admin', 'fund_request_cancelled', 'Fund request cancelled', $message, 'modules/admin/fund_requests.php', $projectId, 'fund_request', (int) $row['id']);
        notify('accounting', 'fund_request_cancelled', 'Fund request cancelled', $message, 'modules/accounting/fund_requests.php', $projectId, 'fund_request', (int) $row['id']);
        if (function_exists('writeAudit')) {
            writeAudit('Cancel fund request', 'Projects', 'fund_requests', (int) $row['id'], ['status' => 'pending'], ['status' => 'cancelled'], 'Project completed');
        }
    }
    return count($pending);
}

function applyProjectStatus(PDO $pdo, int $projectId, string $status): void {
    $current = $pdo->prepare("SELECT status FROM projects WHERE id = ?");
    $current->execute([$projectId]);
    $previous = (string) $current->fetchColumn();
    if ($previous === '') {
        throw new RuntimeException('Project not found.');
    }
    if ($status === 'completed' && $previous !== 'completed') {
        $pdo->prepare("UPDATE projects SET status = 'completed', completion_date = CURDATE() WHERE id = ?")->execute([$projectId]);
        cancelPendingFundRequests($pdo, $projectId);
        if (!function_exists('notify')) {
            require_once __DIR__ . '/notifications.php';
        }
        $info = $pdo->prepare("SELECT project_code, name FROM projects WHERE id = ?");
        $info->execute([$projectId]);
        $project = $info->fetch() ?: ['project_code' => 'Project', 'name' => ''];
        $code = trim((string) $project['project_code']);
        $name = trim((string) $project['name']);
        $label = $name !== '' ? $code . ' — ' . $name : $code;
        notify(
            'admin',
            'project_completed',
            'Project completed',
            $label . ' is completed.',
            'modules/projects/project_details.php?id=' . $projectId,
            $projectId,
            'project',
            $projectId
        );
        return;
    }
    $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?")->execute([$status, $projectId]);
}
