<?php
/**
 * CLI checks that do not change existing rows.
 * New rows are inserted inside a transaction and rolled back.
 */
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/auth.php';

$failed = 0;
function check(bool $ok, string $label): void {
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

$_SESSION['user_id'] = 1;
$threw = false;
try {
    assertNotOwnApproval(1);
} catch (RuntimeException $e) {
    $threw = true;
}
check($threw, 'creator cannot approve own item');
$threw = false;
try {
    assertNotOwnApproval(999999);
} catch (RuntimeException $e) {
    $threw = true;
}
check(!$threw, 'a different user can approve');

$before = [];
foreach (['projects', 'expenses', 'purchase_orders', 'users', 'invoices', 'adjustments', 'month_closures'] as $table) {
    $before[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO month_closures (department, period, closed_by) VALUES ('accounting', '2099-01', NULL)")->execute();
$closed = false;
try {
    assertMonthOpen('accounting', '2099-01-15');
} catch (RuntimeException $e) {
    $closed = str_contains($e->getMessage(), 'closed');
}
check($closed, 'closed month rejects a direct dated action');
[$openPeriod, $source] = resolveAdjustmentPeriod('accounting', '2099-01');
check($openPeriod === date('Y-m') && $source === '2099-01', 'adjustment for a closed month moves to the open month');
$pdo->rollBack();

foreach ($before as $table => $count) {
    $now = (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    check($now === $count, "row count unchanged for $table ($count)");
}

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO adjustments (department, period, type, amount_before, amount_after, difference, reason, status, created_by) VALUES ('accounting', '2026-10', 'rule check', 10, 15, 5, 'rule check only', 'approved', 1)")->execute();
$withAdjustment = reportedActualCost(100.0);
$pdo->rollBack();
check(abs($withAdjustment - 105.0) < 0.001, 'approved adjustment adds 5 to the reported actual cost');

$base = 100.0;
$reported = reportedActualCost($base);
check(abs($reported - ($base + (float) (adjustmentFigures()['by_dept']['accounting'] ?? 0) + (float) (adjustmentFigures()['by_dept']['procurement'] ?? 0))) < 0.001, 'reported actual cost adds each adjustment once');

echo $failed === 0 ? "RULES OK" . PHP_EOL : "RULES FAILED $failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
