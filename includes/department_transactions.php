<?php

function departmentTxCell($value): string
{
    return htmlspecialchars((string) $value);
}

function departmentTxRows(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll();
}

function buildDepartmentTransactionHtml(PDO $pdo, string $department): string
{
    if ($department === 'warehouse') {
        $materials = departmentTxRows($pdo, "
            SELECT material_code, name, category, unit, current_stock, min_stock, cost_per_unit, status, created_at
            FROM materials
            ORDER BY created_at, id
        ");
        $movements = departmentTxRows($pdo, "
            SELECT sm.created_at, sm.movement_type, sm.quantity, sm.reason, m.material_code, m.name, p.project_code
            FROM stock_movements sm
            LEFT JOIN materials m ON m.id = sm.material_id
            LEFT JOIN projects p ON p.id = sm.project_id
            ORDER BY sm.created_at, sm.id
        ");
        $stockValue = 0.0;
        $materialRows = [];
        foreach ($materials as $row) {
            $stockValue += (float) $row['current_stock'] * (float) $row['cost_per_unit'];
            $materialRows[] = [
                departmentTxCell($row['material_code']),
                departmentTxCell($row['name']),
                departmentTxCell($row['category'] ?: 'N/A'),
                departmentTxCell($row['unit']),
                departmentTxCell(number_format((float) $row['current_stock'], 0)),
                departmentTxCell(number_format((float) $row['min_stock'], 0)),
                runehaPdfMoney($row['cost_per_unit']),
                departmentTxCell(ucfirst((string) $row['status'])),
                departmentTxCell(runehaPdfDate($row['created_at'])),
            ];
        }
        $movementRows = [];
        foreach ($movements as $row) {
            $movementRows[] = [
                departmentTxCell(runehaPdfDate($row['created_at'])),
                departmentTxCell($row['material_code'] ?: 'N/A'),
                departmentTxCell($row['name'] ?: 'N/A'),
                departmentTxCell(ucfirst((string) $row['movement_type'])),
                departmentTxCell(number_format((float) $row['quantity'], 0)),
                departmentTxCell($row['project_code'] ?: 'N/A'),
                departmentTxCell($row['reason'] ?: ''),
            ];
        }
        $html = runehaPdfInfo([
            ['Department', 'Warehouse'],
            ['Record', 'All saved materials and stock movements'],
            ['Materials', departmentTxCell((string) count($materials))],
            ['Stock movements', departmentTxCell((string) count($movements))],
            ['Current stock value', runehaPdfMoney($stockValue)],
        ]);
        $html .= runehaPdfSection('Materials', runehaPdfTable(
            ['Code', 'Name', 'Category', 'Unit', 'Stock', 'Min', 'Unit Cost', 'Status', 'Created'],
            $materialRows
        ));
        $html .= runehaPdfSection('Stock Movements', runehaPdfTable(
            ['Date', 'Code', 'Material', 'Type', 'Qty', 'Project', 'Reason'],
            $movementRows
        ));
        return $html;
    }

    if ($department === 'procurement') {
        $requests = departmentTxRows($pdo, "
            SELECT pr.pr_number, pr.purpose, pr.status, pr.total_amount, pr.created_at, p.project_code
            FROM purchase_requests pr
            LEFT JOIN projects p ON p.id = pr.project_id
            ORDER BY pr.created_at, pr.id
        ");
        $quotes = departmentTxRows($pdo, "
            SELECT q.quotation_number, q.supplier, q.status, q.total_amount, q.created_at, p.project_code
            FROM quotations q
            LEFT JOIN projects p ON p.id = q.project_id
            ORDER BY q.created_at, q.id
        ");
        $orders = departmentTxRows($pdo, "
            SELECT po.po_number, po.supplier, po.status, po.total_amount, po.order_date, po.created_at, p.project_code
            FROM purchase_orders po
            LEFT JOIN projects p ON p.id = po.project_id
            ORDER BY po.created_at, po.id
        ");
        $requestRows = [];
        $requestTotal = 0.0;
        foreach ($requests as $row) {
            $requestTotal += (float) $row['total_amount'];
            $requestRows[] = [
                departmentTxCell($row['pr_number']),
                departmentTxCell($row['project_code'] ?: 'N/A'),
                departmentTxCell($row['purpose']),
                departmentTxCell(ucfirst((string) $row['status'])),
                runehaPdfMoney($row['total_amount']),
                departmentTxCell(runehaPdfDate($row['created_at'])),
            ];
        }
        $quoteRows = [];
        $quoteTotal = 0.0;
        foreach ($quotes as $row) {
            $quoteTotal += (float) $row['total_amount'];
            $quoteRows[] = [
                departmentTxCell($row['quotation_number']),
                departmentTxCell($row['project_code'] ?: 'N/A'),
                departmentTxCell($row['supplier']),
                departmentTxCell(ucfirst((string) $row['status'])),
                runehaPdfMoney($row['total_amount']),
                departmentTxCell(runehaPdfDate($row['created_at'])),
            ];
        }
        $orderRows = [];
        $orderTotal = 0.0;
        foreach ($orders as $row) {
            $orderTotal += (float) $row['total_amount'];
            $orderRows[] = [
                departmentTxCell($row['po_number']),
                departmentTxCell($row['project_code'] ?: 'N/A'),
                departmentTxCell($row['supplier']),
                departmentTxCell(ucfirst(str_replace('_', ' ', (string) $row['status']))),
                runehaPdfMoney($row['total_amount']),
                departmentTxCell(runehaPdfDate($row['order_date'] ?: $row['created_at'])),
            ];
        }
        $html = runehaPdfInfo([
            ['Department', 'Procurement'],
            ['Record', 'All saved requests, quotations, and purchase orders'],
            ['Requests', departmentTxCell((string) count($requests))],
            ['Quotations', departmentTxCell((string) count($quotes))],
            ['Purchase orders', departmentTxCell((string) count($orders))],
            ['Purchase order total', runehaPdfMoney($orderTotal)],
        ]);
        $html .= runehaPdfSection('Purchase Requests', runehaPdfTable(
            ['Request', 'Project', 'Purpose', 'Status', 'Amount', 'Date'],
            $requestRows,
            $requestRows ? ['', '', '', 'Total', runehaPdfMoney($requestTotal), ''] : null
        ));
        $html .= runehaPdfSection('Supplier Quotations', runehaPdfTable(
            ['Quotation', 'Project', 'Supplier', 'Status', 'Amount', 'Date'],
            $quoteRows,
            $quoteRows ? ['', '', '', 'Total', runehaPdfMoney($quoteTotal), ''] : null
        ));
        $html .= runehaPdfSection('Purchase Orders', runehaPdfTable(
            ['PO Number', 'Project', 'Supplier', 'Status', 'Amount', 'Date'],
            $orderRows,
            $orderRows ? ['', '', '', 'Total', runehaPdfMoney($orderTotal), ''] : null
        ));
        return $html;
    }

    if ($department === 'accounting') {
        $expenses = departmentTxRows($pdo, "
            SELECT e.expense_number, e.category, e.description, e.amount, e.status, e.expense_date, p.project_code
            FROM expenses e
            LEFT JOIN projects p ON p.id = e.project_id
            ORDER BY e.expense_date, e.id
        ");
        $invoices = departmentTxRows($pdo, "
            SELECT i.invoice_number, i.client, i.client_name, i.amount, i.paid_amount, i.status, i.invoice_date, p.project_code
            FROM invoices i
            LEFT JOIN projects p ON p.id = i.project_id
            ORDER BY i.invoice_date, i.id
        ");
        $payments = departmentTxRows($pdo, "
            SELECT py.payment_number, py.amount, py.payment_method, py.reference_number, py.payment_date, i.invoice_number
            FROM payments py
            LEFT JOIN invoices i ON i.id = py.invoice_id
            ORDER BY py.payment_date, py.id
        ");
        $expenseRows = [];
        $expenseTotal = 0.0;
        foreach ($expenses as $row) {
            $expenseTotal += (float) $row['amount'];
            $expenseRows[] = [
                departmentTxCell($row['expense_number']),
                departmentTxCell($row['project_code'] ?: 'N/A'),
                departmentTxCell($row['category']),
                departmentTxCell($row['description']),
                departmentTxCell(ucfirst((string) $row['status'])),
                runehaPdfMoney($row['amount']),
                departmentTxCell(runehaPdfDate($row['expense_date'])),
            ];
        }
        $invoiceRows = [];
        $invoiceTotal = 0.0;
        $collected = 0.0;
        foreach ($invoices as $row) {
            $invoiceTotal += (float) $row['amount'];
            $collected += (float) $row['paid_amount'];
            $client = trim((string) ($row['client_name'] ?: $row['client']));
            $invoiceRows[] = [
                departmentTxCell($row['invoice_number']),
                departmentTxCell($client !== '' ? $client : 'N/A'),
                departmentTxCell($row['project_code'] ?: 'N/A'),
                runehaPdfMoney($row['amount']),
                runehaPdfMoney($row['paid_amount']),
                departmentTxCell(ucfirst((string) $row['status'])),
                departmentTxCell(runehaPdfDate($row['invoice_date'])),
            ];
        }
        $paymentRows = [];
        $paymentTotal = 0.0;
        foreach ($payments as $row) {
            $paymentTotal += (float) $row['amount'];
            $paymentRows[] = [
                departmentTxCell($row['payment_number']),
                departmentTxCell($row['invoice_number'] ?: 'N/A'),
                departmentTxCell($row['payment_method'] ?: 'N/A'),
                departmentTxCell($row['reference_number'] ?: ''),
                runehaPdfMoney($row['amount']),
                departmentTxCell(runehaPdfDate($row['payment_date'])),
            ];
        }
        $html = runehaPdfInfo([
            ['Department', 'Accounting'],
            ['Record', 'All saved expenses, invoices, and payments'],
            ['Expenses', departmentTxCell((string) count($expenses))],
            ['Expense total', runehaPdfMoney($expenseTotal)],
            ['Invoices', departmentTxCell((string) count($invoices))],
            ['Invoice total', runehaPdfMoney($invoiceTotal)],
            ['Collected on invoices', runehaPdfMoney($collected)],
            ['Payments', departmentTxCell((string) count($payments))],
        ]);
        $html .= runehaPdfSection('Expenses', runehaPdfTable(
            ['Expense', 'Project', 'Category', 'Description', 'Status', 'Amount', 'Date'],
            $expenseRows,
            $expenseRows ? ['', '', '', '', 'Total', runehaPdfMoney($expenseTotal), ''] : null
        ));
        $html .= runehaPdfSection('Invoices', runehaPdfTable(
            ['Invoice', 'Client', 'Project', 'Amount', 'Paid', 'Status', 'Date'],
            $invoiceRows,
            $invoiceRows ? ['', '', 'Total', runehaPdfMoney($invoiceTotal), runehaPdfMoney($collected), '', ''] : null
        ));
        $html .= runehaPdfSection('Payments', runehaPdfTable(
            ['Payment', 'Invoice', 'Method', 'Reference', 'Amount', 'Date'],
            $paymentRows,
            $paymentRows ? ['', '', '', 'Total', runehaPdfMoney($paymentTotal), ''] : null
        ));
        return $html;
    }

    $projects = departmentTxRows($pdo, "
        SELECT project_code, name, client, status, estimated_budget, start_date, created_at, archived_at
        FROM projects
        ORDER BY created_at, id
    ");
    $progress = departmentTxRows($pdo, "
        SELECT a.created_at, a.description, a.completion_percentage, a.status, p.project_code, u.full_name
        FROM accomplishments a
        LEFT JOIN projects p ON p.id = a.project_id
        LEFT JOIN users u ON u.id = a.created_by
        ORDER BY a.created_at, a.id
    ");
    $requests = departmentTxRows($pdo, "
        SELECT pr.pr_number, pr.purpose, pr.status, pr.total_amount, pr.created_at, p.project_code
        FROM purchase_requests pr
        LEFT JOIN projects p ON p.id = pr.project_id
        ORDER BY pr.created_at, pr.id
    ");
    $projectRows = [];
    foreach ($projects as $row) {
        $projectRows[] = [
            departmentTxCell($row['project_code']),
            departmentTxCell($row['name']),
            departmentTxCell($row['client'] ?: 'N/A'),
            departmentTxCell(ucfirst(str_replace('_', ' ', (string) $row['status']))),
            runehaPdfMoney($row['estimated_budget']),
            departmentTxCell(runehaPdfDate($row['start_date'])),
            departmentTxCell($row['archived_at'] ? 'Archived' : 'Active'),
        ];
    }
    $progressRows = [];
    foreach ($progress as $row) {
        $progressRows[] = [
            departmentTxCell(runehaPdfDate($row['created_at'])),
            departmentTxCell($row['project_code'] ?: 'N/A'),
            departmentTxCell($row['description']),
            departmentTxCell(ucfirst(str_replace('_', ' ', (string) $row['status']))),
            departmentTxCell(number_format((float) $row['completion_percentage'], 1) . '%'),
            departmentTxCell($row['full_name'] ?: 'N/A'),
        ];
    }
    $requestRows = [];
    $requestTotal = 0.0;
    foreach ($requests as $row) {
        $requestTotal += (float) $row['total_amount'];
        $requestRows[] = [
            departmentTxCell($row['pr_number']),
            departmentTxCell($row['project_code'] ?: 'N/A'),
            departmentTxCell($row['purpose']),
            departmentTxCell(ucfirst((string) $row['status'])),
            runehaPdfMoney($row['total_amount']),
            departmentTxCell(runehaPdfDate($row['created_at'])),
        ];
    }
    $html = runehaPdfInfo([
        ['Department', 'Engineering'],
        ['Record', 'All saved projects, progress updates, and material requests'],
        ['Projects', departmentTxCell((string) count($projects))],
        ['Progress updates', departmentTxCell((string) count($progress))],
        ['Material requests', departmentTxCell((string) count($requests))],
    ]);
    $html .= runehaPdfSection('Projects', runehaPdfTable(
        ['Code', 'Name', 'Client', 'Status', 'Budget', 'Start', 'Record'],
        $projectRows
    ));
    $html .= runehaPdfSection('Progress Updates', runehaPdfTable(
        ['Date', 'Project', 'Description', 'Status', 'Completion', 'Recorded By'],
        $progressRows
    ));
    $html .= runehaPdfSection('Material Requests', runehaPdfTable(
        ['Request', 'Project', 'Purpose', 'Status', 'Amount', 'Date'],
        $requestRows,
        $requestRows ? ['', '', '', 'Total', runehaPdfMoney($requestTotal), ''] : null
    ));
    return $html;
}
