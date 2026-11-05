<?php
/**
 * PHPMailer wrapper for RUNEHA ERP.
 * Failed sends are logged and never thrown to the caller.
 */

if (!function_exists('sendMail')) {
    require_once __DIR__ . '/../config/mail.php';

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    function mailTemplate($title, $contentHtml) {
        $brand = htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'RUNEHA INC. ERP');
        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f8fafc;font-family:Segoe UI,Arial,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,0.08);">'
            . '<tr><td style="background:linear-gradient(135deg,#1e2a3a,#0f172a);padding:20px 24px;color:#fff;">'
            . '<div style="font-size:18px;font-weight:800;letter-spacing:0.4px;">RUNEHA <span style="color:#f59e0b;">INC.</span></div>'
            . '<div style="font-size:12px;color:#94a3b8;margin-top:4px;">' . $brand . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:28px 24px;">'
            . '<h1 style="margin:0 0 12px;font-size:20px;color:#0f172a;">' . htmlspecialchars($title) . '</h1>'
            . $contentHtml
            . '</td></tr>'
            . '<tr><td style="padding:14px 24px;background:#f8fafc;color:#94a3b8;font-size:12px;">This is an automated message. Please do not reply.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    function sendMail($to, $subject, $body) {
        try {
            $GLOBALS['runeha_last_mail_error'] = '';
            if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $GLOBALS['runeha_last_mail_error'] = 'Invalid recipient';
                logActivity($_SESSION['user_id'] ?? null, 'Email skipped', 'Mail', 'Invalid recipient: ' . (string) $to);
                return false;
            }

            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $GLOBALS['runeha_last_mail_error'] = 'PHPMailer is not installed';
                logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', 'PHPMailer is not installed. Run composer install.');
                return false;
            }

            if (!isSmtpConfigured()) {
                $GLOBALS['runeha_last_mail_error'] = 'SMTP credentials are not configured';
                logActivity($_SESSION['user_id'] ?? null, 'Email skipped', 'Mail', 'SMTP credentials are not configured. To: ' . $to . ' | ' . $subject);
                return false;
            }

            $from = trim((string) SMTP_FROM);
            if ($from === '') {
                $from = trim((string) SMTP_USER);
            }

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = trim((string) SMTP_USER);
            $mail->Password = trim((string) SMTP_PASS);
            $mail->SMTPSecure = SMTP_SECURE ?: PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;
            if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
                $mail->SMTPDebug = 2;
            }

            $mail->setFrom($from, SMTP_FROM_NAME);
            $mail->addReplyTo($from, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n"], $body))));
            $mail->send();
            return true;
        } catch (Throwable $e) {
            $GLOBALS['runeha_last_mail_error'] = $e->getMessage();
            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage() . ' | To: ' . $to . ' | ' . $subject);
            return false;
        }
    }

    function sendLoginOtpEmail($to, $full_name, $code) {
        $title = 'Your login verification code';
        $content = '<p style="margin:0 0 12px;color:#475569;">Hello ' . htmlspecialchars($full_name) . ',</p>'
            . '<p style="margin:0 0 16px;color:#475569;">Use this one-time code to finish signing in to RUNEHA ERP:</p>'
            . '<div style="display:inline-block;background:#0f172a;color:#f59e0b;font-size:28px;font-weight:800;letter-spacing:6px;padding:14px 22px;border-radius:12px;">'
            . htmlspecialchars($code) . '</div>'
            . '<p style="margin:18px 0 0;color:#64748b;font-size:13px;">This code expires in 5 minutes. If you did not request it, you can ignore this email.</p>';
        return sendMail($to, 'RUNEHA ERP login code', mailTemplate($title, $content));
    }

    function notifyDepartment($department, $subject, $body, $role = null) {
        global $pdo;
        try {
            $sql = "SELECT email, full_name FROM users WHERE department = ? AND status = 'active'";
            $params = [$department];
            if ($role) {
                $sql .= " AND role = ?";
                $params[] = $role;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sent = 0;
            foreach ($stmt->fetchAll() as $user) {
                if (!empty($user['email'])) {
                    try {
                        if (sendMail($user['email'], $subject, mailTemplate($subject, $body))) {
                            $sent++;
                        }
                    } catch (Throwable $e) {
                        logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
                    }
                }
            }
            return $sent;
        } catch (Throwable $e) {
            logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
            return 0;
        }
    }

    function notifyPurchaseRequestSubmitted($pr_number, $purpose, $requestor_name) {
        $subject = 'New purchase request: ' . $pr_number;
        $body = '<p>A new purchase request has been submitted.</p>'
            . '<p><strong>PR:</strong> ' . htmlspecialchars($pr_number) . '<br>'
            . '<strong>Requestor:</strong> ' . htmlspecialchars($requestor_name) . '<br>'
            . '<strong>Purpose:</strong> ' . nl2br(htmlspecialchars($purpose)) . '</p>';
        notifyDepartment('procurement', $subject, $body, 'manager');
    }

    function notifyPurchaseOrderApproved($po_number, $supplier, $supplier_email = null) {
        $subject = 'Purchase order approved: ' . $po_number;
        $body = '<p>Purchase order <strong>' . htmlspecialchars($po_number) . '</strong> has been approved/confirmed.</p>'
            . '<p><strong>Supplier:</strong> ' . htmlspecialchars($supplier) . '</p>';
        notifyDepartment('procurement', $subject, $body);
        if (!empty($supplier_email)) {
            try {
                sendMail($supplier_email, $subject, mailTemplate($subject, $body));
            } catch (Throwable $e) {
                logActivity($_SESSION['user_id'] ?? null, 'Email failed', 'Mail', $e->getMessage());
            }
        }
    }

    function notifyInvoiceGenerated($invoice_number, $client, $amount) {
        $subject = 'Invoice generated: ' . $invoice_number;
        $body = '<p>A new invoice has been generated.</p>'
            . '<p><strong>Invoice:</strong> ' . htmlspecialchars($invoice_number) . '<br>'
            . '<strong>Client:</strong> ' . htmlspecialchars($client) . '<br>'
            . '<strong>Amount:</strong> ₱' . number_format((float) $amount, 2) . '</p>';
        notifyDepartment('accounting', $subject, $body);
    }

    function notifyLowStock($material_name, $current_stock, $min_stock, $unit = 'pcs') {
        $subject = 'Low stock alert: ' . $material_name;
        $body = '<p>A material has reached or fallen below its minimum stock level.</p>'
            . '<p><strong>Material:</strong> ' . htmlspecialchars($material_name) . '<br>'
            . '<strong>Current stock:</strong> ' . htmlspecialchars((string) $current_stock) . ' ' . htmlspecialchars((string) $unit) . '<br>'
            . '<strong>Minimum stock:</strong> ' . htmlspecialchars((string) $min_stock) . ' ' . htmlspecialchars((string) $unit) . '</p>'
            . '<p>Please review warehouse inventory and replenish as needed.</p>';
        notifyDepartment('warehouse', $subject, $body);
    }

    function notifyPurchaseRequestConfirmed($pr_number, $purpose = '') {
        $subject = 'Purchase request confirmed by admin: ' . $pr_number;
        $body = '<p>Purchase request <strong>' . htmlspecialchars($pr_number) . '</strong> has been confirmed by an administrator and is ready to convert into a Purchase Order.</p>';
        if ($purpose !== '') {
            $body .= '<p><strong>Purpose:</strong> ' . htmlspecialchars($purpose) . '</p>';
        }
        notifyDepartment('procurement', $subject, $body);
    }

    function reminderClaim(string $recipient, string $subject, string $reference, string $period): bool {
        global $pdo;
        try {
            $existing = $pdo->prepare("SELECT status FROM email_log WHERE recipient = ? AND reference_key = ? AND period_key = ?");
            $existing->execute([$recipient, $reference, $period]);
            $status = $existing->fetchColumn();
            if ($status === 'sent') {
                return false;
            }
            if ($status === 'failed') {
                $pdo->prepare("UPDATE email_log SET status = 'sent', subject = ?, error_text = NULL, sent_at = NOW() WHERE recipient = ? AND reference_key = ? AND period_key = ?")
                    ->execute([$subject, $recipient, $reference, $period]);
                return true;
            }
            $pdo->prepare("INSERT INTO email_log (recipient, subject, reference_key, period_key, status) VALUES (?, ?, ?, ?, 'sent')")
                ->execute([$recipient, $subject, $reference, $period]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    function reminderFail(string $recipient, string $reference, string $period, string $error): void {
        global $pdo;
        try {
            $pdo->prepare("UPDATE email_log SET status = 'failed', error_text = ? WHERE recipient = ? AND reference_key = ? AND period_key = ?")
                ->execute([mb_substr($error, 0, 500), $recipient, $reference, $period]);
        } catch (Throwable $e) {
            error_log('[Runeha ERP] reminder log: ' . $e->getMessage());
        }
    }

    function departmentMailRecipients(string $department): array {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE department = ? AND status = 'active' AND email <> ''");
        $stmt->execute([$department]);
        $seen = [];
        $recipients = [];
        foreach ($stmt->fetchAll() as $user) {
            $email = strtolower(trim((string) $user['email']));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email]) || str_ends_with($email, '@example.com')) {
                continue;
            }
            $seen[$email] = true;
            $user['email'] = $email;
            $recipients[] = $user;
        }
        return $recipients;
    }

    function sendLoggedMail(string $to, string $subject, string $htmlBody, string $reference, string $period): bool {
        if (!reminderClaim($to, $subject, $reference, $period)) {
            return false;
        }
        if (!sendMail($to, $subject, mailTemplate($subject, $htmlBody))) {
            reminderFail($to, $reference, $period, (string) ($GLOBALS['runeha_last_mail_error'] ?? 'SMTP send returned false'));
            return false;
        }
        return true;
    }

    function notifyProjectArrived(int $projectId): void {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, project_code, name, client, estimated_budget, accounting_status, engineering_received_at FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
            if (!$project) {
                return;
            }
            $code = (string) $project['project_code'];
            $details = '<p><strong>Project:</strong> ' . htmlspecialchars($code . ' — ' . (string) $project['name']) . '<br>'
                . '<strong>Client:</strong> ' . htmlspecialchars((string) ($project['client'] ?? '')) . '<br>'
                . '<strong>Amount:</strong> ₱' . number_format((float) $project['estimated_budget'], 2) . '</p>';
            $acctLink = APP_URL . 'modules/accounting/received_projects.php';
            $acctBody = '<p>A new project is waiting for Accounting to approve or reject.</p>' . $details
                . '<p>You have 7 days from today to approve or reject it. Reminders stop after 7 days, or when the project is approved or rejected.</p>'
                . '<p><a href="' . htmlspecialchars($acctLink) . '">Open Received Projects</a></p>';
            if (($project['accounting_status'] ?? '') === 'pending') {
                foreach (departmentMailRecipients('accounting') as $user) {
                    sendLoggedMail($user['email'], 'New project to approve: ' . $code, $acctBody, 'project-accounting-arrival:' . $projectId, 'arrival');
                }
            }
            if (empty($project['engineering_received_at']) && ($project['accounting_status'] ?? '') !== 'rejected') {
                $engLink = APP_URL . 'modules/projects/received_projects.php';
                $engBody = '<p>A new project is waiting in Engineering Received Projects.</p>' . $details
                    . '<p><a href="' . htmlspecialchars($engLink) . '">Open Received Projects</a></p>';
                foreach (departmentMailRecipients('engineering') as $user) {
                    sendLoggedMail($user['email'], 'New project for Engineering: ' . $code, $engBody, 'project-engineering-arrival:' . $projectId, 'arrival');
                }
            }
        } catch (Throwable $e) {
            error_log('[Runeha ERP] project arrival email: ' . $e->getMessage());
        }
    }

    function lookupSupplierEmail($supplier_name) {
        global $pdo;
        if (empty($supplier_name)) {
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT email FROM suppliers WHERE name = ? AND email IS NOT NULL AND email != '' LIMIT 1");
            $stmt->execute([$supplier_name]);
            $row = $stmt->fetch();
            return $row['email'] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
