<?php

require_once __DIR__ . '/xlsx_sheet.php';

const RUNEHA_XLSX_LIMIT = 5242880;

function procurementBoqHeaders(): array {
    return ['Material code', 'Description', 'Unit', 'Quantity', 'Unit price'];
}

function procurementNormalizeHeader(string $value): string {
    $value = strtolower(trim($value));
    $value = str_replace(['_', '-'], ' ', $value);
    return preg_replace('/\s+/', ' ', $value);
}

function procurementHeaderMap(array $header): array {
    $aliases = [
        'code' => ['material code', 'code', 'item code'],
        'description' => ['description', 'material', 'item', 'material description'],
        'unit' => ['unit'],
        'qty' => ['quantity', 'qty'],
        'price' => ['unit price', 'price', 'supplier price'],
    ];
    $found = [];
    foreach ($header as $index => $label) {
        $normal = procurementNormalizeHeader((string) $label);
        foreach ($aliases as $key => $names) {
            if (in_array($normal, $names, true)) {
                $found[$key] = $index;
            }
        }
    }
    return $found;
}

function procurementAssertUpload(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose an Excel file to upload.');
    }
    $name = (string) ($file['name'] ?? '');
    if (!preg_match('/\.xlsx$/i', $name)) {
        throw new RuntimeException('Only .xlsx files are accepted.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > RUNEHA_XLSX_LIMIT) {
        throw new RuntimeException('The Excel file must be larger than 0 and no larger than 5 MB.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('The upload could not be read.');
    }
    return $tmp;
}

function procurementParseBoqRows(array $rows): array {
    if (!$rows) {
        throw new RuntimeException('The Excel file has no rows.');
    }
    $map = procurementHeaderMap($rows[0]);
    foreach (['description', 'unit', 'qty'] as $required) {
        if (!isset($map[$required]) && !($required === 'description' && isset($map['code']))) {
            if ($required !== 'description' || !isset($map['code'])) {
                if ($required === 'description' && isset($map['code'])) {
                    continue;
                }
            }
        }
    }
    if (!isset($map['unit']) || !isset($map['qty']) || (!isset($map['code']) && !isset($map['description']))) {
        throw new RuntimeException('The file needs columns for a material code or description, a unit, and a quantity.');
    }
    $lines = [];
    $warnings = [];
    $errors = [];
    $seen = [];
    foreach (array_slice($rows, 1) as $offset => $row) {
        $excelRow = $offset + 2;
        $code = trim((string) ($row[$map['code'] ?? -1] ?? ''));
        $description = trim((string) ($row[$map['description'] ?? -1] ?? ''));
        $unit = trim((string) ($row[$map['unit']] ?? ''));
        $qtyRaw = trim((string) ($row[$map['qty']] ?? ''));
        if ($code === '' && $description === '' && $unit === '' && $qtyRaw === '') {
            continue;
        }
        if ($code === '' && $description === '') {
            $errors[] = 'Row ' . $excelRow . ' needs a material code or a description.';
            continue;
        }
        if ($unit === '') {
            $errors[] = 'Row ' . $excelRow . ' needs a unit.';
            continue;
        }
        if (!is_numeric($qtyRaw) || (float) $qtyRaw <= 0) {
            $errors[] = 'Row ' . $excelRow . ' quantity must be a number greater than 0.';
            continue;
        }
        $qty = round((float) $qtyRaw, 3);
        if (abs($qty - round($qty)) > 0.0001) {
            $errors[] = 'Row ' . $excelRow . ' quantity must be a whole number because purchase orders store whole quantities.';
            continue;
        }
        $key = strtolower($code !== '' ? $code : $description);
        if (isset($seen[$key])) {
            $previous = $lines[$seen[$key]];
            if (strcasecmp($previous['description'], $description) !== 0 || strcasecmp($previous['unit'], $unit) !== 0) {
                $errors[] = 'Row ' . $excelRow . ' duplicates ' . ($code !== '' ? $code : $description) . ' with a different description or unit.';
                continue;
            }
            $lines[$seen[$key]]['quantity'] += (int) $qty;
            $warnings[] = 'Row ' . $excelRow . ' duplicates ' . ($code !== '' ? $code : $description) . '. The quantities were added together.';
            continue;
        }
        $seen[$key] = count($lines);
        $lines[] = [
            'material_code' => $code !== '' ? $code : '',
            'description' => $description !== '' ? $description : $code,
            'unit' => $unit,
            'quantity' => (int) $qty,
        ];
    }
    if (!$lines && !$errors) {
        $errors[] = 'The file has no material rows.';
    }
    return ['lines' => $lines, 'warnings' => $warnings, 'errors' => $errors];
}

function procurementMatchMaterials(PDO $pdo, array $lines): array {
    $errors = [];
    foreach ($lines as $index => $line) {
        $material = null;
        if ($line['material_code'] !== '') {
            $stmt = $pdo->prepare("SELECT id, material_code, name, unit FROM materials WHERE material_code = ? LIMIT 1");
            $stmt->execute([$line['material_code']]);
            $material = $stmt->fetch();
        }
        if (!$material) {
            $stmt = $pdo->prepare("SELECT id, material_code, name, unit FROM materials WHERE name = ? LIMIT 1");
            $stmt->execute([$line['description']]);
            $material = $stmt->fetch();
        }
        if (!$material) {
            $errors[] = 'Row ' . ($index + 2) . ' (' . ($line['material_code'] !== '' ? $line['material_code'] : $line['description']) . ') does not match a material. Materials are not created from the Excel file.';
            continue;
        }
        $lines[$index]['material_id'] = (int) $material['id'];
        if ($lines[$index]['material_code'] === '') {
            $lines[$index]['material_code'] = (string) $material['material_code'];
        }
        if ($lines[$index]['description'] === '') {
            $lines[$index]['description'] = (string) $material['name'];
        }
    }
    return ['lines' => $lines, 'errors' => $errors];
}

function procurementStoreUpload(array $file, string $folder): array {
    $tmp = procurementAssertUpload($file);
    $hash = hash_file('sha256', $tmp);
    $dir = dirname(__DIR__) . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not store the Excel file.');
    }
    $stored = $folder . '/' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
    $dest = dirname(__DIR__) . '/uploads/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not store the Excel file.');
    }
    return ['path' => 'uploads/' . $stored, 'hash' => $hash, 'name' => basename((string) $file['name'])];
}

