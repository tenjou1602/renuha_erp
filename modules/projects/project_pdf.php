<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireDepartment(['engineering']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid project selected for PDF download.';
    header('Location: projects.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT p.*, vpc.actual_cost, vpc.pending_cost, vpc.budget_variance, u.full_name AS in_charge_name FROM projects p LEFT JOIN v_project_costs vpc ON vpc.project_id = p.id LEFT JOIN users u ON u.id = COALESCE(p.in_charge_id, p.assigned_to) WHERE p.id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) {
        $_SESSION['error'] = 'Project not found.';
        header('Location: projects.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Unable to load project for PDF.';
    header('Location: projects.php');
    exit();
}

logActivity(
    $_SESSION['user_id'],
    'Downloaded project PDF',
    'Projects',
    'Project: ' . $project['project_code']
);

require_once '../../includes/report_export.php';
runehaPdfDocument(
    'project-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $project['project_code']) . '.pdf',
    'Project Report',
    buildProjectReportHtml($pdo, $project)
);
