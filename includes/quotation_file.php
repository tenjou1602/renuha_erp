<?php

function extractQuotationUpload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a quotation file to extract.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('The file must be 5 MB or smaller.');
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'pdf'], true)) {
        throw new RuntimeException('Use a CSV, Excel (.xlsx), or PDF file.');
    }

    $plain = '';
    $parsed = null;
    if ($ext === 'csv' || $ext === 'txt') {
        $rows = quotationRowsFromDelimited($file['tmp_name']);
    } elseif ($ext === 'xlsx') {
        $book = quotationWorkbookFromXlsx($file['tmp_name']);
        $parsed = quotationInterpretBoq($book);
        $rows = array_map(static fn($line) => $line['cells'], $book['lines']);
    } else {
        $plain = quotationTextFromPdf($file['tmp_name']);
        $rows = quotationRowsFromPlain($plain);
    }

    if ($parsed === null) {
        $parsed = interpretQuotationRows($rows, $plain);
    }
    if (empty($parsed['items']) && $parsed['client'] === '' && $parsed['scope'] === '') {
        throw new RuntimeException('No quotation lines were found. Use columns for description, quantity, unit, and price.');
    }
    $parsed['project'] = $parsed['project'] ?? '';
    $parsed['location'] = $parsed['location'] ?? '';
    $parsed['subject'] = $parsed['subject'] ?? '';
    $total = 0.0;
    foreach ($parsed['items'] as $item) {
        $total += round(((float) $item['quantity']) * ((float) $item['unit_price']), 2);
    }
    $parsed['grand_total'] = round($total, 2);
    return $parsed;
}

function extractQuotationPath(string $relativePath, string $originalName): array
{
    $path = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!is_file($path)) {
        throw new RuntimeException('The quotation file from Executive Admin is missing.');
    }
    return extractQuotationUpload([
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($path) ?: 0,
        'name' => $originalName !== '' ? $originalName : basename($path),
        'tmp_name' => $path,
    ]);
}

function copyStoredQuotation(string $relativePath, string $originalName, int $quoteId): ?array
{
    $source = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!is_file($source)) {
        return null;
    }
    $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $source, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'pdf'], true)) {
        return null;
    }
    $dir = dirname(__DIR__) . '/uploads/quotations';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not store the quotation file.');
    }
    $stored = 'q' . $quoteId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!copy($source, $dir . '/' . $stored)) {
        throw new RuntimeException('Could not store the quotation file.');
    }
    return [
        'path' => 'uploads/quotations/' . $stored,
        'name' => basename($originalName !== '' ? $originalName : $source),
    ];
}

function storeQuotationAttachment(array $file, int $quoteId): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The quotation file could not be uploaded.');
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'pdf'], true)) {
        throw new RuntimeException('Attach a CSV, Excel (.xlsx), or PDF file.');
    }
    $dir = dirname(__DIR__) . '/uploads/quotations';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not store the quotation file.');
    }
    $stored = 'q' . $quoteId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('Could not store the quotation file.');
    }
    return [
        'path' => 'uploads/quotations/' . $stored,
        'name' => basename((string) $file['name']),
    ];
}

function storeProjectQuotation(array $file, int $projectId): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The quotation file could not be uploaded.');
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'xlsx', 'pdf'], true)) {
        throw new RuntimeException('Attach a CSV, Excel (.xlsx), or PDF file.');
    }
    $dir = dirname(__DIR__) . '/uploads/project_quotations';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not store the quotation file.');
    }
    $stored = 'p' . $projectId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('Could not store the quotation file.');
    }
    return [
        'path' => 'uploads/project_quotations/' . $stored,
        'name' => basename((string) $file['name']),
    ];
}

function quotationRowsFromDelimited(string $path): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Could not read the file.');
    }
    $sample = (string) fread($handle, 4096);
    rewind($handle);
    $delimiter = substr_count($sample, "\t") > substr_count($sample, ',') ? "\t" : (substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',');
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows[] = array_map(static fn($cell) => trim((string) $cell), $row);
    }
    fclose($handle);
    return $rows;
}

