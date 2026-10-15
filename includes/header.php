<?php
/**
 * Header include - Common header for all pages
 * 
 * This file should be included at the top of every page
 * It requires that $page_title is defined before inclusion
 */

// Ensure config is loaded
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/database.php';
}

// Ensure auth functions are available
if (!function_exists('checkAuth')) {
    require_once __DIR__ . '/auth.php';
}

$page_title = $page_title ?? 'RUNEHA INC. ERP';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: time(); ?>">
</head>
<body>
    <!-- Mobile menu toggle -->
    <button class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <?php 
    // Include sidebar if it exists
    $sidebar_file = __DIR__ . '/sidebar.php';
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    }
    ?>
    
    <div class="main-content">
        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
        <?php if (function_exists('scanOperationalAlerts')) { scanOperationalAlerts(); } ?>
        <div class="topbar">
            <button type="button" class="bell-btn" id="notifBell" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="bell-badge" id="notifBadge" hidden>0</span>
            </button>
            <div class="notif-drop" id="notifDrop" hidden>
                <div class="notif-head">
                    <strong>Notifications</strong>
                    <button type="button" id="notifReadAll">Mark all as read</button>
                </div>
                <div id="notifList"></div>
            </div>
        </div>
        <script>
        (function () {
            var feed = <?php echo json_encode(APP_URL . 'notifications_feed.php'); ?>;
            var bell = document.getElementById('notifBell');
            var drop = document.getElementById('notifDrop');
            var badge = document.getElementById('notifBadge');
            var list = document.getElementById('notifList');
            function paint(data) {
                badge.hidden = !data.unread;
                badge.textContent = data.unread > 99 ? '99+' : String(data.unread || 0);
                list.innerHTML = '';
                if (!data.items || !data.items.length) {
                    list.innerHTML = '<p class="notif-empty">No notifications.</p>';
                    return;
                }
                data.items.forEach(function (item) {
                    var row = document.createElement('a');
                    row.className = 'notif-item' + (Number(item.is_read) ? '' : ' unread');
                    row.href = item.link_url ? (<?php echo json_encode(APP_URL); ?> + item.link_url.replace(/^\/+/, '')) : '#';
                    var when = document.createElement('small');
                    when.textContent = item.created_at || '';
                    var title = document.createElement('strong');
                    title.textContent = item.title || '';
                    var msg = document.createElement('span');
                    msg.textContent = item.message || '';
                    var mark = document.createElement('button');
                    mark.type = 'button';
                    mark.textContent = 'Mark as read';
                    mark.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        var body = new FormData();
                        body.append('action', 'read');
                        body.append('id', item.notification_id);
                        fetch(feed, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(paint);
                    });
                    row.appendChild(title);
                    row.appendChild(msg);
                    row.appendChild(when);
                    row.appendChild(mark);
                    list.appendChild(row);
                });
            }
            function load() {
                fetch(feed, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(paint).catch(function () {});
            }
            bell.addEventListener('click', function () { drop.hidden = !drop.hidden; });
            document.getElementById('notifReadAll').addEventListener('click', function () {
                var body = new FormData();
                body.append('action', 'read_all');
                fetch(feed, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(paint);
            });
            load();
            setInterval(load, 30000);
        })();
        </script>
        <?php endif; ?>
        <div class="container">