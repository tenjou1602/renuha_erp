<?php

function ensureWarehouseFifoSchema(PDO $pdo): void
{
    $col = $pdo->query("SHOW COLUMNS FROM stock_movements LIKE 'batch_number'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE stock_movements ADD COLUMN batch_number VARCHAR(100) NULL AFTER project_id");
    }
}

function warehouseNextBatchNumber(PDO $pdo, ?string $day = null): string
{
    $day = $day ?: date('Ymd');
    $prefix = 'FIFO-' . $day . '-';
    $stmt = $pdo->prepare("SELECT batch_number FROM warehouse_stock WHERE batch_number LIKE ? ORDER BY batch_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = (string) $stmt->fetchColumn();
    $seq = 1;
    if ($last !== '' && preg_match('/-(\d+)$/', $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

function warehouseReceiveBatch(PDO $pdo, int $materialId, int $qty, int $userId, string $reason, ?string $expiration = null, ?string $location = null, ?string $notes = null, ?string $createdAt = null): string
{
    if ($materialId <= 0 || $qty <= 0) {
        throw new InvalidArgumentException('Quantity must be greater than 0.');
    }
    $day = $createdAt ? date('Ymd', strtotime($createdAt)) : date('Ymd');
    $batch = warehouseNextBatchNumber($pdo, $day);
    $expiration = $expiration !== null && $expiration !== '' ? $expiration : null;
    $location = $location !== null && trim($location) !== '' ? trim($location) : null;
    $notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

    if ($createdAt) {
        $stmt = $pdo->prepare("INSERT INTO warehouse_stock (material_id, quantity, location, batch_number, expiration_date, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$materialId, $qty, $location, $batch, $expiration, $notes, $userId ?: null, $createdAt]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO warehouse_stock (material_id, quantity, location, batch_number, expiration_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$materialId, $qty, $location, $batch, $expiration, $notes, $userId ?: null]);
    }
    $pdo->prepare("UPDATE materials SET current_stock = current_stock + ? WHERE id = ?")->execute([$qty, $materialId]);
    $move = $pdo->prepare("INSERT INTO stock_movements (material_id, batch_number, movement_type, quantity, reason, created_by) VALUES (?, ?, 'in', ?, ?, ?)");
    $move->execute([$materialId, $batch, $qty, $reason, $userId ?: null]);
    return $batch;
}

function warehouseIssueFifo(PDO $pdo, int $materialId, int $qty, int $projectId, string $reason, int $userId): array
{
    if ($qty <= 0) {
        throw new InvalidArgumentException('Quantity must be greater than 0.');
    }
    $stmt = $pdo->prepare("SELECT id, quantity, batch_number FROM warehouse_stock WHERE material_id = ? AND quantity > 0 ORDER BY created_at ASC, id ASC FOR UPDATE");
    $stmt->execute([$materialId]);
    $layers = $stmt->fetchAll();
    $left = $qty;
    $used = [];
    $deduct = $pdo->prepare("UPDATE warehouse_stock SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
    $movement = $pdo->prepare("INSERT INTO stock_movements (material_id, project_id, batch_number, movement_type, quantity, reason, created_by) VALUES (?, ?, ?, 'out', ?, ?, ?)");
    foreach ($layers as $layer) {
        if ($left <= 0) {
            break;
        }
        $take = min($left, (int) $layer['quantity']);
        $deduct->execute([$take, (int) $layer['id'], $take]);
        if ($deduct->rowCount() < 1) {
            continue;
        }
        $movement->execute([$materialId, $projectId ?: null, $layer['batch_number'], $take, $reason, $userId]);
        $used[] = ($layer['batch_number'] ?: 'batch') . ' × ' . $take;
        $left -= $take;
    }
    if ($left > 0) {
        throw new RuntimeException('Kulang ang FIFO batch. Pinakamatandang batch muna ang lumalabas.');
    }
    return $used;
}

function ensureWarehouseFifoBatches(PDO $pdo): void
{
    ensureWarehouseFifoSchema($pdo);
    $materials = $pdo->query("SELECT id, material_code, current_stock, created_at FROM materials WHERE status = 'active' AND current_stock > 0")->fetchAll();
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM warehouse_stock WHERE material_id = ?");
    $inStmt = $pdo->prepare("SELECT quantity, created_at, reason FROM stock_movements WHERE material_id = ? AND movement_type = 'in' ORDER BY created_at ASC, id ASC");
    $insert = $pdo->prepare("INSERT INTO warehouse_stock (material_id, quantity, batch_number, notes, created_at) VALUES (?, ?, ?, ?, ?)");

    foreach ($materials as $material) {
        $materialId = (int) $material['id'];
        $sumStmt->execute([$materialId]);
        if ((int) $sumStmt->fetchColumn() > 0) {
            continue;
        }
        $current = (int) $material['current_stock'];
        $inStmt->execute([$materialId]);
        $ins = $inStmt->fetchAll();
        $layers = [];
        $inQty = 0;
        foreach ($ins as $in) {
            $qty = (int) $in['quantity'];
            if ($qty <= 0) {
                continue;
            }
            $inQty += $qty;
            $layers[] = $in;
        }
        $opening = $current - $inQty;
        if ($opening > 0) {
            $day = !empty($material['created_at']) ? date('Ymd', strtotime($material['created_at'])) : date('Ymd');
            $batch = warehouseNextBatchNumber($pdo, $day);
            $created = !empty($material['created_at']) ? $material['created_at'] : date('Y-m-d H:i:s');
            $insert->execute([$materialId, $opening, $batch, 'Opening balance for FIFO', $created]);
        }
        $consume = max(0, $inQty - $current);
        foreach ($layers as $layer) {
            $qty = (int) $layer['quantity'];
            if ($consume > 0) {
                $take = min($consume, $qty);
                $qty -= $take;
                $consume -= $take;
            }
            if ($qty <= 0) {
                continue;
            }
            $day = !empty($layer['created_at']) ? date('Ymd', strtotime($layer['created_at'])) : date('Ymd');
            $batch = warehouseNextBatchNumber($pdo, $day);
            $insert->execute([$materialId, $qty, $batch, $layer['reason'] ?: 'FIFO layer', $layer['created_at']]);
        }
    }
}