function quotationWorkbookFromXlsx(string $path): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Excel import is not available on this server. Save the sheet as CSV instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not read the Excel file.');
    }
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $item) {
                $texts = $item->xpath('.//*[local-name()="t"]');
                $shared[] = trim(implode('', array_map('strval', $texts ?: [])));
            }
        }
    }
    $sheetXml = '';
    $bestScore = -1;
    for ($i = 1; $i <= 8; $i++) {
        $candidate = $zip->getFromName('xl/worksheets/sheet' . $i . '.xml');
        if (!$candidate) {
            continue;
        }
        $score = substr_count($candidate, '<c ');
        if ($score > $bestScore) {
            $bestScore = $score;
            $sheetXml = $candidate;
        }
    }
    $zip->close();
    if ($sheetXml === '') {
        throw new RuntimeException('The Excel file has no worksheet.');
    }
    $sheet = simplexml_load_string($sheetXml);
    if (!$sheet || !isset($sheet->sheetData)) {
        throw new RuntimeException('Could not read the Excel sheet.');
    }
    $merges = [];
    if (isset($sheet->mergeCells)) {
        foreach ($sheet->mergeCells->mergeCell as $mergeCell) {
            if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', (string) $mergeCell['ref'], $match)) {
                $merges[] = [
                    'c1' => quotationColIndex($match[1]),
                    'r1' => (int) $match[2],
                    'c2' => quotationColIndex($match[3]),
                    'r2' => (int) $match[4],
                ];
            }
        }
    }
    $lines = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $col = preg_replace('/\d+/', '', $ref);
            $value = isset($cell->v) ? (string) $cell->v : '';
            if ((string) $cell['t'] === 's') {
                $value = $shared[(int) $value] ?? '';
            } elseif ((string) $cell['t'] === 'inlineStr' && isset($cell->is)) {
                $texts = $cell->xpath('.//*[local-name()="t"]');
                $value = trim(implode('', array_map('strval', $texts ?: [])));
            }
            $cells[quotationColIndex($col)] = trim(preg_replace('/\s+/', ' ', $value));
        }
        if (!$cells) {
            continue;
        }
        ksort($cells);
        $line = [];
        for ($i = 0; $i <= max(array_keys($cells)); $i++) {
            $line[] = $cells[$i] ?? '';
        }
        $lines[] = ['row' => (int) $row['r'], 'cells' => $line];
    }
    return ['lines' => $lines, 'merges' => $merges];
}

function quotationColIndex(string $letters): int
{
    $index = 0;
    $letters = strtoupper($letters);
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}