function procurementCurrentBoq(PDO $pdo, int $requestId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM procurement_boq WHERE purchase_request_id = ? ORDER BY version_no DESC, id DESC LIMIT 1");
    $stmt->execute([$requestId]);
    $boq = $stmt->fetch();
    if (!$boq) {
        return null;
    }
    $lines = $pdo->prepare("SELECT * FROM procurement_boq_lines WHERE boq_id = ? ORDER BY line_no, id");
    $lines->execute([(int) $boq['id']]);
    $boq['lines'] = $lines->fetchAll();
    return $boq;
}

function procurementRequestHasPo(PDO $pdo, int $requestId): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE purchase_request_id = ? AND status <> 'cancelled'");
    $stmt->execute([$requestId]);
    return (int) $stmt->fetchColumn() > 0;
}

function procurementPrepareBoq(PDO $pdo, int $requestId, array $file): array {
    if (procurementRequestHasPo($pdo, $requestId)) {
        throw new RuntimeException('A purchase order already exists for this request. The material list can no longer be changed.');
    }
    $request = $pdo->prepare("SELECT id, project_id, pr_number FROM purchase_requests WHERE id = ?");
    $request->execute([$requestId]);
    $pr = $request->fetch();
    if (!$pr) {
        throw new RuntimeException('That material request was not found.');
    }
    $stored = procurementStoreUpload($file, 'procurement_boq');
    $absolute = dirname(__DIR__) . '/' . $stored['path'];
    try {
        $parsed = procurementParseBoqRows(runehaReadXlsx($absolute));
        if ($parsed['errors']) {
            throw new RuntimeException(implode(' ', $parsed['errors']));
        }
        $matched = procurementMatchMaterials($pdo, $parsed['lines']);
        if ($matched['errors']) {
            throw new RuntimeException(implode(' ', $matched['errors']));
        }
    } catch (Throwable $e) {
        @unlink($absolute);
        throw $e;
    }
    return [
        'request_id' => $requestId,
        'project_id' => $pr['project_id'] ?: null,
        'pr_number' => $pr['pr_number'],
        'stored' => $stored,
        'warnings' => $parsed['warnings'],
        'lines' => $matched['lines'],
    ];
}

