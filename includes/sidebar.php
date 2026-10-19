<?php
// Ensure auth functions are available
if (!function_exists('isAdmin')) {
    require_once __DIR__ . '/auth.php';
}

// Sidebar - Department Headers on Top with Resize (Original Colors)
$department = $_SESSION['department'] ?? 'admin';
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));
$is_admin = isAdmin();
$is_manager = isManager();
$show_projects = canViewModule('projects');
$show_procurement = canViewModule('procurement');
$show_accounting = canViewModule('accounting');
$show_warehouse = canViewModule('warehouse');


// Only the section for the current page stays open.
$expanded_sections = [];
if ($current_module === 'accounting') {
    $expanded_sections[] = 'accounting';
} elseif ($current_module === 'procurement') {
    $expanded_sections[] = 'procurement';
} elseif ($current_module === 'warehouse') {
    $expanded_sections[] = 'warehouse';
} elseif ($current_module === 'projects' && !($is_admin && $current_page === 'projects.php')) {
    $expanded_sections[] = 'engineering';
} elseif ($current_module === 'admin' || $current_page === 'index.php' || ($is_admin && $current_page === 'projects.php')) {
    $expanded_sections[] = 'administration';
} elseif ($department === 'engineering') {
    $expanded_sections[] = 'engineering';
} elseif ($department === 'procurement') {
    $expanded_sections[] = 'procurement';
} elseif ($department === 'accounting') {
    $expanded_sections[] = 'accounting';
} elseif ($department === 'warehouse') {
    $expanded_sections[] = 'warehouse';
} else {
    $expanded_sections[] = 'administration';
}
$accounting_new_projects = 0;
if ($show_accounting && isset($pdo)) {
    try {
        $accounting_new_projects = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE accounting_status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        $accounting_new_projects = 0;
    }
}

// If no section is expanded, expand the user's department
if (empty($expanded_sections)) {
    $expanded_sections[] = $department;
}

