<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

$uid = (int) $_SESSION['user_id'];
$dept = (string) ($_SESSION['department'] ?? '');
$scope = notificationScopeSql();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'read_all') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND {$scope}");
        $stmt->execute(['uid' => $uid, 'dept' => $dept]);
    } elseif ($action === 'read') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND {$scope}");
        $stmt->execute(['id' => $id, 'uid' => $uid, 'dept' => $dept]);
    }
}

$count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND {$scope}");
$count->execute(['uid' => $uid, 'dept' => $dept]);
$list = $pdo->prepare("SELECT notification_id, title, message, link_url, is_read, created_at FROM notifications WHERE {$scope} ORDER BY created_at DESC LIMIT 10");
$list->execute(['uid' => $uid, 'dept' => $dept]);

echo json_encode([
    'ok' => true,
    'unread' => (int) $count->fetchColumn(),
    'items' => $list->fetchAll(),
]);