function procurementSaveBoq(PDO $pdo, int $requestId, array $preview, int $userId): array {
    if ((int) ($preview['request_id'] ?? 0) !== $requestId) {
        throw new RuntimeException('Confirm the preview for this request.');
    }
    if (procurementRequestHasPo($pdo, $requestId)) {
        throw new RuntimeException('A purchase order already exists for this request. The material list can no longer be changed.');
    }
    $stored = $preview['stored'];
    $matched = ['lines' => $preview['lines']];
    $parsed = ['warnings' => $preview['warnings'] ?? []];
    $pr = ['project_id' => $preview['project_id'], 'pr_number' => $preview['pr_number']];
    $version = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM procurement_boq WHERE purchase_request_id = ?");
    $version->execute([$requestId]);
    $versionNo = (int) $version->fetchColumn();
    $pdo->prepare("INSERT INTO procurement_boq (purchase_request_id, project_id, version_no, original_name, stored_path, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$requestId, $pr['project_id'] ?: null, $versionNo, $stored['name'], $stored['path'], $stored['hash'], $userId > 0 ? $userId : null]);
    $boqId = (int) $pdo->lastInsertId();
    $insert = $pdo->prepare("INSERT INTO procurement_boq_lines (boq_id, line_no, material_code, description, unit, quantity, material_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($matched['lines'] as $index => $line) {
        $insert->execute([$boqId, $index + 1, $line['material_code'], $line['description'], $line['unit'], $line['quantity'], $line['material_id']]);
    }
    writeAudit('Upload material Excel', 'Procurement', 'procurement_boq', $boqId, null, ['request' => $pr['pr_number'], 'version' => $versionNo, 'sha256' => $stored['hash']], null);
    return ['boq_id' => $boqId, 'version' => $versionNo, 'warnings' => $parsed['warnings'], 'lines' => $matched['lines']];
}

function procurementTemplateRows(array $lines): array {
    $rows = [procurementBoqHeaders()];
    foreach ($lines as $line) {
        $rows[] = [$line['material_code'], $line['description'], $line['unit'], (int) $line['quantity'], ''];
    }
    return $rows;
}

function procurementParseSupplierPrices(array $boqLines, array $rows): array {
    $map = procurementHeaderMap($rows[0] ?? []);
    if (!isset($map['price']) || !isset($map['qty']) || (!isset($map['code']) && !isset($map['description']))) {
        throw new RuntimeException('The supplier file must keep the material code, description, unit, quantity, and unit price columns.');
    }
    $byCode = [];
    foreach ($boqLines as $line) {
        $byCode[strtolower((string) $line['material_code'])] = $line;
    }
    $errors = [];
    $prices = [];
    $seen = [];
    foreach (array_slice($rows, 1) as $offset => $row) {
        $excelRow = $offset + 2;
        $code = trim((string) ($row[$map['code'] ?? -1] ?? ''));
        $description = trim((string) ($row[$map['description'] ?? -1] ?? ''));
        $unit = trim((string) ($row[$map['unit'] ?? -1] ?? ''));
        $qtyRaw = trim((string) ($row[$map['qty']] ?? ''));
        $priceRaw = trim((string) ($row[$map['price']] ?? ''));
        if ($code === '' && $description === '' && $qtyRaw === '' && $priceRaw === '') {
            continue;
        }
        $key = strtolower($code);
        $expected = $byCode[$key] ?? null;
        if (!$expected) {
            $errors[] = 'Row ' . $excelRow . ' material ' . ($code !== '' ? $code : $description) . ' is not in the request. Extra rows are not allowed.';
            continue;
        }
        if (isset($seen[$key])) {
            $errors[] = 'Row ' . $excelRow . ' repeats ' . $code . '.';
            continue;
        }
        $seen[$key] = true;
        if (strcasecmp($description, (string) $expected['description']) !== 0 || strcasecmp($unit, (string) $expected['unit']) !== 0) {
            $errors[] = 'Row ' . $excelRow . ' changed the description or unit for ' . $code . '.';
        }
        if (!is_numeric($qtyRaw) || (int) $qtyRaw !== (int) $expected['quantity']) {
            $errors[] = 'Row ' . $excelRow . ' changed the quantity for ' . $code . '. Expected ' . (int) $expected['quantity'] . '.';
        }
        if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw <= 0) {
            $errors[] = 'Row ' . $excelRow . ' unit price for ' . $code . ' must be a number greater than 0.';
            continue;
        }
        $prices[(int) $expected['id']] = round((float) $priceRaw, 2);
    }
    foreach ($boqLines as $line) {
        if (!isset($seen[strtolower((string) $line['material_code'])])) {
            $errors[] = 'Missing row for ' . $line['material_code'] . ' — ' . $line['description'] . '.';
        }
    }
    return ['prices' => $prices, 'errors' => $errors];
}

