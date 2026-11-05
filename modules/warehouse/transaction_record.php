<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';
require_once '../../includes/department_transactions.php';

requireDepartment(['warehouse']);
logActivity($_SESSION['user_id'] ?? null, 'Downloaded transaction record', 'Warehouse', 'All materials and stock movements');
runehaPdfDocument('warehouse-transaction-record.pdf', 'Warehouse Transaction Record', buildDepartmentTransactionHtml($pdo, 'warehouse'), 'landscape');