function quotationInterpretBoq(array $book): ?array
{
    $header = null;
    foreach ($book['lines'] as $index => $line) {
        $map = quotationBoqHeader($line['cells']);
        if ($map !== null) {
            $header = ['index' => $index, 'row' => $line['row'], 'map' => $map];
            break;
        }
    }
    if ($header === null) {
        return null;
    }

    $meta = [];
    foreach ($book['lines'] as $line) {
        if ($line['row'] >= $header['row']) {
            break;
        }
        quotationCaptureLabels($line['cells'], $meta);
    }

    $result = [
        'client' => $meta['client'] ?? '',
        'project' => $meta['project'] ?? '',
        'location' => $meta['location'] ?? '',
        'subject' => $meta['subject'] ?? '',
        'validity_date' => '',
        'scope' => trim(implode("\n", array_filter([$meta['project'] ?? '', $meta['subject'] ?? '']))),
        'notes' => '',
        'items' => [],
    ];
    $notes = [];
    if (!empty($meta['location'])) {
        $notes[] = 'Location: ' . $meta['location'];
    }
    if (!empty($meta['estimated by'])) {
        $notes[] = 'Estimated by: ' . $meta['estimated by'];
    }
    if (!empty($meta['date'])) {
        $notes[] = 'BOQ date: ' . $meta['date'];
    }
    $notes[] = 'Each line uses the BOQ amount column. Section subtotals are skipped. 12% VAT is kept as its own line so the total matches the file.';

    $section = '';
    $category = '';
    $detail = '';
    $map = $header['map'];
    foreach (array_slice($book['lines'], $header['index'] + 1) as $line) {
        $cells = $line['cells'];
        $desc = trim((string) ($cells[$map['description']] ?? ''));
        if ($desc === '' || quotationIsBoqColumnLabel($desc)) {
            continue;
        }
        $qtySlave = quotationCellIsMergeSlave($line['row'], $map['quantity'], $book['merges']);
        $amountSlave = quotationCellIsMergeSlave($line['row'], $map['amount'], $book['merges']);
        if (($qtySlave || $amountSlave) && $result['items']) {
            $last = count($result['items']) - 1;
            $extra = ltrim($desc, "- \t");
            $result['items'][$last]['description'] = trim($result['items'][$last]['description'] . "\n" . $extra);
            continue;
        }
        if (quotationIsSubtotal($desc)) {
            continue;
        }
        $qty = quotationNumber($cells[$map['quantity']] ?? 0);
        $amount = quotationNumber($cells[$map['amount']] ?? 0);
        $unit = trim((string) ($cells[$map['unit']] ?? ''));
        $itemNo = trim((string) ($cells[$map['item']] ?? ''));
        if (quotationIsVatLine($desc)) {
            if ($amount > 0) {
                $result['items'][] = quotationBoqItem($desc, '', 1, 'lot', round($amount, 2));
            }
            continue;
        }
        if ($qty <= 0) {
            if (preg_match('/^[A-Z]$/', $itemNo)) {
                $section = $desc;
                $category = '';
                $detail = '';
            } elseif ($itemNo !== '') {
                $category = $desc;
                $detail = '';
            } else {
                $detail = $desc;
            }
            continue;
        }
        if ($itemNo !== '' && !preg_match('/^[A-Z]$/', $itemNo)) {
            $category = '';
            $detail = '';
        }
        $name = $desc;
        $spec = '';
        if (preg_match('/^[-–—]\s*/', $desc)) {
            $spec = preg_replace('/^[-–—]\s*/', '', $desc);
            $name = $detail !== '' ? $detail : ($category !== '' ? $category : $spec);
        }
        $context = [];
        foreach ([$section, $category, $detail] as $part) {
            if ($part !== '' && strcasecmp($part, $name) !== 0) {
                $context[] = $part;
            }
        }
        $details = implode(' — ', $context);
        if ($spec !== '' && strcasecmp($spec, $name) !== 0) {
            $details = trim($details . "\n" . $spec);
        }
        $target = round($amount, 2);
        $price = $target > 0 ? round($target / $qty, 2) : 0.0;
        if ($target > 0 && round($qty * $price, 2) !== $target) {
            $step = round($qty * $price, 2) > $target ? -0.01 : 0.01;
            $adjusted = round($price + $step, 2);
            if (round($qty * $adjusted, 2) === $target) {
                $price = $adjusted;
            } else {
                $details = trim($details . ' (' . rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') . ' ' . ($unit !== '' ? $unit : 'units') . ')');
                $qty = 1;
                $unit = 'lot';
                $price = $target;
            }
        }
        $result['items'][] = quotationBoqItem($name, $details, $qty, $unit !== '' ? $unit : 'lot', $price);
    }
    $result['notes'] = implode("\n", $notes);
    return $result['items'] ? $result : null;
}

function quotationBoqHeader(array $row): ?array
{
    $map = [];
    foreach ($row as $index => $cell) {
        $label = strtolower(trim((string) $cell));
        if ($label === 'description') {
            $map['description'] = $index;
        } elseif ($label === 'qty' || $label === 'quantity') {
            $map['quantity'] = $index;
        } elseif ($label === 'unit' && !isset($map['unit'])) {
            $map['unit'] = $index;
        } elseif ($label === 'item') {
            $map['item'] = $index;
        } elseif ($label === 'amount' && !isset($map['amount'])) {
            $map['amount'] = $index;
        }
    }
    if (!isset($map['description'], $map['quantity'], $map['amount'], $map['unit'], $map['item'])) {
        return null;
    }
    return $map;
}

function quotationCaptureLabels(array $cells, array &$meta): void
{
    $count = count($cells);
    for ($i = 0; $i < $count; $i++) {
        $label = rtrim(strtolower(trim((string) $cells[$i])), ':');
        if (!in_array($label, ['project', 'location', 'client', 'subject', 'date', 'estimated by'], true)) {
            continue;
        }
        for ($j = $i + 1; $j < $count; $j++) {
            $value = trim((string) $cells[$j]);
            if ($value === '') {
                continue;
            }
            if ($label === 'date') {
                $value = quotationExcelDate($value);
            }
            if ($value !== '' && empty($meta[$label])) {
                $meta[$label] = $value;
            }
            break;
        }
    }
}

