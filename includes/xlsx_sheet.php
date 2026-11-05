<?php

function runehaXlsxColumn(int $index): string {
    $index++;
    $letters = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

function runehaXlsxXml(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function runehaWriteXlsx(string $path, array $rows, array $unlockedColumns = []): void {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel export is not available on this server.');
    }
    $sheetRows = '';
    foreach ($rows as $rIndex => $row) {
        $cells = '';
        foreach (array_values($row) as $cIndex => $value) {
            $ref = runehaXlsxColumn($cIndex) . ($rIndex + 1);
            $unlocked = in_array($cIndex, $unlockedColumns, true) && $rIndex > 0;
            $style = $unlocked ? ' s="1"' : '';
            if ($value === null || $value === '') {
                $cells .= '<c r="' . $ref . '"' . $style . '/>';
            } elseif (is_int($value) || is_float($value)) {
                $cells .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
            } else {
                $cells .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t>' . runehaXlsxXml((string) $value) . '</t></is></c>';
            }
        }
        $sheetRows .= '<row r="' . ($rIndex + 1) . '">' . $cells . '</row>';
    }
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetProtection sheet="1" objects="1" scenarios="1" insertColumns="1" insertRows="1" deleteColumns="1" deleteRows="1"/>'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
        . '<borders count="1"><border/></borders>'
        . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyProtection="1"><protection locked="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyProtection="1"><protection locked="0"/></xf>'
        . '</cellXfs></styleSheet>';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the Excel file.');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Materials" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
}

function runehaReadXlsx(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel import is not available on this server.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('The file is not a valid .xlsx workbook.');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = strtolower((string) $zip->getNameIndex($i));
        if (str_contains($name, 'vbaproject') || str_ends_with($name, '.bin')) {
            $zip->close();
            throw new RuntimeException('Macros are not allowed. Upload a plain .xlsx file.');
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    if ($sheetXml === false) {
        throw new RuntimeException('The workbook has no worksheet.');
    }
    $shared = [];
    if ($sharedXml !== false) {
        $sharedDoc = new DOMDocument();
        $sharedDoc->loadXML($sharedXml);
        foreach ($sharedDoc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $node) {
                $text .= $node->textContent;
            }
            $shared[] = $text;
        }
    }
    $doc = new DOMDocument();
    $doc->loadXML($sheetXml);
    $rows = [];
    $formulas = [];
    foreach ($doc->getElementsByTagName('row') as $row) {
        $line = [];
        $max = 0;
        foreach ($row->getElementsByTagName('c') as $cell) {
            if ($cell->getElementsByTagName('f')->length > 0) {
                $formulas[] = $cell->getAttribute('r') ?: 'a cell';
            }
            $ref = $cell->getAttribute('r');
            $col = 0;
            if (preg_match('/^([A-Z]+)/', $ref, $match)) {
                foreach (str_split($match[1]) as $letter) {
                    $col = $col * 26 + (ord($letter) - 64);
                }
                $col--;
            }
            $type = $cell->getAttribute('t');
            $value = '';
            if ($type === 'inlineStr') {
                foreach ($cell->getElementsByTagName('t') as $node) {
                    $value .= $node->textContent;
                }
            } else {
                $v = $cell->getElementsByTagName('v')->item(0);
                $raw = $v ? $v->textContent : '';
                $value = $type === 's' ? ($shared[(int) $raw] ?? '') : $raw;
            }
            $line[$col] = $value;
            $max = max($max, $col);
        }
        $filled = [];
        for ($i = 0; $i <= $max; $i++) {
            $filled[] = $line[$i] ?? '';
        }
        $rows[] = $filled;
    }
    if ($formulas) {
        throw new RuntimeException('Formulas are not allowed. Replace them with values. Found at ' . implode(', ', array_slice($formulas, 0, 8)) . '.');
    }
    return $rows;
}