// Get saved sidebar width from cookie
$sidebar_width = isset($_COOKIE['sidebar_width']) ? (int)$_COOKIE['sidebar_width'] : 280;
if ($sidebar_width < 200) $sidebar_width = 200;
if ($sidebar_width > 400) $sidebar_width = 400;
?>
<div class="sidebar" id="sidebar" style="width: <?php echo $sidebar_width; ?>px;">
    <!-- Resize Handle -->
    <div class="sidebar-resize-handle" id="sidebarResizeHandle">
        <div class="resize-dots">
            <span></span><span></span><span></span>
        </div>
    </div>
    
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="<?php echo APP_URL; ?>index.php" class="brand-logo-link" aria-label="RUNEHA home">
            <img src="<?php echo APP_URL; ?>assets/images/logo.png" alt="RUNEHA INC. logo" class="brand-logo-full">
        </a>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- User Profile -->
    <div class="sidebar-user">
        <div class="avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
            <div class="role">
                <span><?php echo htmlspecialchars(getDepartmentName($department)); ?></span>
                <span class="badge badge-<?php echo $is_admin ? 'admin' : ($is_manager ? 'manager' : 'staff'); ?>">
                    <?php echo $is_admin ? 'Admin' : ($is_manager ? 'Manager' : 'Staff'); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav" id="sidebarNav">
        <!-- ADMINISTRATION / EXECUTIVE ADMIN -->
        <?php if ($is_admin): ?>
        <div class="nav-section collapsible <?php echo in_array('administration', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-crown"></i>
                <span>Executive Admin</span>
                <i class="fas fa-chevron-<?php echo in_array('administration', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/projects.php?action=add" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'projects.php' && in_array(($_GET['action'] ?? ''), ['add', 'edit'], true) ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i>
                    <span>Project Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/quotations.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'quotations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i>
                    <span>Project Quotation</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/projects.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'projects.php' && !in_array(($_GET['action'] ?? ''), ['add', 'edit'], true) ? 'active' : ''; ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Project Monitoring</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/department_monitoring.php" class="nav-item <?php echo $current_page === 'department_monitoring.php' ? 'active' : ''; ?>">
                    <i class="fas fa-eye"></i>
                    <span>Department Status</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/users.php" class="nav-item <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>User & Role Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/audit_trail.php" class="nav-item <?php echo $current_page === 'audit_trail.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i>
                    <span>System Records</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/admin/consolidated_reports.php" class="nav-item <?php echo $current_page === 'consolidated_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Consolidated Reports</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ACCOUNTING DEPARTMENT -->
        <?php if ($show_accounting): ?>
        <div class="nav-section collapsible <?php echo in_array('accounting', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-calculator"></i>
                <span>Accounting</span>
                <i class="fas fa-chevron-<?php echo in_array('accounting', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/accounting/dashboard.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/supplier_quotations.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'supplier_quotations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Supplier Quotations</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/received_projects.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'received_projects.php' ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i>
                    <span>Received Projects</span>
                    <?php if ($accounting_new_projects > 0): ?>
                        <span class="nav-badge warning"><?php echo (int) $accounting_new_projects; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/project_financials.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'project_financials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-project-diagram"></i>
                    <span>Project Financial Setup</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/contracts.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'contracts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-contract"></i>
                    <span>Contract / Project Amount</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/labor_budget.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'labor_budget.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Labor / Salary Budget</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/expenses.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'expenses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Expense Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/funds_budget.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'funds_budget.php' ? 'active' : ''; ?>">
                    <i class="fas fa-wallet"></i>
                    <span>Funds Monitoring</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/assign_in_charge.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'assign_in_charge.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>Project In-Charge Assignment</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/financial_reports.php" class="nav-item <?php echo $current_module === 'accounting' && $current_page === 'financial_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Financial Reports</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ENGINEERING DEPARTMENT -->
        <?php if ($show_projects): ?>
        <div class="nav-section collapsible <?php echo in_array('engineering', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-hard-hat"></i>
                <span>Engineering</span>
                <i class="fas fa-chevron-<?php echo in_array('engineering', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/projects/dashboard.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/received_projects.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'received_projects.php' && !isset($_GET['requests']) ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i>
                    <span>Received Projects</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/project_details.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'project_details.php' && (int) ($_GET['id'] ?? 0) === 0 ? 'active' : ''; ?>">
                    <i class="fas fa-drafting-compass"></i>
                    <span>Project Planning</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/material_requirements.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'material_requirements.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Project Requirements</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/received_projects.php?requests=1" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'received_projects.php' && isset($_GET['requests']) ? 'active' : ''; ?>">
                    <i class="fas fa-paper-plane"></i>
                    <span>Material Requests</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/project_details.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'project_details.php' && (int) ($_GET['id'] ?? 0) > 0 ? 'active' : ''; ?>">
                    <i class="fas fa-hard-hat"></i>
                    <span>Project Implementation</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/accomplishments.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'accomplishments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Progress Updates</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/projects/project_reports.php" class="nav-item <?php echo $current_module === 'projects' && $current_page === 'project_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Accomplishment Reports</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- PROCUREMENT DEPARTMENT -->
        <?php if ($show_procurement): ?>
        <div class="nav-section collapsible <?php echo in_array('procurement', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-shopping-cart"></i>
                <span>Procurement</span>
                <i class="fas fa-chevron-<?php echo in_array('procurement', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/procurement/dashboard.php" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/received_requests.php" class="nav-item <?php echo $current_module === 'procurement' && ($current_page === 'received_requests.php' || $current_page === 'purchase_requests.php') ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i>
                    <span>Material Requests</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/suppliers.php" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'suppliers.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck"></i>
                    <span>Supplier Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/quotations.php" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'quotations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-balance-scale"></i>
                    <span>Price / Supplier Comparison</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/purchase_orders.php" class="nav-item <?php echo $current_module === 'procurement' && ($current_page === 'purchase_orders.php' || $current_page === 'purchase_history.php' || $current_page === 'materials.php') ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Purchase Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/reports.php?report=costs" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-coins"></i>
                    <span>Purchase Costs</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/accounting/purchase_costs.php" class="nav-item <?php echo $current_page === 'purchase_costs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i>
                    <span>Accounting Connection</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/procurement/purchased_materials.php" class="nav-item <?php echo $current_module === 'procurement' && $current_page === 'purchased_materials.php' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i>
                    <span>Warehouse Receiving Notification</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- WAREHOUSE DEPARTMENT -->
        <?php if ($show_warehouse): ?>
        <div class="nav-section collapsible <?php echo in_array('warehouse', $expanded_sections) ? 'expanded' : ''; ?>">
            <div class="nav-section-header" onclick="toggleSection(this)">
                <i class="fas fa-warehouse"></i>
                <span>Warehouse</span>
                <i class="fas fa-chevron-<?php echo in_array('warehouse', $expanded_sections) ? 'down' : 'right'; ?> arrow"></i>
            </div>
            <div class="nav-section-content">
                <a href="<?php echo APP_URL; ?>modules/warehouse/dashboard.php" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/inventory.php" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory Management</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/receive_purchases.php" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'receive_purchases.php' ? 'active' : ''; ?>">
                    <i class="fas fa-truck-loading"></i>
                    <span>Receiving</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/stock.php?action=in" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'stock.php' && (($_GET['action'] ?? '') !== 'out') ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-down"></i>
                    <span>Stock-In</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/material_requests.php" class="nav-item <?php echo $current_module === 'warehouse' && ($current_page === 'material_requests.php' || $current_page === 'project_materials.php') ? 'active' : ''; ?>">
                    <i class="fas fa-dolly"></i>
                    <span>Material Release</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/stock.php?action=out" class="nav-item <?php echo $current_module === 'warehouse' && $current_page === 'stock.php' && (($_GET['action'] ?? '') === 'out') ? 'active' : ''; ?>">
                    <i class="fas fa-arrow-up"></i>
                    <span>Stock-Out</span>
                </a>
                <a href="<?php echo APP_URL; ?>modules/warehouse/inventory_reports.php" class="nav-item <?php echo $current_module === 'warehouse' && in_array($current_page, ['inventory_reports.php', 'inventory_history.php', 'movements.php'], true) ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Inventory Reports</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ACCOUNT - Logout -->
        <div class="nav-section" style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.3rem;">
            <div class="nav-label">Account</div>
            <a href="<?php echo APP_URL; ?>logout.php" class="nav-item" style="color: #e74a3b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</div>