function quotationCellIsMergeSlave(int $row, int $col, array $merges): bool
{
    foreach ($merges as $merge) {
        if ($row < $merge['r1'] || $row > $merge['r2'] || $col < $merge['c1'] || $col > $merge['c2']) {
            continue;
        }
        return $row !== $merge['r1'] || $col !== $merge['c1'];
    }
    return false;
}

function quotationIsBoqColumnLabel(string $text): bool
{
    return in_array(strtolower(trim($text)), ['item', 'description', 'qty', 'quantity', 'unit', 'unit cost', 'amount', 'material cost', 'labor cost'], true);
}

function quotationIsSubtotal(string $text): bool
{
    return (bool) preg_match('/\b(sub-?total|grand total)\b/i', $text);
}

function quotationIsVatLine(string $text): bool
{
    return (bool) preg_match('/\bvat\b/i', $text);
}

function quotationBoqItem(string $name, string $description, float $qty, string $unit, float $price): array
{
    return [
        'line_type' => 'other',
        'item_name' => $name,
        'description' => $description,
        'quantity' => $qty,
        'unit' => $unit,
        'unit_price' => $price,
    ];
}

function quotationExcelDate(string $value): string
{
    if (is_numeric($value)) {
        $serial = (float) $value;
        if ($serial >= 20000 && $serial <= 80000) {
            return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
        }
    }
    return quotationNormalizeDate($value);
}

function quotationTextFromPdf(string $path): string
{
    $raw = file_get_contents($path);
    if ($raw === false || strncmp($raw, '%PDF', 4) !== 0) {
        throw new RuntimeException('Could not read the PDF.');
    }
    $pieces = [];
    if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if (!is_string($decoded) || $decoded === '') {
                $decoded = @gzinflate($stream);
            }
            if (!is_string($decoded) || $decoded === '') {
                $decoded = $stream;
            }
            $pieces[] = quotationPdfOperatorsToText($decoded);
        }
    }
    $text = trim(preg_replace("/[ \t]+\n/", "\n", implode("\n", $pieces)));
    if ($text === '') {
        throw new RuntimeException('No readable text was found in this PDF. Save it as CSV or Excel, then attach that file.');
    }
    return $text;
}

function quotationPdfOperatorsToText(string $content): string
{
    $lines = [];
    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $groups)) {
        foreach ($groups[1] as $group) {
            $line = '';
            if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $group, $parts)) {
                foreach ($parts[0] as $part) {
                    $line .= quotationPdfUnescape(substr($part, 1, -1));
                }
            }
            if (trim($line) !== '') {
                $lines[] = trim($line);
            }
        }
    }
    if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/', $content, $parts)) {
        foreach ($parts[0] as $part) {
            if (preg_match('/\((?:\\\\.|[^\\\\)])*\)/', $part, $inner)) {
                $lines[] = trim(quotationPdfUnescape(substr($inner[0], 1, -1)));
            }
        }
    }
    return implode("\n", $lines);
}

function quotationPdfUnescape(string $text): string
{
    return str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", '', ' ', '(', ')', '\\'], $text);
}

function quotationRowsFromPlain(string $text): array
{
    $rows = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $cells = preg_split('/\t+|\s{2,}/', $line) ?: [$line];
        $rows[] = array_map('trim', $cells);
    }
    return $rows;
}

