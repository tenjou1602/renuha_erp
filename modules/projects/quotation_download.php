<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/quotation_file.php';

requireDepartment(['engineering', 'procurement', 'warehouse', 'accounting', 'admin']);

$projectId = (int) ($_GET['project_id'] ?? 0);
$file = $projectId > 0 ? projectQuotationAttachment($pdo, $projectId) : null;
if (!$file) {
    $_SESSION['error'] = 'No Excel file is attached to this project.';
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
header('Content-Length: ' . (string) filesize($file['absolute']));
header('X-Content-Type-Options: nosniff');
readfile($file['absolute']);
exit();
