<?php

function adjustmentTypeOptions(): array
{
    return ['Amount correction', 'Quantity correction', 'Stock count', 'Budget change', 'Other'];
}

function adjustmentReferenceTypes(): array
{
    return [
        'expense' => 'Expense',
        'invoice' => 'Invoice',
        'purchase_order' => 'Purchase order',
        'stock_item' => 'Stock item',
        'progress_update' => 'Progress update',
        'project' => 'Project',
    ];
}

function adjustmentReferenceTypesFor(string $department, bool $admin): array
{
    if ($admin || $department === 'admin') {
        return adjustmentReferenceTypes();
    }
    $allowed = [
        'accounting' => ['expense', 'invoice', 'project'],
        'procurement' => ['purchase_order', 'project'],
        'warehouse' => ['stock_item', 'project'],
        'engineering' => ['progress_update', 'project'],
    ];
    $types = adjustmentReferenceTypes();
    $keys = $allowed[$department] ?? ['project'];
    return array_intersect_key($types, array_flip($keys));
}

function adjustmentLoadReference(PDO $pdo, string $type, int $id): ?array
{
    if ($id <= 0 || !isset(adjustmentReferenceTypes()[$type])) {
        return null;
    }
    $sql = [
        'expense' => "SELECT id, CONCAT(expense_number, ' — ', COALESCE(description, '')) AS label, amount, NULL AS qty, project_id FROM expenses WHERE id = ?",
        'invoice' => "SELECT id, CONCAT(invoice_number, ' — ', COALESCE(client_name, client, '')) AS label, amount, NULL AS qty, project_id FROM invoices WHERE id = ?",
        'purchase_order' => "SELECT id, po_number AS label, total_amount AS amount, NULL AS qty, project_id FROM purchase_orders WHERE id = ?",
        'stock_item' => "SELECT id, CONCAT(material_code, ' — ', name) AS label, NULL AS amount, current_stock AS qty, NULL AS project_id FROM materials WHERE id = ?",
        'progress_update' => "SELECT id, description AS label, NULL AS amount, completion_percentage AS qty, project_id FROM accomplishments WHERE id = ?",
        'project' => "SELECT id, CONCAT(project_code, ' — ', name) AS label, estimated_budget AS amount, NULL AS qty, id AS project_id FROM projects WHERE id = ?",
    ];
    $stmt = $pdo->prepare($sql[$type]);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $projectId = (int) ($row['project_id'] ?? 0);
    if ($projectId > 0) {
        $status = $pdo->prepare("SELECT status FROM projects WHERE id = ?");
        $status->execute([$projectId]);
        if ($status->fetchColumn() === 'completed') {
            return null;
        }
    }
    return [
        'id' => (int) $row['id'],
        'label' => trim((string) $row['label']),
        'amount' => $row['amount'] === null ? null : (float) $row['amount'],
        'qty' => $row['qty'] === null ? null : (float) $row['qty'],
        'project_id' => (int) ($row['project_id'] ?? 0),
    ];
}

function adjustmentSearchReferences(PDO $pdo, string $type, string $q): array
{
    if (!isset(adjustmentReferenceTypes()[$type])) {
        return [];
    }
    $like = '%' . trim($q) . '%';
    $sql = [
        'expense' => "SELECT id FROM expenses WHERE expense_number LIKE ? OR description LIKE ? ORDER BY id DESC LIMIT 40",
        'invoice' => "SELECT id FROM invoices WHERE invoice_number LIKE ? OR client LIKE ? OR client_name LIKE ? ORDER BY id DESC LIMIT 40",
        'purchase_order' => "SELECT id FROM purchase_orders WHERE po_number LIKE ? OR supplier LIKE ? ORDER BY id DESC LIMIT 40",
        'stock_item' => "SELECT id FROM materials WHERE material_code LIKE ? OR name LIKE ? ORDER BY name LIMIT 40",
        'progress_update' => "SELECT id FROM accomplishments WHERE description LIKE ? ORDER BY id DESC LIMIT 40",
        'project' => "SELECT id FROM projects WHERE project_code LIKE ? OR name LIKE ? ORDER BY project_code LIMIT 40",
    ];
    $stmt = $pdo->prepare($sql[$type]);
    $params = $type === 'progress_update' ? [$like] : ($type === 'invoice' ? [$like, $like, $like] : [$like, $like]);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $hit) {
        $loaded = adjustmentLoadReference($pdo, $type, (int) $hit['id']);
        if ($loaded) {
            $rows[] = $loaded;
        }
    }
    return $rows;
}
