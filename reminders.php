<?php
/**
 * Hourly accounting reminders. Run from Windows Task Scheduler:
 * Program: C:\xampp\php\php.exe
 * Arguments: C:\xampp\htdocs\runeha_erp\reminders.php
 * Start in: C:\xampp\htdocs\runeha_erp
 * Repeat: every 1 hour.
 *
 * SMTP is config/mail.php (host, port, username, app password, from).
 * PHP mail() is not used. Set RUNEHA_REMINDER_HOURS to change the 24-hour wait
 * for expenses and supplier quotations.
 * A new project emails Accounting and Engineering immediately.
 * Accounting project reminders continue once a day for at most 7 days, then stop.
 * Engineering gets one more email if the project is still not received after 1 day.
 * A failed send is written to email_log and does not stop the run.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

if (!defined('REMINDER_HOURS')) {
    define('REMINDER_HOURS', 24);
}

$hours = max(1, (int) REMINDER_HOURS);
$accountants = [];
try {
    $accountants = $pdo->query("SELECT id, email, full_name FROM users WHERE department = 'accounting' AND status = 'active' AND email <> ''")->fetchAll();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
$seen = [];
$recipients = [];
foreach ($accountants as $user) {
    $email = strtolower(trim((string) $user['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {
        continue;
    }
    $seen[$email] = true;
    $recipients[] = $user;
}

$pending = [];
try {
    $stmt = $pdo->prepare("SELECT e.id, e.expense_number AS ref, e.amount, e.created_at, 'expense' AS kind, p.name AS project_name FROM expenses e LEFT JOIN projects p ON p.id = e.project_id WHERE e.status = 'pending' AND e.created_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)");
    $stmt->execute([$hours]);
    $pending = array_merge($pending, $stmt->fetchAll());
    $stmt = $pdo->prepare("
        SELECT q.id, q.quotation_number AS ref, q.total_amount AS amount, q.submitted_to_accounting_at AS created_at, 'quotation' AS kind, p.name AS project_name
        FROM quotations q
        LEFT JOIN projects p ON p.id = q.project_id
        WHERE q.submitted_to_accounting_at IS NOT NULL
          AND q.submitted_to_accounting_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)
          AND COALESCE((
              SELECT e.status FROM purchase_orders po
              LEFT JOIN expenses e ON e.purchase_order_id = po.id
              WHERE po.purchase_request_id = q.purchase_request_id AND po.status <> 'cancelled'
              ORDER BY po.id DESC LIMIT 1
          ), 'pending') <> 'paid'
    ");
    $stmt->execute([$hours]);
    $pending = array_merge($pending, $stmt->fetchAll());
    $stmt = $pdo->prepare("SELECT fr.id, fr.request_number AS ref, fr.amount, fr.requested_at AS created_at, 'fund request' AS kind, p.name AS project_name FROM fund_requests fr INNER JOIN projects p ON p.id = fr.project_id WHERE fr.status = 'pending' AND fr.requested_at <= DATE_SUB(NOW(), INTERVAL ? HOUR)");
    $stmt->execute([$hours]);
    $pending = array_merge($pending, $stmt->fetchAll());
} catch (Throwable $e) {
    error_log('[Runeha ERP] reminder query: ' . $e->getMessage());
}

$period = date('Y-m-d');
$digestRows = '';
foreach ($pending as $item) {
    $waiting = 'more than ' . $hours . ' hours';
    $links = [
        'expense' => 'modules/accounting/expenses.php?action=view&id=' . (int) $item['id'],
        'quotation' => 'modules/accounting/supplier_quotations.php?action=view&id=' . (int) $item['id'],
        'fund request' => 'modules/accounting/fund_requests.php?id=' . (int) $item['id'],
        'financial' => 'modules/accounting/project_financials.php',
    ];
    $link = APP_URL . ($links[$item['kind']] ?? 'modules/accounting/received_projects.php');
    $digestRows .= '<li>' . htmlspecialchars($item['kind'] . ' ' . $item['ref']) . ' — ₱' . number_format((float) $item['amount'], 2) . ' — waiting ' . $waiting . ' — <a href="' . htmlspecialchars($link) . '">Open</a></li>';
    foreach ($recipients as $user) {
        $key = $item['kind'] . ':' . $item['id'];
        $subject = 'Pending approval: ' . $item['ref'];
        if (!reminderClaim($user['email'], $subject, $key, $period)) {
            continue;
        }
        $body = mailTemplate('Approval waiting', '<p>' . htmlspecialchars($item['kind']) . ' <strong>' . htmlspecialchars($item['ref']) . '</strong> for project ' . htmlspecialchars((string) ($item['project_name'] ?? '')) . ' has been pending for ' . $waiting . '.</p><p>Amount: ₱' . number_format((float) $item['amount'], 2) . '</p><p><a href="' . htmlspecialchars($link) . '">Open the approval page</a></p>');
        if (!sendMail($user['email'], $subject, $body)) {
            reminderFail($user['email'], $key, $period, 'SMTP send returned false');
        }
    }
}

if ($digestRows !== '') {
    foreach ($recipients as $user) {
        $subject = 'Daily pending approvals';
        if (!reminderClaim($user['email'], $subject, 'digest', $period)) {
            continue;
        }
        $body = mailTemplate('Daily digest', '<p>These items are still pending:</p><ul>' . $digestRows . '</ul>');
        if (!sendMail($user['email'], $subject, $body)) {
            reminderFail($user['email'], 'digest', $period, 'SMTP send returned false');
        }
    }
}

$projectMails = 0;
try {
    $acctLink = APP_URL . 'modules/accounting/received_projects.php';
    $acctProjects = $pdo->query("
        SELECT id, project_code, name, client, estimated_budget, created_at
        FROM projects
        WHERE accounting_status = 'pending'
          AND archived_at IS NULL
          AND EXISTS (
              SELECT 1 FROM email_log e
              WHERE e.reference_key = CONCAT('project-accounting-arrival:', projects.id)
                AND e.status = 'sent'
                AND e.sent_at <= DATE_SUB(NOW(), INTERVAL 1 DAY)
                AND e.sent_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
          )
    ")->fetchAll();
    foreach ($acctProjects as $project) {
        $sentAt = $pdo->prepare("SELECT sent_at FROM email_log WHERE reference_key = ? AND status = 'sent' ORDER BY sent_at ASC LIMIT 1");
        $sentAt->execute(['project-accounting-arrival:' . (int) $project['id']]);
        $started = $sentAt->fetchColumn() ?: $project['created_at'];
        $ageDays = max(1, (int) floor((time() - strtotime((string) $started)) / 86400));
        $daysLeft = max(0, 7 - $ageDays);
        $subject = 'Reminder: approve or reject ' . $project['project_code'];
        $body = '<p><strong>' . htmlspecialchars($project['project_code'] . ' — ' . $project['name']) . '</strong> is still waiting for Accounting.</p>'
            . '<p>Client: ' . htmlspecialchars((string) ($project['client'] ?? '')) . '<br>Amount: ₱' . number_format((float) $project['estimated_budget'], 2) . '</p>'
            . '<p>It has been waiting ' . $ageDays . ' day' . ($ageDays === 1 ? '' : 's') . '. '
            . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' remain in the 7-day approval window. Approve or reject it before the window ends.</p>'
            . '<p><a href="' . htmlspecialchars($acctLink) . '">Open Received Projects</a></p>';
        foreach (departmentMailRecipients('accounting') as $user) {
            if (sendLoggedMail($user['email'], $subject, $body, 'project-accounting-reminder:' . (int) $project['id'], $period)) {
                $projectMails++;
            }
        }
    }

    $engLink = APP_URL . 'modules/projects/received_projects.php';
    $engProjects = $pdo->query("
        SELECT p.id, p.project_code, p.name, p.client, p.estimated_budget, p.created_at
        FROM projects p
        WHERE p.engineering_received_at IS NULL
          AND p.archived_at IS NULL
          AND p.accounting_status <> 'rejected'
          AND EXISTS (
              SELECT 1 FROM email_log e
              WHERE e.reference_key = CONCAT('project-engineering-arrival:', p.id)
                AND e.status = 'sent'
                AND e.sent_at <= DATE_SUB(NOW(), INTERVAL 1 DAY)
          )
    ")->fetchAll();
    foreach ($engProjects as $project) {
        $subject = 'Reminder: project not received ' . $project['project_code'];
        $body = '<p><strong>' . htmlspecialchars($project['project_code'] . ' — ' . $project['name']) . '</strong> has been waiting in Engineering for at least 1 day and has not been received.</p>'
            . '<p>Client: ' . htmlspecialchars((string) ($project['client'] ?? '')) . '<br>Amount: ₱' . number_format((float) $project['estimated_budget'], 2) . '</p>'
            . '<p><a href="' . htmlspecialchars($engLink) . '">Open Received Projects</a></p>';
        foreach (departmentMailRecipients('engineering') as $user) {
            if (sendLoggedMail($user['email'], $subject, $body, 'project-engineering-unseen:' . (int) $project['id'], 'day1')) {
                $projectMails++;
            }
        }
    }
} catch (Throwable $e) {
    error_log('[Runeha ERP] project reminder: ' . $e->getMessage());
}

echo 'Reminders checked: ' . count($pending) . '; project emails sent: ' . $projectMails . PHP_EOL;
