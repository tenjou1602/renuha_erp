<?php

require_once __DIR__ . '/warehouse_fifo.php';

function ensureWarehouseCheckerColumns(PDO $pdo): void
{
    try {
        $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS received_by_name VARCHAR(150) NULL");
    } catch (Throwable $e) {
        error_log('[Runeha ERP] received_by_name: ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE material_requests ADD COLUMN IF NOT EXISTS released_by_name VARCHAR(150) NULL");
    } catch (Throwable $e) {
        error_log('[Runeha ERP] released_by_name: ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE warehouse_releases ADD COLUMN IF NOT EXISTS released_by_name VARCHAR(150) NULL");
    } catch (Throwable $e) {
        error_log('[Runeha ERP] warehouse release name: ' . $e->getMessage());
    }
}

function warehouseCheckedByName($posted): string
{
    $name = trim(preg_replace('/\s+/', ' ', (string) $posted));
    $name = str_replace(["\r", "\n"], '', $name);
    if ($name === '') {
        throw new RuntimeException('Enter the employee who checked the item.');
    }
    if (mb_strlen($name) > 150) {
        throw new RuntimeException('The employee name is too long.');
    }
    return $name;
}

function warehouseReleaseBlockedMessage(string $flow): string
{
    if ($flow === 'procurement') {
        return 'This request is still with Procurement. Receive the materials before release.';
    }
    if ($flow === 'accounting') {
        return 'This request is still with Accounting. Receive the materials before release.';
    }
    return 'There is no material request waiting for release on this project. Receive the materials first.';
}

function releasePendingProjectMaterials(PDO $pdo, int $projectId, int $userId, string $checkedBy = ''): array
{
    if ($projectId <= 0) {
        throw new InvalidArgumentException('Select the project site for this stock-out.');
    }

    ensureWarehouseFifoBatches($pdo);
    ensureWarehouseCheckerColumns($pdo);
    $checkedBy = warehouseCheckedByName($checkedBy);
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
            $waiting = $pdo->prepare("SELECT flow_status FROM material_requests WHERE project_id = ? AND status = 'pending' AND flow_status <> 'warehouse' ORDER BY id DESC LIMIT 1");
            $waiting->execute([$projectId]);
            throw new RuntimeException(warehouseReleaseBlockedMessage((string) $waiting->fetchColumn()));
        }

        $projectLabel = trim(($rows[0]['project_code'] ?? '') . ' ' . ($rows[0]['project_name'] ?? ''));
        $location = trim((string) ($rows[0]['project_location'] ?? ''));
        $reason = 'Released to project site ' . ($projectLabel !== '' ? $projectLabel : ('#' . $projectId));
        if ($location !== '') {
            $reason .= ' @ ' . $location;
        }
        $reason .= ' Released by ' . $checkedBy . '.';
        $running = [];
        $released = [];
        $short = [];
        $alerts = [];

        $deduct = $pdo->prepare("UPDATE materials SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
        $mark = $pdo->prepare("UPDATE material_requests SET status = 'approved', released_quantity = ?, released_by = ?, released_by_name = ?, released_at = NOW() WHERE id = ? AND status = 'pending'");

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
            $mark->execute([$qty, $userId, $checkedBy, (int) $row['id']]);
            $pdo->prepare("UPDATE material_requests SET flow_status = 'released' WHERE id = ? AND flow_status = 'warehouse'")->execute([(int) $row['id']]);
            $pdo->prepare("INSERT INTO warehouse_releases (material_request_id, project_id, engineer_id, quantity, status, released_by, released_by_name) VALUES (?, ?, ?, ?, 'released', ?, ?)")
                ->execute([(int) $row['id'], $projectId, (int) $row['requested_by'], $qty, $userId, $checkedBy]);
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

function connectMaterialRequestToProcurement(PDO $pdo, int $materialRequestId): ?string
{
    if ($materialRequestId <= 0) {
        return null;
    }
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare("
            SELECT mr.id, mr.project_id, mr.material_id, mr.quantity, mr.requested_by, mr.notes, mr.purchase_request_id, mr.flow_status, mr.status,
                   m.name AS material_name, m.unit, m.cost_per_unit,
                   p.project_code, p.name AS project_name
            FROM material_requests mr
            INNER JOIN materials m ON m.id = mr.material_id
            INNER JOIN projects p ON p.id = mr.project_id
            WHERE mr.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$materialRequestId]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'pending' || ($row['flow_status'] ?? '') !== 'procurement') {
            if ($ownTransaction) {
                $pdo->commit();
            }
            return null;
        }
        if ((int) $row['purchase_request_id'] > 0) {
            $existing = $pdo->prepare("SELECT pr_number FROM purchase_requests WHERE id = ?");
            $existing->execute([(int) $row['purchase_request_id']]);
            $number = (string) $existing->fetchColumn();
            if ($ownTransaction) {
                $pdo->commit();
            }
            return $number !== '' ? $number : null;
        }
        $qty = (int) $row['quantity'];
        $requestorId = (int) $row['requested_by'];
        if ($qty <= 0 || $requestorId <= 0) {
            throw new RuntimeException('The material request needs a quantity and a requestor before Procurement can receive it.');
        }
        $unitPrice = round((float) $row['cost_per_unit'], 2);
        $excelPrice = $pdo->prepare("
            SELECT unit_price FROM (
                SELECT item_name, unit_price FROM project_quotation_items WHERE project_id = ?
                UNION ALL
                SELECT i.item_name, i.unit_price
                FROM client_quotation_items i
                INNER JOIN client_quotations q ON q.id = i.quotation_id
                WHERE q.project_id = ?
            ) price_lines
            WHERE LOWER(TRIM(item_name)) = LOWER(?)
            ORDER BY unit_price DESC
            LIMIT 1
        ");
        $excelPrice->execute([(int) $row['project_id'], (int) $row['project_id'], (string) $row['material_name']]);
        $matchedPrice = $excelPrice->fetchColumn();
        if ($matchedPrice !== false && $matchedPrice !== null) {
            $unitPrice = round((float) $matchedPrice, 2);
        }
        $prNumber = generateNumber('PR', 'purchase_requests', 'pr_number');
        $purpose = 'Material request for ' . $row['project_code'] . ' - ' . $row['project_name'];
        $note = trim((string) ($row['notes'] ?? ''));
        if ($note !== '') {
            $purpose .= '. ' . $note;
        }
        $insertRequest = $pdo->prepare("INSERT INTO purchase_requests (pr_number, project_id, requestor_id, purpose, priority, status, total_amount, created_by) VALUES (?, ?, ?, ?, 'medium', 'pending', ?, ?)");
        $lineTotal = round($qty * $unitPrice, 2);
        $insertRequest->execute([$prNumber, (int) $row['project_id'], $requestorId, $purpose, $lineTotal, $requestorId]);
        $requestId = (int) $pdo->lastInsertId();
        $insertItem = $pdo->prepare("INSERT INTO purchase_request_items (purchase_request_id, material_id, quantity, unit, estimated_cost, remarks) VALUES (?, ?, ?, ?, ?, ?)");
        $insertItem->execute([
            $requestId,
            (int) $row['material_id'],
            $qty,
            trim((string) ($row['unit'] ?? '')) ?: 'pcs',
            $unitPrice,
            (string) $row['material_name'],
        ]);
        $pdo->prepare("UPDATE material_requests SET purchase_request_id = ?, flow_status = 'procurement' WHERE id = ? AND purchase_request_id IS NULL")
            ->execute([$requestId, $materialRequestId]);
        if ($ownTransaction) {
            $pdo->commit();
        }
        logActivity($requestorId, 'Sent material request to Procurement', 'Procurement', $prNumber . ' for ' . $row['project_code']);
        notify('procurement', 'material_request', 'Material request', $prNumber . ' is waiting in Procurement. Create the purchase order so Accounting can pay it.', 'modules/procurement/purchase_requests.php', (int) $row['project_id'], 'purchase_request', $requestId);
        return $prNumber;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function connectUnlinkedProcurementMaterialRequests(PDO $pdo): array
{
    $table = $pdo->query("SHOW TABLES LIKE 'material_requests'")->fetch();
    if (!$table) {
        return [];
    }
    $ids = $pdo->query("SELECT id FROM material_requests WHERE purchase_request_id IS NULL AND status = 'pending' AND flow_status = 'procurement' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $numbers = [];
    foreach ($ids as $id) {
        try {
            $number = connectMaterialRequestToProcurement($pdo, (int) $id);
            if ($number) {
                $numbers[] = $number;
            }
        } catch (Throwable $e) {
            error_log('[Runeha ERP] connect material request ' . (int) $id . ': ' . $e->getMessage());
        }
    }
    return $numbers;
}
