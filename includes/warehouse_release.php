<?php

require_once __DIR__ . '/warehouse_fifo.php';

function releasePendingProjectMaterials(PDO $pdo, int $projectId, int $userId): array
{
    if ($projectId <= 0) {
        throw new InvalidArgumentException('Select the project site for this stock-out.');
    }

    ensureWarehouseFifoBatches($pdo);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT mr.id, mr.material_id, mr.quantity, mr.project_id, mr.requested_by,
                   m.name AS material_name, m.unit, m.current_stock, m.min_stock,
                   p.project_code, p.name AS project_name, p.location AS project_location,
                   pr.pr_number
            FROM material_requests mr
            INNER JOIN materials m ON m.id = mr.material_id
            INNER JOIN projects p ON p.id = mr.project_id
            LEFT JOIN purchase_requests pr ON pr.id = mr.purchase_request_id
            WHERE mr.project_id = ? AND mr.status = 'pending' AND mr.flow_status = 'warehouse'
            ORDER BY mr.id
            FOR UPDATE
        ");
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            $pdo->rollBack();
            throw new RuntimeException('There is no material request waiting for release on this project.');
        }

        $projectLabel = trim(($rows[0]['project_code'] ?? '') . ' ' . ($rows[0]['project_name'] ?? ''));
        $location = trim((string) ($rows[0]['project_location'] ?? ''));
        $reason = 'Released to project site ' . ($projectLabel !== '' ? $projectLabel : ('#' . $projectId));
        if ($location !== '') {
            $reason .= ' @ ' . $location;
        }
        $running = [];
        $released = [];
        $short = [];
        $alerts = [];

        $deduct = $pdo->prepare("UPDATE materials SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
        $mark = $pdo->prepare("UPDATE material_requests SET status = 'approved', released_quantity = ?, released_by = ?, released_at = NOW() WHERE id = ? AND status = 'pending'");

        foreach ($rows as $row) {
            $materialId = (int) $row['material_id'];
            $qty = (int) $row['quantity'];
            if (!isset($running[$materialId])) {
                $running[$materialId] = (int) $row['current_stock'];
            }
            if ($qty <= 0 || $running[$materialId] < $qty) {
                $short[] = [
                    'name' => $row['material_name'],
                    'unit' => $row['unit'],
                    'need' => $qty,
                    'stock' => $running[$materialId],
                ];
                continue;
            }

            $deduct->execute([$qty, $materialId, $qty]);
            if ($deduct->rowCount() < 1) {
                $short[] = [
                    'name' => $row['material_name'],
                    'unit' => $row['unit'],
                    'need' => $qty,
                    'stock' => $running[$materialId],
                ];
                continue;
            }

            $previous = $running[$materialId];
            $running[$materialId] -= $qty;
            $batches = warehouseIssueFifo($pdo, $materialId, $qty, $projectId, $reason, $userId);
            $mark->execute([$qty, $userId, (int) $row['id']]);
            $pdo->prepare("UPDATE material_requests SET flow_status = 'released' WHERE id = ? AND flow_status = 'warehouse'")->execute([(int) $row['id']]);
            $pdo->prepare("INSERT INTO warehouse_releases (material_request_id, project_id, engineer_id, quantity, status, released_by) VALUES (?, ?, ?, ?, 'released', ?)")
                ->execute([(int) $row['id'], $projectId, (int) $row['requested_by'], $qty, $userId]);
            $ref = $row['pr_number'] ?: ('request #' . (int) $row['id']);
            notify((int) $row['requested_by'], 'materials_released', 'Materials released', 'Materials for ' . $ref . ' have been released. Please confirm receipt.', 'modules/projects/material_requirements.php', $projectId, 'material_request', (int) $row['id']);

            $minStock = (float) $row['min_stock'];
            $newStock = $previous - $qty;
            if ($minStock > 0 && $previous > $minStock && $newStock <= $minStock) {
                $alerts[] = [
                    'name' => $row['material_name'],
                    'stock' => $newStock,
                    'min' => $minStock,
                    'unit' => $row['unit'] ?? 'pcs',
                ];
            }
            $released[] = $row['material_name'] . ' ' . number_format($qty) . ' ' . ($row['unit'] ?? '') . ' (' . implode(', ', $batches) . ')';
        }

        if (!$released) {
            $pdo->rollBack();
            $names = implode(', ', array_map(static function ($item) {
                return $item['name'] . ' (needed ' . number_format($item['need']) . ', stock ' . number_format($item['stock']) . ')';
            }, $short));
            throw new RuntimeException('Nothing was released. Stock is short: ' . $names);
        }

        $pdo->commit();
        return [
            'project_label' => $projectLabel,
            'released' => $released,
            'short' => $short,
            'alerts' => $alerts,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function syncReceivedOrdersForRelease(PDO $pdo): void
{
    $table = $pdo->query("SHOW TABLES LIKE 'material_requests'")->fetch();
    if (!$table) {
        return;
    }
    $col = $pdo->query("SHOW COLUMNS FROM material_requests LIKE 'purchase_order_item_id'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE material_requests ADD COLUMN purchase_order_item_id BIGINT UNSIGNED NULL");
        $pdo->exec("ALTER TABLE material_requests ADD UNIQUE KEY uq_mr_po_item (purchase_order_item_id)");
    }
    $rows = $pdo->query("
        SELECT poi.id AS po_item_id, poi.material_id,
               GREATEST(COALESCE(poi.received_quantity, 0), 0) AS received_qty,
               pr.id AS purchase_request_id, pr.project_id, pr.requestor_id, pr.created_by, pr.pr_number,
               COALESCE(DATE(po.received_at), po.order_date, CURDATE()) AS request_date,
               po.po_number
        FROM purchase_order_items poi
        INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
        INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        WHERE pr.project_id IS NOT NULL
          AND COALESCE(poi.received_quantity, 0) > 0
          AND NOT EXISTS (
              SELECT 1 FROM material_requests mr WHERE mr.purchase_order_item_id = poi.id
          )
          AND NOT EXISTS (
              SELECT 1 FROM material_requests mr
              WHERE mr.project_id = pr.project_id
                AND mr.material_id = poi.material_id
                AND mr.purchase_request_id = pr.id
          )
    ")->fetchAll();
    if (!$rows) {
        return;
    }
    $insert = $pdo->prepare("INSERT INTO material_requests
        (project_id, material_id, quantity, request_date, status, flow_status, requested_by, notes, purchase_request_id, purchase_order_item_id)
        VALUES (?, ?, ?, ?, 'pending', 'warehouse', ?, ?, ?, ?)");
    foreach ($rows as $row) {
        $engineerId = (int) ($row['requestor_id'] ?: $row['created_by']);
        $qty = (int) $row['received_qty'];
        if ($engineerId <= 0 || $qty <= 0) {
            continue;
        }
        $notes = 'Engineering request ' . $row['pr_number'] . ', ordered and received on ' . $row['po_number'] . '.';
        $insert->execute([
            (int) $row['project_id'],
            (int) $row['material_id'],
            $qty,
            $row['request_date'],
            $engineerId,
            $notes,
            (int) $row['purchase_request_id'],
            (int) $row['po_item_id'],
        ]);
    }
}
