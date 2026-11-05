<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';
require_once '../../includes/department_transactions.php';

requireDepartment(['procurement']);
logActivity($_SESSION['user_id'] ?? null, 'Downloaded transaction record', 'Procurement', 'All requests, quotations, and purchase orders');
runehaPdfDocument('procurement-transaction-record.pdf', 'Procurement Transaction Record', buildDepartmentTransactionHtml($pdo, 'procurement'), 'landscape');