function procurementSaveSupplierPrices(PDO $pdo, int $requestId, int $supplierId, array $file, int $userId): array {
    $boq = procurementCurrentBoq($pdo, $requestId);
    if (!$boq) {
        throw new RuntimeException('Upload the material Excel file before supplier prices.');
    }
    $supplier = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND status = 'active'");
    $supplier->execute([$supplierId]);
    $supplierRow = $supplier->fetch();
    if (!$supplierRow) {
        throw new RuntimeException('Choose an active supplier.');
    }
    $stored = procurementStoreUpload($file, 'supplier_prices');
    $absolute = dirname(__DIR__) . '/' . $stored['path'];
    try {
        $parsed = procurementParseSupplierPrices($boq['lines'], runehaReadXlsx($absolute));
    } catch (Throwable $e) {
        @unlink($absolute);
        throw $e;
    }
    if ($parsed['errors']) {
        @unlink($absolute);
        throw new RuntimeException(implode(' ', $parsed['errors']));
    }
    $version = $pdo->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM supplier_price_uploads WHERE purchase_request_id = ? AND supplier_id = ?");
    $version->execute([$requestId, $supplierId]);
    $versionNo = (int) $version->fetchColumn();
    $company = trim((string) ($supplierRow['company_name'] ?? ''));
    $quoteNumber = generateNumber('QT', 'quotations', 'quotation_number');
    $total = 0.0;
    foreach ($boq['lines'] as $line) {
        $total += round((int) $line['quantity'] * $parsed['prices'][(int) $line['id']], 2);
    }
    $pdo->prepare("INSERT INTO quotations (quotation_number, project_id, purchase_request_id, supplier, supplier_contact, status, total_amount, notes, created_by) VALUES (?, ?, ?, ?, ?, 'received', ?, ?, ?)")
        ->execute([
            $quoteNumber,
            $boq['project_id'] ?: null,
            $requestId,
            $company !== '' ? $company : $supplierRow['name'],
            trim((string) ($supplierRow['phone'] ?? '')),
            round($total, 2),
            'Excel version ' . $versionNo . ' SHA-256 ' . $stored['hash'],
            $userId > 0 ? $userId : null,
        ]);
    $quoteId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO supplier_price_uploads (purchase_request_id, boq_id, supplier_id, version_no, original_name, stored_path, sha256, quotation_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$requestId, (int) $boq['id'], $supplierId, $versionNo, $stored['name'], $stored['path'], $stored['hash'], $quoteId, $userId > 0 ? $userId : null]);
    $uploadId = (int) $pdo->lastInsertId();
    $priceInsert = $pdo->prepare("INSERT INTO supplier_price_lines (upload_id, boq_line_id, unit_price) VALUES (?, ?, ?)");
    foreach ($parsed['prices'] as $lineId => $price) {
        $priceInsert->execute([$uploadId, $lineId, $price]);
    }
    writeAudit('Upload supplier prices', 'Procurement', 'supplier_price_uploads', $uploadId, null, ['supplier_id' => $supplierId, 'version' => $versionNo, 'quotation' => $quoteNumber], null);
    return ['upload_id' => $uploadId, 'version' => $versionNo, 'quotation_number' => $quoteNumber, 'total' => round($total, 2)];
}

