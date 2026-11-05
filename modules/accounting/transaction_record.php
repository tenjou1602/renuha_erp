<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';
require_once '../../includes/department_transactions.php';

requireDepartment(['accounting']);
logActivity($_SESSION['user_id'] ?? null, 'Downloaded transaction record', 'Accounting', 'All expenses, invoices, and payments');
runehaPdfDocument('accounting-transaction-record.pdf', 'Accounting Transaction Record', buildDepartmentTransactionHtml($pdo, 'accounting'), 'landscape');
