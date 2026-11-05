<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/report_export.php';
require_once '../../includes/department_transactions.php';

requireDepartment(['engineering']);
logActivity($_SESSION['user_id'] ?? null, 'Downloaded transaction record', 'Engineering', 'All projects, progress, and material requests');
runehaPdfDocument('engineering-transaction-record.pdf', 'Engineering Transaction Record', buildDepartmentTransactionHtml($pdo, 'engineering'), 'landscape');
