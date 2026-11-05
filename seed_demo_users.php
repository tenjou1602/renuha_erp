<?php
/**
 * Manual demo users only. Never include this from a page.
 *
 * Password for every new demo account: Demo-Only-2026
 * Emails use example.com so they are obviously not real staff.
 * Existing usernames are left unchanged.
 *
 * Run: C:\xampp\php\php.exe C:\xampp\htdocs\runeha_erp\seed_demo_users.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/config/database.php';

$demos = [
    ['demo_admin_creator', 'Demo Admin Creator', 'demo.admin.creator@example.com', 'admin', 'admin'],
    ['demo_admin_approver', 'Demo Admin Approver', 'demo.admin.approver@example.com', 'admin', 'admin'],
    ['demo_acct_creator', 'Demo Accounting Creator', 'demo.accounting.creator@example.com', 'accounting', 'staff'],
    ['demo_acct_approver', 'Demo Accounting Approver', 'demo.accounting.approver@example.com', 'accounting', 'manager'],
    ['demo_eng_creator', 'Demo Engineering Creator', 'demo.engineering.creator@example.com', 'engineering', 'staff'],
    ['demo_eng_approver', 'Demo Engineering Approver', 'demo.engineering.approver@example.com', 'engineering', 'manager'],
    ['demo_proc_creator', 'Demo Procurement Creator', 'demo.procurement.creator@example.com', 'procurement', 'staff'],
    ['demo_proc_approver', 'Demo Procurement Approver', 'demo.procurement.approver@example.com', 'procurement', 'manager'],
    ['demo_wh_creator', 'Demo Warehouse Creator', 'demo.warehouse.creator@example.com', 'warehouse', 'staff'],
    ['demo_wh_approver', 'Demo Warehouse Approver', 'demo.warehouse.approver@example.com', 'warehouse', 'manager'],
];

$hash = password_hash('Demo-Only-2026', PASSWORD_DEFAULT);
$check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$insert = $pdo->prepare('INSERT INTO users (username, password, full_name, email, department, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
$added = 0;
foreach ($demos as $demo) {
    $check->execute([$demo[0], $demo[2]]);
    if ($check->fetch()) {
        echo "kept existing {$demo[0]}" . PHP_EOL;
        continue;
    }
    $insert->execute([$demo[0], $hash, $demo[1], $demo[2], $demo[3], $demo[4], 'active']);
    echo "added {$demo[0]}" . PHP_EOL;
    $added++;
}
echo "Added $added demo users. Existing users were not changed." . PHP_EOL;