function procurementLatestUploads(PDO $pdo, int $requestId): array {
    $stmt = $pdo->prepare("
        SELECT u.*, s.name, s.company_name, s.phone, s.email, s.address, s.tin_number
        FROM supplier_price_uploads u
        INNER JOIN suppliers s ON s.id = u.supplier_id
        WHERE u.purchase_request_id = ?
          AND u.id = (
              SELECT MAX(u2.id) FROM supplier_price_uploads u2
              WHERE u2.purchase_request_id = u.purchase_request_id AND u2.supplier_id = u.supplier_id
          )
        ORDER BY s.company_name, s.name
    ");
    $stmt->execute([$requestId]);
    $uploads = $stmt->fetchAll();
    foreach ($uploads as &$upload) {
        $prices = $pdo->prepare("SELECT boq_line_id, unit_price FROM supplier_price_lines WHERE upload_id = ?");
        $prices->execute([(int) $upload['id']]);
        $upload['prices'] = [];
        foreach ($prices->fetchAll() as $price) {
            $upload['prices'][(int) $price['boq_line_id']] = (float) $price['unit_price'];
        }
    }
    unset($upload);
    return $uploads;
}

function procurementComparison(PDO $pdo, int $requestId): array {
    $boq = procurementCurrentBoq($pdo, $requestId);
    $uploads = $boq ? procurementLatestUploads($pdo, $requestId) : [];
    $rows = [];
    $totals = [];
    foreach ($uploads as $upload) {
        $totals[(int) $upload['id']] = 0.0;
    }
    if ($boq) {
        foreach ($boq['lines'] as $line) {
            $cells = [];
            $lowest = null;
            foreach ($uploads as $upload) {
                $price = $upload['prices'][(int) $line['id']] ?? null;
                $cells[(int) $upload['id']] = $price;
                if ($price !== null) {
                    $totals[(int) $upload['id']] += round((int) $line['quantity'] * $price, 2);
                    $lowest = $lowest === null ? $price : min($lowest, $price);
                }
            }
            $rows[] = ['line' => $line, 'cells' => $cells, 'lowest' => $lowest];
        }
    }
    $lowestTotal = null;
    foreach ($totals as $total) {
        if ($total > 0) {
            $lowestTotal = $lowestTotal === null ? $total : min($lowestTotal, $total);
        }
    }
    $choice = $pdo->prepare("SELECT * FROM procurement_supplier_choice WHERE purchase_request_id = ?");
    $choice->execute([$requestId]);
    return [
        'boq' => $boq,
        'uploads' => $uploads,
        'rows' => $rows,
        'totals' => $totals,
        'lowest_total' => $lowestTotal,
        'choice' => $choice->fetch() ?: null,
    ];
}

function procurementSelectSupplier(PDO $pdo, int $requestId, int $uploadId, int $userId): void {
    $upload = $pdo->prepare("SELECT * FROM supplier_price_uploads WHERE id = ? AND purchase_request_id = ?");
    $upload->execute([$uploadId, $requestId]);
    $row = $upload->fetch();
    if (!$row || !(int) $row['quotation_id']) {
        throw new RuntimeException('Select a supplier whose price file was uploaded.');
    }
    $pdo->prepare("UPDATE quotations SET status = 'received' WHERE purchase_request_id = ? AND status = 'accepted'")->execute([$requestId]);
    $pdo->prepare("UPDATE quotations SET status = 'accepted' WHERE id = ?")->execute([(int) $row['quotation_id']]);
    $pdo->prepare("INSERT INTO procurement_supplier_choice (purchase_request_id, supplier_id, upload_id, chosen_by, chosen_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE supplier_id = VALUES(supplier_id), upload_id = VALUES(upload_id), chosen_by = VALUES(chosen_by), chosen_at = NOW()")
        ->execute([$requestId, (int) $row['supplier_id'], $uploadId, $userId > 0 ? $userId : null]);
    writeAudit('Select supplier', 'Procurement', 'procurement_supplier_choice', $requestId, null, ['upload_id' => $uploadId, 'supplier_id' => (int) $row['supplier_id']], null);
}

function procurementChooseSupplier(PDO $pdo, int $requestId, int $supplierId, int $userId): void {
    $supplier = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND status = 'active'");
    $supplier->execute([$supplierId]);
    if (!$supplier->fetchColumn()) {
        throw new RuntimeException('Select an active supplier.');
    }
    $request = $pdo->prepare("SELECT id FROM purchase_requests WHERE id = ? AND project_id IS NOT NULL");
    $request->execute([$requestId]);
    if (!$request->fetchColumn()) {
        throw new RuntimeException('Select a material request.');
    }
    $pdo->prepare("INSERT INTO procurement_supplier_choice (purchase_request_id, supplier_id, upload_id, chosen_by, chosen_at) VALUES (?, ?, 0, ?, NOW()) ON DUPLICATE KEY UPDATE supplier_id = VALUES(supplier_id), upload_id = 0, chosen_by = VALUES(chosen_by), chosen_at = NOW()")
        ->execute([$requestId, $supplierId, $userId > 0 ? $userId : null]);
    writeAudit('Select supplier', 'Procurement', 'procurement_supplier_choice', $requestId, null, ['supplier_id' => $supplierId], null);
}

function procurementCreateOrderFromExcel(PDO $pdo, int $requestId, int $userId): array {
    $comparison = procurementComparison($pdo, $requestId);
    $choice = $comparison['choice'];
    if (!$choice) {
        throw new RuntimeException('Select a supplier before creating the purchase order.');
    }
    $upload = null;
    foreach ($comparison['uploads'] as $item) {
        if ((int) $choice['upload_id'] > 0 && (int) $item['id'] === (int) $choice['upload_id']) {
            $upload = $item;
        }
    }
    $existing = $pdo->prepare("SELECT id, po_number FROM purchase_orders WHERE purchase_request_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1");
    $existing->execute([$requestId]);
    if ($existing->fetch()) {
        throw new RuntimeException('A purchase order already exists for this request.');
    }
    $request = $pdo->prepare("SELECT project_id, pr_number FROM purchase_requests WHERE id = ?");
    $request->execute([$requestId]);
    $pr = $request->fetch();
    if (!$pr || (int) $pr['project_id'] <= 0) {
        throw new RuntimeException('The request must be linked to a project.');
    }
    if ($upload) {
        $company = trim((string) ($upload['company_name'] ?? ''));
        $supplierName = $company !== '' ? $company : (string) $upload['name'];
        $supplierPhone = trim((string) ($upload['phone'] ?? ''));
    } else {
        $supplier = $pdo->prepare("SELECT name, company_name, phone FROM suppliers WHERE id = ? AND status = 'active'");
        $supplier->execute([(int) $choice['supplier_id']]);
        $supplier = $supplier->fetch();
        if (!$supplier) {
            throw new RuntimeException('Select an active supplier before creating the purchase order.');
        }
        $company = trim((string) ($supplier['company_name'] ?? ''));
        $supplierName = $company !== '' ? $company : (string) $supplier['name'];
        $supplierPhone = trim((string) ($supplier['phone'] ?? ''));
    }
    $storedItems = $pdo->prepare("SELECT material_id, quantity, estimated_cost FROM purchase_request_items WHERE purchase_request_id = ? ORDER BY id");
    $storedItems->execute([$requestId]);
    $storedItems = $storedItems->fetchAll();
    if (!$storedItems) {
        throw new RuntimeException('This request has no Excel materials.');
    }
    $poNumber = generateNumber('PO', 'purchase_orders', 'po_number');
    $total = 0.0;
    $items = [];
    foreach ($storedItems as $row) {
        $price = round((float) $row['estimated_cost'], 2);
        if ($price < 0) {
            throw new RuntimeException('The Excel price cannot be changed.');
        }
        $qty = (int) $row['quantity'];
        $total += round($qty * $price, 2);
        $items[] = [(int) $row['material_id'], $qty, $price];
    }
    $total = round($total, 2);
    $pdo->prepare("INSERT INTO purchase_orders (po_number, purchase_request_id, project_id, supplier, supplier_contact, order_date, payment_terms, total_amount, status, created_by) VALUES (?, ?, ?, ?, ?, CURDATE(), 'Cash', ?, 'sent', ?)")
        ->execute([$poNumber, $requestId, (int) $pr['project_id'], $supplierName, $supplierPhone, $total, $userId > 0 ? $userId : null]);
    $orderId = (int) $pdo->lastInsertId();
    $insert = $pdo->prepare("INSERT INTO purchase_order_items (purchase_order_id, material_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $insert->execute([$orderId, $item[0], $item[1], $item[2]]);
    }
    $pdo->prepare("UPDATE purchase_requests SET status = 'ordered', total_amount = ? WHERE id = ? AND status <> 'completed'")->execute([$total, $requestId]);
    $pdo->prepare("UPDATE material_requests SET flow_status = 'accounting' WHERE purchase_request_id = ? AND flow_status = 'procurement'")->execute([$requestId]);
    require_once __DIR__ . '/accounting_ledger.php';
    $expenseId = queuePurchaseOrderForAccounting($pdo, $orderId, $userId);
    writeAudit('Create purchase order', 'Procurement', 'purchase_orders', $orderId, null, ['po_number' => $poNumber, 'total' => $total, 'payment_terms' => 'Cash'], null);
    if ($expenseId > 0) {
        notify('accounting', 'purchase_order', 'Purchase order to pay', $poNumber . ' is waiting for payment. The materials are listed on the expense.', 'modules/accounting/expenses.php?action=view&id=' . $expenseId, (int) $pr['project_id'], 'purchase_order', $orderId);
    }
    return ['po_number' => $poNumber, 'order_id' => $orderId, 'total' => $total, 'expense_id' => $expenseId];
}
