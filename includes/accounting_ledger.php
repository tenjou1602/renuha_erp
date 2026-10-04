<?php

const RUNEHA_ACCT_CASH = '1000';
const RUNEHA_ACCT_AR = '1200';
const RUNEHA_ACCT_REVENUE = '4000';

function ensureClientAccountingSchema() {
    global $pdo;
    static $done = false;
    if ($done || !isset($pdo)) {
        return;
    }
    $done = true;

    $statements = [
        "CREATE TABLE IF NOT EXISTS chart_of_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(150) NOT NULL,
            account_type ENUM('asset','income') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coa_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS journal_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_date DATE NOT NULL,
            reference_type ENUM('invoice','payment') NOT NULL,
            reference_id BIGINT UNSIGNED NOT NULL,
            description VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_journal_ref (reference_type, reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS journal_lines (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            journal_entry_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY idx_jl_entry (journal_entry_id),
            KEY idx_jl_account (account_id),
            KEY idx_jl_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS clients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(200) NOT NULL,
            contact_person VARCHAR(150) NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(200) NULL,
            address TEXT NULL,
            tin_number VARCHAR(50) NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_clients_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS client_quotations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quotation_number VARCHAR(50) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            client_name VARCHAR(200) NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            scope_of_work TEXT NULL,
            validity_date DATE NULL,
            status ENUM('draft','sent','approved','rejected','converted') NOT NULL DEFAULT 'draft',
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_client_quotation_number (quotation_number),
            KEY idx_cq_project (project_id),
            KEY idx_cq_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS client_quotation_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quotation_id BIGINT UNSIGNED NOT NULL,
            line_type ENUM('material','labor','other') NOT NULL DEFAULT 'other',
            item_name VARCHAR(255) NULL,
            material_id BIGINT UNSIGNED NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            unit VARCHAR(50) NULL,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY idx_cqi_quotation (quotation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS invoice_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT UNSIGNED NOT NULL,
            line_type ENUM('material','labor','other') NOT NULL DEFAULT 'other',
            material_id BIGINT UNSIGNED NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
            unit VARCHAR(50) NULL,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            KEY idx_ii_invoice (invoice_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('[Runeha ERP] client accounting schema: ' . $e->getMessage());
        }
    }

    $quoteColumns = [
        'client_id' => 'client_id BIGINT UNSIGNED NULL',
        'scope_of_work' => 'scope_of_work TEXT NULL',
        'validity_date' => 'validity_date DATE NULL',
        'subtotal' => 'subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00',
        'grand_total' => 'grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00',
        'attachment_path' => 'attachment_path VARCHAR(255) NULL',
        'attachment_name' => 'attachment_name VARCHAR(255) NULL',
    ];
    try {
        $descCol = $pdo->query("SHOW COLUMNS FROM client_quotation_items LIKE 'description'")->fetch();
        if ($descCol && stripos((string) $descCol['Type'], 'varchar') !== false) {
            $pdo->exec("ALTER TABLE client_quotation_items MODIFY description TEXT NOT NULL");
        }
        $nameCol = $pdo->query("SHOW COLUMNS FROM client_quotation_items LIKE 'item_name'")->fetch();
        if (!$nameCol) {
            $pdo->exec("ALTER TABLE client_quotation_items ADD COLUMN item_name VARCHAR(255) NULL AFTER line_type");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] client_quotation_items description: ' . $e->getMessage());
    }

    foreach ($quoteColumns as $name => $ddl) {
        try {
            $exists = $pdo->query("SHOW COLUMNS FROM client_quotations LIKE " . $pdo->quote($name))->fetch();
            if (!$exists) {
                $pdo->exec("ALTER TABLE client_quotations ADD COLUMN $ddl");
            }
        } catch (PDOException $e) {
            error_log('[Runeha ERP] client_quotations column ' . $name . ': ' . $e->getMessage());
        }
    }
    try {
        $amountCol = $pdo->query("SHOW COLUMNS FROM client_quotations LIKE 'amount'")->fetch();
        $grandCol = $pdo->query("SHOW COLUMNS FROM client_quotations LIKE 'grand_total'")->fetch();
        if ($amountCol && $grandCol) {
            $pdo->exec("UPDATE client_quotations SET grand_total = amount, subtotal = amount WHERE grand_total = 0 AND amount <> 0");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] client_quotations amount copy: ' . $e->getMessage());
    }
    try {
        $title = $pdo->query("SHOW COLUMNS FROM client_quotations LIKE 'title'")->fetch();
        if ($title && strtoupper((string) $title['Null']) === 'NO') {
            $pdo->exec("ALTER TABLE client_quotations MODIFY title VARCHAR(255) NULL");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] client_quotations title: ' . $e->getMessage());
    }
    try {
        $pdo->exec("ALTER TABLE client_quotations MODIFY status ENUM('draft','sent','approved','rejected','converted') NOT NULL DEFAULT 'draft'");
    } catch (PDOException $e) {
        error_log('[Runeha ERP] client_quotations status: ' . $e->getMessage());
    }

    $columns = [
        'quotation_id' => 'quotation_id BIGINT UNSIGNED NULL',
        'client_id' => 'client_id BIGINT UNSIGNED NULL',
        'client_name' => 'client_name VARCHAR(200) NULL',
        'subtotal' => 'subtotal DECIMAL(15,2) NULL',
    ];
    foreach ($columns as $name => $ddl) {
        try {
            $exists = $pdo->query("SHOW COLUMNS FROM invoices LIKE " . $pdo->quote($name))->fetch();
            if (!$exists) {
                $pdo->exec("ALTER TABLE invoices ADD COLUMN $ddl");
            }
        } catch (PDOException $e) {
            error_log('[Runeha ERP] invoices column ' . $name . ': ' . $e->getMessage());
        }
    }
    try {
        $idx = $pdo->query("SHOW INDEX FROM invoices WHERE Key_name = 'uq_invoices_quotation'")->fetch();
        if (!$idx) {
            $pdo->exec("ALTER TABLE invoices ADD UNIQUE KEY uq_invoices_quotation (quotation_id)");
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] invoices quotation index: ' . $e->getMessage());
    }

    $accounts = [
        [RUNEHA_ACCT_CASH, 'Cash', 'asset'],
        [RUNEHA_ACCT_AR, 'Accounts Receivable', 'asset'],
        [RUNEHA_ACCT_REVENUE, 'Sales/Service Revenue', 'income'],
    ];
    try {
        $check = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE code = ?");
        $insert = $pdo->prepare("INSERT INTO chart_of_accounts (code, name, account_type) VALUES (?, ?, ?)");
        foreach ($accounts as $account) {
            $check->execute([$account[0]]);
            if (!$check->fetchColumn()) {
                $insert->execute($account);
            }
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] chart of accounts seed: ' . $e->getMessage());
    }
}

function invoiceClientLabel(array $invoice) {
    $name = trim((string) ($invoice['client_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return (string) ($invoice['client'] ?? '');
}

function ledgerEntryExists($referenceType, $referenceId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = ? AND reference_id = ?");
    $stmt->execute([$referenceType, (int) $referenceId]);
    return (bool) $stmt->fetchColumn();
}

function accountIdByCode($code) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE code = ?");
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        throw new RuntimeException('Account ' . $code . ' is missing from the chart of accounts.');
    }
    return (int) $id;
}

function postJournal($referenceType, $referenceId, $entryDate, $description, array $lines, $userId) {
    global $pdo;
    $referenceId = (int) $referenceId;
    if (ledgerEntryExists($referenceType, $referenceId)) {
        return;
    }

    $debit = 0.0;
    $credit = 0.0;
    foreach ($lines as $line) {
        $debit += (float) $line['debit'];
        $credit += (float) $line['credit'];
    }
    if (abs(round($debit, 2) - round($credit, 2)) > 0.009) {
        throw new RuntimeException('Journal entry is out of balance.');
    }

    $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_date, reference_type, reference_id, description, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$entryDate ?: date('Y-m-d'), $referenceType, $referenceId, $description, $userId]);
    $entryId = (int) $pdo->lastInsertId();

    $lineStmt = $pdo->prepare("INSERT INTO journal_lines (journal_entry_id, account_id, project_id, debit, credit) VALUES (?, ?, ?, ?, ?)");
    foreach ($lines as $line) {
        $projectId = !empty($line['project_id']) ? (int) $line['project_id'] : null;
        $lineStmt->execute([
            $entryId,
            accountIdByCode($line['code']),
            $projectId,
            round((float) $line['debit'], 2),
            round((float) $line['credit'], 2),
        ]);
    }
}

function postInvoiceEntry(array $invoice, $userId) {
    $amount = round((float) ($invoice['amount'] ?? 0), 2);
    if ($amount <= 0) {
        throw new RuntimeException('Invoice amount must be greater than 0 before it can be recorded.');
    }
    $projectId = !empty($invoice['project_id']) ? (int) $invoice['project_id'] : null;
    postJournal(
        'invoice',
        $invoice['id'],
        $invoice['invoice_date'] ?? date('Y-m-d'),
        'Invoice ' . ($invoice['invoice_number'] ?? ''),
        [
            ['code' => RUNEHA_ACCT_AR, 'debit' => $amount, 'credit' => 0, 'project_id' => $projectId],
            ['code' => RUNEHA_ACCT_REVENUE, 'debit' => 0, 'credit' => $amount, 'project_id' => $projectId],
        ],
        $userId
    );
}

function postPaymentEntry(array $payment, array $invoice, $userId) {
    if (!ledgerEntryExists('invoice', $invoice['id'])) {
        return;
    }
    $amount = round((float) ($payment['amount'] ?? 0), 2);
    if ($amount <= 0) {
        throw new RuntimeException('Payment amount must be greater than 0.');
    }
    $projectId = !empty($invoice['project_id']) ? (int) $invoice['project_id'] : null;
    postJournal(
        'payment',
        $payment['id'],
        $payment['payment_date'] ?? date('Y-m-d'),
        'Cash payment ' . ($payment['payment_number'] ?? ''),
        [
            ['code' => RUNEHA_ACCT_CASH, 'debit' => $amount, 'credit' => 0, 'project_id' => $projectId],
            ['code' => RUNEHA_ACCT_AR, 'debit' => 0, 'credit' => $amount, 'project_id' => $projectId],
        ],
        $userId
    );
}

function ledgerAccountBalances() {
    global $pdo;
    $rows = $pdo->query("
        SELECT a.code, a.name, a.account_type,
               COALESCE(SUM(jl.debit), 0) AS total_debit,
               COALESCE(SUM(jl.credit), 0) AS total_credit
        FROM chart_of_accounts a
        LEFT JOIN journal_lines jl ON jl.account_id = a.id
        GROUP BY a.id, a.code, a.name, a.account_type
        ORDER BY a.code
    ")->fetchAll();
    foreach ($rows as &$row) {
        $debit = (float) $row['total_debit'];
        $credit = (float) $row['total_credit'];
        $row['balance'] = $row['account_type'] === 'income' ? ($credit - $debit) : ($debit - $credit);
    }
    unset($row);
    return $rows;
}

function quotationCanConvert(array $quotation) {
    if (($quotation['status'] ?? '') !== 'approved') {
        return false;
    }
    if (empty($quotation['validity_date'])) {
        return true;
    }
    return $quotation['validity_date'] >= date('Y-m-d');
}

function activeClients() {
    global $pdo;
    try {
        return $pdo->query("SELECT id, name, contact_person, phone, email, address, tin_number FROM clients WHERE status = 'active' ORDER BY name")->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function requireActiveClient($clientId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND status = 'active'");
    $stmt->execute([(int) $clientId]);
    $client = $stmt->fetch();
    if (!$client) {
        throw new RuntimeException('Select an active client from the client list.');
    }
    return $client;
}

function findOrCreateClientByName($name) {
    global $pdo;
    $name = trim((string) $name);
    if ($name === '') {
        throw new RuntimeException('Select an active client from the client list.');
    }
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE status = 'active' AND LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $client = $stmt->fetch();
    if ($client) {
        return $client;
    }
    $insert = $pdo->prepare("INSERT INTO clients (name, status, created_by) VALUES (?, 'active', ?)");
    $insert->execute([$name, $_SESSION['user_id'] ?? null]);
    return requireActiveClient((int) $pdo->lastInsertId());
}

function syncProjectFundsFromLabor(PDO $pdo, ?int $projectId = null): void
{
    try {
        $pdo->exec("UPDATE funds SET allocated_amount = 0 WHERE project_id IS NOT NULL AND status = 'active'" . ($projectId ? ' AND project_id = ' . (int) $projectId : ''));
        $sql = "UPDATE funds f
            INNER JOIN (
                SELECT project_id, MAX(id) AS fund_id
                FROM funds
                WHERE status = 'active' AND project_id IS NOT NULL
                " . ($projectId ? ' AND project_id = ' . (int) $projectId : '') . "
                GROUP BY project_id
            ) latest ON latest.fund_id = f.id
            LEFT JOIN (
                SELECT b.project_id, COALESCE(SUM(l.line_total), 0) AS payroll
                FROM labor_budgets b
                LEFT JOIN labor_budget_lines l ON l.labor_budget_id = b.id
                WHERE b.status = 'active' AND b.project_id IS NOT NULL
                " . ($projectId ? ' AND b.project_id = ' . (int) $projectId : '') . "
                GROUP BY b.project_id
            ) wages ON wages.project_id = f.project_id
            LEFT JOIN (
                SELECT project_id, COALESCE(SUM(amount), 0) AS materials
                FROM expenses
                WHERE status IN ('pending', 'paid')
                  AND category = 'Materials'
                  AND reference_number LIKE 'PO-%'
                  AND project_id IS NOT NULL
                  " . ($projectId ? ' AND project_id = ' . (int) $projectId : '') . "
                GROUP BY project_id
            ) mats ON mats.project_id = f.project_id
            SET f.allocated_amount = COALESCE(wages.payroll, 0) + COALESCE(mats.materials, 0)";
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log('[Runeha ERP] fund labor sync: ' . $e->getMessage());
    }
}

function syncPurchasedMaterialsToAccounting(PDO $pdo): void
{
    try {
        $orders = $pdo->query("
            SELECT po.po_number, po.supplier, pr.project_id,
                   COALESCE(DATE(po.received_at), po.order_date, CURDATE()) AS expense_date,
                   ROUND(SUM(poi.received_quantity * poi.unit_price), 2) AS spent
            FROM purchase_orders po
            INNER JOIN purchase_requests pr ON pr.id = po.purchase_request_id
            INNER JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
            WHERE pr.project_id IS NOT NULL
              AND po.status NOT IN ('draft', 'cancelled')
              AND poi.received_quantity > 0
            GROUP BY po.id, po.po_number, po.supplier, pr.project_id, COALESCE(DATE(po.received_at), po.order_date, CURDATE())
            HAVING spent > 0
        ")->fetchAll();
        $find = $pdo->prepare("SELECT id FROM expenses WHERE reference_number = ? AND category = 'Materials' LIMIT 1");
        $update = $pdo->prepare("UPDATE expenses SET project_id = ?, amount = ?, expense_date = ?, description = ?, status = 'paid', payment_method = 'Purchase order' WHERE id = ?");
        $insert = $pdo->prepare("INSERT INTO expenses (expense_number, project_id, category, description, amount, expense_date, payment_method, reference_number, status) VALUES (?, ?, 'Materials', ?, ?, ?, 'Purchase order', ?, 'paid')");
        foreach ($orders as $order) {
            $amount = round((float) $order['spent'], 2);
            $description = 'Purchased materials from ' . $order['supplier'] . ' (' . $order['po_number'] . ')';
            $find->execute([$order['po_number']]);
            $existingId = (int) $find->fetchColumn();
            if ($existingId > 0) {
                $update->execute([(int) $order['project_id'], $amount, $order['expense_date'], $description, $existingId]);
            } else {
                $insert->execute([
                    generateNumber('EXP', 'expenses', 'expense_number'),
                    (int) $order['project_id'],
                    $description,
                    $amount,
                    $order['expense_date'],
                    $order['po_number'],
                ]);
            }
        }
        syncProjectFundsFromLabor($pdo);
        if (function_exists('syncProjectActualCosts')) {
            syncProjectActualCosts();
        }
    } catch (PDOException $e) {
        error_log('[Runeha ERP] purchase expense sync: ' . $e->getMessage());
    }
}

function resolveAccountingClient($posted) {
    $posted = trim((string) $posted);
    if (preg_match('/^\d+$/', $posted) && (int) $posted > 0) {
        return requireActiveClient((int) $posted);
    }
    if (str_starts_with($posted, 'name:')) {
        return findOrCreateClientByName(substr($posted, 5));
    }
    throw new RuntimeException('Select an active client from the client list.');
}
