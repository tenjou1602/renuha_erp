<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/accounting_ledger.php';
require_once '../../includes/report_export.php';

requireDepartment(['accounting', 'procurement']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT i.*, p.project_code, p.name AS project_name, u.full_name AS created_by_name,
           COALESCE(cq.quotation_number, pq.quotation_number) AS quotation_number,
           c.address AS client_address, c.email AS client_email, c.phone AS client_phone,
           c.tin_number AS client_tin, c.contact_person AS client_contact,
           " . invoiceAccountingPaidSql('i') . " AS accounting_paid
    FROM invoices i
    LEFT JOIN projects p ON p.id = i.project_id
    LEFT JOIN users u ON u.id = i.created_by
    LEFT JOIN clients c ON c.id = i.client_id
    LEFT JOIN client_quotations cq ON cq.id = i.quotation_id
    LEFT JOIN quotations pq ON pq.id = i.procurement_quotation_id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    exit('Invoice not found.');
}
$invoice = invoiceWithAccountingPayment($invoice);

runehaStreamPdf(
    'invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $invoice['invoice_number']) . '.pdf',
    buildInvoiceReportHtml($pdo, $invoice)
);
