<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$user = getCurrentUser();
$department = $_SESSION['department'] ?? 'admin';
$role = $_SESSION['role'] ?? 'staff';
$is_manager = isManager();
$is_admin = isAdmin();

// Redirect non-admin users to their department dashboard
if (!$is_admin) {
    switch ($department) {
        case 'procurement':
            header('Location: modules/procurement/dashboard.php');
            exit();
        case 'accounting':
            header('Location: modules/accounting/dashboard.php');
            exit();
        case 'engineering':
            header('Location: modules/projects/dashboard.php');
            exit();
        case 'warehouse':
            header('Location: modules/warehouse/dashboard.php');
            exit();
        default:
            header('Location: modules/projects/dashboard.php');
            exit();
    }
}

// Executive Admin Dashboard only for admin users
$page_title = 'Executive Dashboard - ' . APP_NAME;

// Get consolidated statistics
$stats = [];
$department_summaries = [];
$items_needing_attention = [];
$recent_accomplishments = [];
$recent_department_activity = [];

try {
    // Project Statistics
    $stats['active_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    $stats['total_project_amount'] = $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0;
    $stats['total_project_expenses'] = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0;
    
    // Financial Statistics
    try {
        $stats['available_funds'] = $pdo->query("SELECT COALESCE(SUM(amount - allocated_amount), 0) FROM funds WHERE status = 'active'")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $stats['available_funds'] = 0;
    }
    
    $stats['pending_purchases'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status IN ('pending', 'approved')")->fetchColumn() ?? 0;
    
    // Warehouse Statistics
    $stats['inventory_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0;
    $stats['low_stock_items'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    
    // Department Summaries
    $department_summaries['procurement'] = [
        'name' => 'Procurement',
        'icon' => 'shopping-cart',
        'color' => '#f59e0b',
        'total_prs' => $pdo->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn() ?? 0,
        'pending_prs' => $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0,
        'total_value' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status IN ('approved', 'confirmed', 'ordered', 'received')")->fetchColumn() ?? 0
    ];
    
    $department_summaries['accounting'] = [
        'name' => 'Accounting',
        'icon' => 'chart-line',
        'color' => '#36b9cc',
        'total_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0,
        'total_expenses' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'paid'")->fetchColumn() ?? 0,
        'funds_count' => 0
    ];
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM funds WHERE status = 'active'");
        if ($result) {
            $department_summaries['accounting']['funds_count'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $department_summaries['accounting']['funds_count'] = 0;
    }
    
    $department_summaries['engineering'] = [
        'name' => 'Engineering',
        'icon' => 'hard-hat',
        'color' => '#4e73df',
        'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0,
        'ongoing_projects' => $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0,
        'accomplishments' => 0
    ];
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM accomplishments");
        if ($result) {
            $department_summaries['engineering']['accomplishments'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $department_summaries['engineering']['accomplishments'] = 0;
    }
    
    $department_summaries['warehouse'] = [
        'name' => 'Warehouse',
        'icon' => 'warehouse',
        'color' => '#1cc88a',
        'total_stock' => $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials WHERE status = 'active'")->fetchColumn() ?? 0,
        'stock_movements' => $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn() ?? 0,
        'material_requests' => 0
    ];
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'");
        if ($result) {
            $department_summaries['warehouse']['material_requests'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $department_summaries['warehouse']['material_requests'] = 0;
    }
    
    // Items Needing Attention
    $items_needing_attention['low_stock'] = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND min_stock > 0 AND status = 'active'")->fetchColumn() ?? 0;
    $items_needing_attention['pending_prs'] = $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn() ?? 0;
    $items_needing_attention['pending_material_requests'] = 0;
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'");
        if ($result) {
            $items_needing_attention['pending_material_requests'] = $result->fetchColumn() ?? 0;
        }
    } catch (PDOException $e) {
        $items_needing_attention['pending_material_requests'] = 0;
    }
    $items_needing_attention['unassigned_pic'] = 0;
    try {
        $items_needing_attention['unassigned_pic'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE COALESCE(assigned_to, in_charge_id) IS NULL AND status IN ('planning','ongoing')")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $items_needing_attention['unassigned_pic'] = 0;
    }
    $items_needing_attention['overdue_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status IN ('planning', 'ongoing')")->fetchColumn() ?? 0;
    
    // Recent Accomplishments
    try {
        $recent_accomplishments = $pdo->query("
            SELECT a.*, p.project_code, p.name as project_name, u.full_name as created_by_name
            FROM accomplishments a
            LEFT JOIN projects p ON a.project_id = p.id
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY a.created_at DESC
            LIMIT 5
        ")->fetchAll();
    } catch (PDOException $e) {
        $recent_accomplishments = [];
    }
    
    // Recent Department Activity
    $recent_department_activity = $pdo->query("
        SELECT al.*, u.full_name, u.department
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 15
    ")->fetchAll();
    
    $stats['planning_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'planning'")->fetchColumn() ?? 0;
    $stats['on_hold_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'on_hold'")->fetchColumn() ?? 0;
    $stats['ongoing_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn() ?? 0;
    $stats['completed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn() ?? 0;
    $stats['pending_pos'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft', 'sent', 'confirmed')")->fetchColumn() ?? 0;
    $stats['pending_invoices'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent', 'partial')")->fetchColumn() ?? 0;
    $stats['total_projects'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
    $stats['pending_projects'] = (int) ($stats['planning_projects'] ?? 0) + (int) ($stats['on_hold_projects'] ?? 0);
    $stats['delayed_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE end_date < CURDATE() AND status IN ('planning', 'ongoing', 'on_hold')")->fetchColumn() ?? 0;
    $stats['completed_purchases'] = 0;
    $stats['incoming_deliveries'] = (int) ($stats['pending_pos'] ?? 0);
    $stats['outgoing_requests'] = (int) ($items_needing_attention['pending_material_requests'] ?? 0);
    $stats['delayed_deliveries'] = 0;
    $stats['contract_value'] = (float) ($stats['total_project_amount'] ?? 0);
    $stats['collected'] = 0;
    try {
        $stats['completed_purchases'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('received', 'delivered', 'completed')")->fetchColumn() ?? 0;
        $stats['incoming_deliveries'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('sent', 'confirmed', 'ready_for_warehouse') AND received_at IS NULL")->fetchColumn() ?? 0;
        $stats['delayed_deliveries'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE received_at IS NULL AND status IN ('sent', 'confirmed', 'ready_for_warehouse') AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        try {
            $stats['incoming_deliveries'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('sent', 'confirmed')")->fetchColumn() ?? 0;
        } catch (PDOException $e2) {
            $stats['incoming_deliveries'] = (int) ($stats['pending_pos'] ?? 0);
        }
    }
    try {
        $contract = $pdo->query("SELECT COALESCE(SUM(contract_amount), 0) FROM project_financials")->fetchColumn();
        if ($contract !== false && (float) $contract > 0) {
            $stats['contract_value'] = (float) $contract;
        }
    } catch (PDOException $e) {
        $stats['contract_value'] = (float) ($stats['total_project_amount'] ?? 0);
    }
    try {
        $stats['collected'] = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices")->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        try {
            $stats['collected'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status = 'paid'")->fetchColumn() ?? 0;
        } catch (PDOException $e2) {
            $stats['collected'] = 0;
        }
    }

    $cost_trend_labels = [];
    $cost_trend_budget = [];
    $cost_trend_actual = [];
    try {
        $trend_rows = $pdo->query("
            SELECT DATE_FORMAT(COALESCE(start_date, created_at), '%b') AS label,
                   DATE_FORMAT(COALESCE(start_date, created_at), '%Y-%m') AS ym,
                   COALESCE(SUM(estimated_budget), 0) AS budget,
                   COALESCE(SUM(actual_cost), 0) AS actual
            FROM projects
            WHERE COALESCE(start_date, created_at) >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY ym, label
            ORDER BY ym
        ")->fetchAll();
        foreach ($trend_rows as $row) {
            $cost_trend_labels[] = $row['label'];
            $cost_trend_budget[] = (float) $row['budget'];
            $cost_trend_actual[] = (float) $row['actual'];
        }
    } catch (PDOException $e) {
        $cost_trend_labels = [];
    }

    try {
        $pdo->exec("UPDATE projects
            SET project_code = CONCAT('PRJ-', YEAR(IFNULL(created_at, NOW())), '-', LPAD(id, 3, '0'))
            WHERE project_code LIKE '%E+%' OR project_code LIKE '%e+%' OR CHAR_LENGTH(project_code) > 24");
    } catch (PDOException $e) {
        // non-fatal
    }

    // Project Overview
    try {
        $project_overview = $pdo->query("
            SELECT p.id, p.project_code, p.name, p.status, p.location, p.client, p.start_date, p.end_date,
                   p.estimated_budget, p.actual_cost, p.progress,
                   COALESCE(ua.full_name, ui.full_name) AS in_charge_name
            FROM projects p
            LEFT JOIN users ua ON p.assigned_to = ua.id
            LEFT JOIN users ui ON p.in_charge_id = ui.id
            ORDER BY p.created_at DESC
            LIMIT 8
        ")->fetchAll();
    } catch (PDOException $e) {
        try {
            $project_overview = $pdo->query("
                SELECT id, project_code, name, status, location, client, start_date, end_date, estimated_budget, actual_cost, progress
                FROM projects
                ORDER BY created_at DESC
                LIMIT 8
            ")->fetchAll();
        } catch (PDOException $e2) {
            $project_overview = $pdo->query("
                SELECT id, project_code, name, status, location, start_date, end_date, estimated_budget, actual_cost
                FROM projects
                ORDER BY created_at DESC
                LIMIT 8
            ")->fetchAll();
        }
    }
    
    // Consolidated Reports Summary
    $consolidated_reports = [
        'total_projects' => $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0,
        'total_budget' => $pdo->query("SELECT COALESCE(SUM(estimated_budget), 0) FROM projects")->fetchColumn() ?? 0,
        'total_actual_cost' => $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) FROM projects")->fetchColumn() ?? 0,
        'total_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn() ?? 0,
        'total_invoice_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM invoices")->fetchColumn() ?? 0,
        'total_expenses' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expenses")->fetchColumn() ?? 0,
        'total_stock_movements' => $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn() ?? 0,
        'activity_count' => $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn() ?? 0,
    ];
    
} catch (PDOException $e) {
    $error = userDatabaseError($e);
}

if (!isset($project_overview)) {
    $project_overview = [];
}
if (!isset($cost_trend_labels)) {
    $cost_trend_labels = [];
    $cost_trend_budget = [];
    $cost_trend_actual = [];
}

if (!function_exists('dashboardTimeProgress')) {
function dashboardTimeProgress(array $project) {
    $status = $project['status'] ?? '';
    if ($status === 'completed') {
        return 100.0;
    }
    $manual = isset($project['progress']) && $project['progress'] !== null && $project['progress'] !== ''
        ? (float) $project['progress']
        : 0.0;
    if ($manual > 0) {
        return max(0.0, min(100.0, $manual));
    }
    $start = !empty($project['start_date']) ? strtotime($project['start_date']) : false;
    $end = !empty($project['end_date']) ? strtotime($project['end_date']) : false;
    if (!$start || !$end || $end <= $start) {
        return 0.0;
    }
    return max(0.0, min(100.0, ((time() - $start) / ($end - $start)) * 100));
}
}
include 'includes/header.php';
include 'includes/exec_admin_view.php';
include 'includes/footer.php';
