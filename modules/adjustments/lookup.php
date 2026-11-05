<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/adjustment_refs.php';

header('Content-Type: application/json');

$department = $_GET['department'] ?? '';
$allowed = ['accounting', 'engineering', 'procurement', 'warehouse', 'admin'];
if (!in_array($department, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose a department.']);
    exit();
}
if ($department === 'admin') {
    requireAdmin();
} elseif ($department === 'engineering') {
    requireDepartment(['engineering']);
} else {
    requireDepartment([$department]);
}

$type = $_GET['reference_type'] ?? '';
$types = adjustmentReferenceTypesFor($department, isAdmin());
if (!isset($types[$type])) {
    http_response_code(403);
    echo json_encode(['error' => 'You cannot adjust that record type.']);
    exit();
}

echo json_encode(adjustmentSearchReferences($pdo, $type, (string) ($_GET['q'] ?? '')));