function interpretQuotationRows(array $rows, string $plain = ''): array
{
    $result = ['client' => '', 'validity_date' => '', 'scope' => '', 'notes' => '', 'items' => []];
    $headerMap = null;
    $headerIndex = null;

    foreach ($rows as $index => $row) {
        $joined = trim(implode(' ', $row));
        if ($result['client'] === '' && preg_match('/\b(?:client|customer|bill to|sold to)\b\s*[:\-]?\s*(.+)$/i', $joined, $match)) {
            $result['client'] = trim($match[1]);
        }
        if ($result['validity_date'] === '' && preg_match('/\b(?:valid until|validity|valid thru|expiry|expiration)\b\s*[:\-]?\s*(.+)$/i', $joined, $match)) {
            $result['validity_date'] = quotationNormalizeDate($match[1]);
        }
        if ($result['scope'] === '' && preg_match('/\b(?:scope of work|scope|subject)\b\s*[:\-]?\s*(.+)$/i', $joined, $match)) {
            $result['scope'] = trim($match[1]);
        }
        $map = quotationHeaderMap($row);
        if ($map !== null) {
            $headerMap = $map;
            $headerIndex = $index;
        }
    }

    $source = $headerMap === null ? $rows : array_slice($rows, $headerIndex + 1);
    foreach ($source as $row) {
        $item = $headerMap ? quotationItemFromMappedRow($row, $headerMap) : quotationItemFromLooseRow($row);
        if ($item) {
            $result['items'][] = $item;
        }
    }

    if ($plain !== '' && $result['client'] === '' && preg_match('/\b(?:client|customer|bill to)\b\s*[:\-]\s*(.+)/i', $plain, $match)) {
        $result['client'] = trim($match[1]);
    }
    return $result;
}

function quotationHeaderMap(array $row): ?array
{
    $aliases = [
        'description' => ['description', 'item', 'particular', 'particulars', 'item description', 'details', 'name'],
        'quantity' => ['qty', 'quantity', 'qnty'],
        'unit' => ['unit', 'uom'],
        'unit_price' => ['unit price', 'price', 'rate', 'unit cost', 'cost'],
        'amount' => ['amount', 'line total', 'total', 'ext price'],
    ];
    $map = [];
    foreach ($row as $index => $cell) {
        $label = strtolower(trim($cell));
        foreach ($aliases as $field => $names) {
            if (in_array($label, $names, true)) {
                $map[$field] = $index;
            }
        }
    }
    return isset($map['description']) ? $map : null;
}

function quotationItemFromMappedRow(array $row, array $map): ?array
{
    $description = trim((string) ($row[$map['description']] ?? ''));
    if ($description === '' || quotationHeaderMap([$description])) {
        return null;
    }
    $qty = quotationNumber($row[$map['quantity']] ?? 1);
    $price = quotationNumber($row[$map['unit_price']] ?? 0);
    if (isset($map['amount']) && $price <= 0 && $qty > 0) {
        $price = round(quotationNumber($row[$map['amount']]) / $qty, 2);
    }
    if ($qty <= 0) {
        return null;
    }
    return [
        'line_type' => quotationLineType($description),
        'description' => $description,
        'quantity' => $qty,
        'unit' => trim((string) ($row[$map['unit']] ?? '')) ?: 'pcs',
        'unit_price' => $price,
    ];
}

function quotationItemFromLooseRow(array $row): ?array
{
    $text = [];
    $numbers = [];
    foreach ($row as $cell) {
        $cell = trim((string) $cell);
        if ($cell === '') {
            continue;
        }
        if (preg_match('/^-?\d[\d,]*(\.\d+)?$/', str_replace(['₱', 'PHP', ' '], '', $cell))) {
            $numbers[] = quotationNumber($cell);
        } else {
            $text[] = $cell;
        }
    }
    if (!$text || count($numbers) < 1) {
        return null;
    }
    $description = implode(' ', $text);
    if (preg_match('/\b(client|customer|total|subtotal|grand total|quotation)\b/i', $description)) {
        return null;
    }
    $qty = $numbers[0] ?? 1;
    $price = $numbers[1] ?? 0;
    if (count($numbers) >= 3 && $qty > 0) {
        $price = $numbers[count($numbers) - 2];
    }
    return [
        'line_type' => quotationLineType($description),
        'description' => $description,
        'quantity' => $qty > 0 ? $qty : 1,
        'unit' => 'pcs',
        'unit_price' => $price,
    ];
}

function quotationLineType(string $description): string
{
    return preg_match('/\b(labor|labour|manpower|salary|wage)\b/i', $description) ? 'labor' : 'other';
}

function quotationNumber($value): float
{
    $clean = str_replace([',', '₱', 'PHP', 'php', ' '], '', (string) $value);
    return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
}

function quotationNormalizeDate(string $value): string
{
    $value = trim($value);
    foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'M d, Y', 'F d, Y'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }
    $stamp = strtotime($value);
    return $stamp ? date('Y-m-d', $stamp) : '';
}