<!-- Styles - Original Colors Preserved -->
<style>
/* ============================================
   SIDEBAR - ORIGINAL COLORS WITH RESIZE
   ============================================ */

/* Sidebar - Original Dark Navy */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    background: linear-gradient(180deg, #1e2a3a 0%, #0f172a 100%);
    color: #fff;
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden;
    transition: none;
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.2) transparent;
    box-shadow: 2px 0 20px rgba(0,0,0,0.1);
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 2px;
}

/* Resize Handle */
.sidebar-resize-handle {
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 60px;
    cursor: col-resize;
    z-index: 1002;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sidebar:hover .sidebar-resize-handle {
    opacity: 1;
}

.sidebar-resize-handle .resize-dots {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
}

.sidebar-resize-handle .resize-dots span {
    display: block;
    width: 3px;
    height: 3px;
    background: rgba(255,255,255,0.5);
    border-radius: 50%;
}

.sidebar-resize-handle:hover .resize-dots span,
.sidebar-resize-handle.active .resize-dots span {
    background: #fbbf24;
}

/* Brand - Original */
.sidebar-brand {
    padding: 1rem 1.4rem 0.9rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-logo-link {
    flex: 1;
    display: block;
    min-width: 0;
}

.brand-logo-full {
    width: 100%;
    max-width: 220px;
    height: auto;
    display: block;
    object-fit: contain;
    filter: drop-shadow(0 6px 16px rgba(0,0,0,0.25));
}

.sidebar-toggle {
    display: none;
    background: none;
    border: none;
    color: #fff;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 4px;
    margin-left: auto;
}

/* Sidebar User Profile - Original */
.sidebar-user {
    padding: 0.8rem 1.8rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-user .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #facc15;
    flex-shrink: 0;
}

.sidebar-user .user-info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #fff;
}

.sidebar-user .user-info .role {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.5);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.sidebar-user .user-info .role .badge {
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 0.55rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-admin { background: #e74a3b; color: #fff; }
.badge-manager { background: #f59e0b; color: #0f172a; }
.badge-staff { background: rgba(255,255,255,0.15); color: #fff; }

/* Navigation - Original */
.sidebar-nav {
    flex: 1;
    padding: 0.3rem 0 0.5rem;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.nav-section {
    margin-bottom: 0.1rem;
}

.nav-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: rgba(255,255,255,0.3);
    padding: 0.6rem 1.8rem 0.3rem;
    font-weight: 700;
}

/* Collapsible Section Header - Original */
.nav-section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.45rem 1.8rem;
    color: rgba(255,255,255,0.7);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    font-weight: 500;
    user-select: none;
}

.nav-section-header:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.nav-section-header i:first-child {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.4);
}

.nav-section-header .arrow {
    margin-left: auto;
    font-size: 0.65rem;
    transition: transform 0.3s ease;
    color: rgba(255,255,255,0.3);
}

.nav-section.expanded .nav-section-header .arrow {
    transform: rotate(0deg);
}

.nav-section:not(.expanded) .nav-section-header .arrow {
    transform: rotate(-90deg);
}

/* Collapsible Content */
.nav-section-content {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
    padding-left: 0.5rem;
}

.nav-section.expanded .nav-section-content {
    max-height: 500px;
}

.nav-section-content .nav-item {
    padding-left: 3.5rem;
    font-size: 0.78rem;
    padding-top: 0.3rem;
    padding-bottom: 0.3rem;
}

/* Nav Items - Original */
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.4rem 1.8rem;
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.85rem;
    border-left: 3px solid transparent;
    cursor: pointer;
}

.nav-item:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.nav-item.active {
    background: rgba(251,191,36,0.12);
    color: #facc15;
    border-left-color: #fbbf24;
}

.nav-item i {
    width: 22px;
    text-align: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.nav-item .nav-badge {
    margin-left: auto;
    background: #e74a3b;
    color: #fff;
    font-size: 0.55rem;
    padding: 1px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.nav-item .nav-badge.warning {
    background: #f59e0b;
    color: #0f172a;
}

/* Push logout to bottom */
.nav-section:last-of-type {
    margin-top: auto;
    border-top: 1px solid rgba(255,255,255,0.06);
    padding-top: 0.3rem;
}

/* Mobile menu toggle */
.menu-toggle {
    display: none;
    position: fixed;
    top: 0.8rem;
    left: 0.8rem;
    z-index: 1001;
    background: #1a1a2e;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 0.8rem;
    font-size: 1.1rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Main content margin */
.main-content {
    margin-left: <?php echo $sidebar_width; ?>px;
    transition: margin-left 0.3s ease;
}

@media (max-width: 768px) {
    .menu-toggle {
        display: block;
    }
    .sidebar {
        transform: translateX(-100%);
        width: 280px !important;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .sidebar-toggle {
        display: block;
    }
    .sidebar-brand .brand-text .runeha {
        font-size: 15px;
    }
    .sidebar-user .user-info .name {
        font-size: 0.8rem;
    }
    .main-content {
        margin-left: 0 !important;
    }
    .sidebar-resize-handle {
        display: none;
    }
}

@media (min-width: 769px) {
    .sidebar-toggle {
        display: none !important;
    }
}
</style>

<script>
// ============================================
// SIDEBAR RESIZE FUNCTIONALITY
// ============================================
(function() {
    const sidebar = document.getElementById('sidebar');
    const resizeHandle = document.getElementById('sidebarResizeHandle');
    const mainContent = document.querySelector('.main-content');
    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    if (resizeHandle) {
        resizeHandle.addEventListener('mousedown', function(e) {
            isResizing = true;
            startX = e.clientX;
            startWidth = sidebar.offsetWidth;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            resizeHandle.classList.add('active');
            e.preventDefault();
        });
    }

    document.addEventListener('mousemove', function(e) {
        if (!isResizing) return;
        
        let newWidth = startWidth + (e.clientX - startX);
        if (newWidth < 200) newWidth = 200;
        if (newWidth > 400) newWidth = 400;
        
        sidebar.style.width = newWidth + 'px';
        if (mainContent) {
            mainContent.style.marginLeft = newWidth + 'px';
        }
    });

    document.addEventListener('mouseup', function() {
        if (isResizing) {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            resizeHandle.classList.remove('active');
            
            const newWidth = sidebar.offsetWidth;
            document.cookie = 'sidebar_width=' + newWidth + '; path=/; max-age=31536000';
        }
    });

    document.addEventListener('selectstart', function(e) {
        if (isResizing) {
            e.preventDefault();
        }
    });
})();

// ============================================
// COLLAPSIBLE SECTIONS
// ============================================
function toggleSection(header) {
    const section = header.closest('.nav-section');
    if (!section) {
        return;
    }
    const open = !section.classList.contains('expanded');
    document.querySelectorAll('.nav-section.collapsible.expanded').forEach(function (other) {
        if (other !== section) {
            other.classList.remove('expanded');
        }
    });
    section.classList.toggle('expanded', open);
}

// ============================================
// SIDEBAR TOGGLE (Mobile)
// ============================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Close sidebar on mobile when clicking a link
document.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            document.getElementById('sidebar').classList.remove('open');
        }
    });
});

// Close sidebar on outside click (mobile)
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggle?.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// Auto-expand section based on current page
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.nav-section.collapsible');
    let activeSection = null;
    sections.forEach(function (section) {
        if (section.querySelector('.nav-item.active')) {
            activeSection = section;
        }
    });
    if (!activeSection) {
        return;
    }
    sections.forEach(function (section) {
        section.classList.toggle('expanded', section === activeSection);
    });
});
</script>