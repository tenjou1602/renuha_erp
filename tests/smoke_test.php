<?php
/**
 * Signs in through localhost and opens every sidebar link.
 * Requires RUNEHA_DEV_LOGIN=1, and only from 127.0.0.1.
 * Run: C:\xampp\php\php.exe tests\smoke_test.php
 */
$base = 'http://127.0.0.1/runeha_erp/';
$cookieDir = sys_get_temp_dir();
$failed = 0;

function smokeRequest(string $url, string $cookie, array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 25,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $raw = $raw === false ? '' : $raw;
    $parts = explode("\r\n\r\n", $raw, 2);
    return ['status' => $status, 'body' => $parts[1] ?? $raw, 'error' => $error, 'raw' => $raw];
}

function smokeLogin(string $base, string $cookie, string $username, string $password): bool {
    smokeRequest($base . 'login.php', $cookie);
    $response = smokeRequest($base . 'login.php', $cookie, [
        'username' => $username,
        'password' => $password,
    ]);
    $location = '';
    if (preg_match('/^Location:\s*(\S+)/mi', $response['raw'], $match)) {
        $location = $match[1];
    }
    return $response['status'] === 302 && stripos($location, 'index.php') !== false;
}

function smokeCheck(string $label, bool $ok): void {
    global $failed;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

$sidebar = file_get_contents(dirname(__DIR__) . '/includes/sidebar.php');
preg_match_all('/href="<\?php echo APP_URL; \?>([^"]+)"/', $sidebar, $matches);
$paths = array_values(array_unique($matches[1]));

$adminCookie = $cookieDir . DIRECTORY_SEPARATOR . 'runeha_smoke_admin.txt';
@unlink($adminCookie);
$loggedIn = smokeLogin($base, $adminCookie, 'demo_admin_creator', 'Demo-Only-2026');
smokeCheck('admin demo login skips the email code on localhost', $loggedIn);

if ($loggedIn) {
    $banner = smokeRequest($base . 'modules/accounting/expenses.php', $adminCookie);
    smokeCheck('Viewing as Admin banner on a department page', str_contains($banner['body'], 'Viewing as Admin'));
    foreach ($paths as $path) {
        $url = $base . $path;
        $response = smokeRequest($url, $adminCookie);
        $badText = preg_match('/\b(Fatal error|Warning:|Notice:|Deprecated:)\b/', $response['body']);
        $ok = !$badText && (($response['status'] === 200 && trim($response['body']) !== '') || ($path === 'logout.php' && $response['status'] === 302));
        smokeCheck('admin ' . $response['status'] . ' ' . $path, $ok);
        if (!$ok && $response['error'] !== '') {
            echo '  curl ' . $response['error'] . PHP_EOL;
        }
    }
}

$acctCookie = $cookieDir . DIRECTORY_SEPARATOR . 'runeha_smoke_acct.txt';
@unlink($acctCookie);
$acctIn = smokeLogin($base, $acctCookie, 'demo_acct_creator', 'Demo-Only-2026');
smokeCheck('accounting creator login', $acctIn);
if ($acctIn) {
    $denied = smokeRequest($base . 'modules/procurement/dashboard.php', $acctCookie);
    $location = '';
    if (preg_match('/^Location:\s*(\S+)/mi', $denied['raw'], $match)) {
        $location = $match[1];
    }
    smokeCheck('accounting user is redirected away from procurement', $denied['status'] === 302 && stripos($location, 'index.php') !== false);
    $own = smokeRequest($base . 'modules/accounting/dashboard.php', $acctCookie);
    smokeCheck('accounting user opens accounting dashboard', $own['status'] === 200 && !preg_match('/\b(Fatal error|Warning:|Notice:)\b/', $own['body']));
}

echo $failed === 0 ? "SMOKE OK" . PHP_EOL : "SMOKE FAILED $failed" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
