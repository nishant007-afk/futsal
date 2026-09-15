<?php
/**
 * Full system check: render + auth + HTTP + audits.
 * Usage: php tools/system_check.php
 * CLI-only (also blocked via .htaccess for web).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: run via CLI only.');
}

$B = 'http://localhost/futsal';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/functions.php';
$PASS = 0;
$FAIL = 0;
$rows = [];

function check($label, $ok, $detail = ''): void
{
    global $PASS, $FAIL, $rows;
    if ($ok) {
        $PASS++;
        $rows[] = "  PASS  $label" . ($detail !== '' ? "  ($detail)" : '');
    } else {
        $FAIL++;
        $rows[] = "  FAIL  $label" . ($detail !== '' ? "  ($detail)" : '');
    }
}

function get_scope(string $url, ?string $cookieFile): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_COOKIEFILE     => $cookieFile ?? '',
        CURLOPT_COOKIEJAR      => $cookieFile ?? '',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    if ($errno !== 0) {
        return ['code' => 0, 'body' => '', 'final' => $url, 'error' => $err];
    }
    return ['code' => $code, 'body' => $raw, 'final' => $final, 'error' => ''];
}

fwrite(STDOUT, "== public pages (expect 200)\n");
// Use first active ground for the detail check (seed ids vary per env).
$activeGroundId = 1;
try {
    $gdb = @new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), (string)env('DB_PASS', ''), env('DB_NAME', 'futsal_booking'), (int)env('DB_PORT', '3306'));
    if (!$gdb->connect_errno) {
        $gr = $gdb->query('SELECT id FROM grounds WHERE is_active = 1 ORDER BY id LIMIT 1');
        if ($gr && ($grow = $gr->fetch_assoc())) { $activeGroundId = (int)$grow['id']; }
        $gdb->close();
    }
} catch (Throwable $e) { /* keep default */ }
$public = [
    '/'                                       => 200,
    '/pages/courts.php'                       => 200,
    '/pages/login.php'                        => 200,
    '/pages/register.php'                     => 200,
    '/pages/ground.php?id=' . $activeGroundId => 200,
    '/pages/forgot_password.php'              => 200,
    '/assets/css/style.css?v=258'             => 200,
    '/assets/vendor/fontawesome/css/all.min.css' => 200,
    '/assets/vendor/fontawesome/webfonts/fa-solid-900.woff2' => 200,
    '/uploads/grounds/ground_1_111d21fbae40.webp' => 200,
    '/ajax/search_suggest.php?q=ground'       => 200,
];
foreach ($public as $path => $want) {
    $out = get_scope($B . $path, '');
    check($path, $out['code'] === $want, 'HTTP ' . $out['code']);
}

fwrite(STDOUT, "\n== protected pages redirect to login when logged out ==\n");
$guarded = ['/pages/my_bookings.php', '/pages/notifications.php', '/pages/profile.php', '/manager/dashboard.php', '/admin/dashboard.php'];
foreach ($guarded as $url) {
    $out = get_scope($B . $url, '');
    check($url, $out['code'] === 302, 'HTTP ' . $out['code']);
}

fwrite(STDOUT, "\n== login as manager + role pages ==\n");
$jar = sys_get_temp_dir() . '/gs_check_cookies.txt';
@unlink($jar);
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $B . '/pages/login.php',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_COOKIEJAR      => $jar,
    CURLOPT_COOKIEFILE     => $jar,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$loginBody = curl_exec($ch);
curl_close($ch);
if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $loginBody, $m)) {
    check('login CSRF token present', true);
    $token = $m[1];
    // Test credentials come from env when set; defaults are local demo seeds only.
    $testManagerEmail = (string)(env('TEST_MANAGER_EMAIL') ?: 'manager@futsal.com');
    $testManagerPass = (string)(env('TEST_PASSWORD') ?: 'password123');
    $dbc = @new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), (string)env('DB_PASS', ''), env('DB_NAME', 'futsal_booking'), (int)env('DB_PORT', '3306'));
    if (!$dbc->connect_errno) {
        $escMgr = $dbc->real_escape_string($testManagerEmail);
        $dbc->query("UPDATE users SET login_count = 0 WHERE email = '$escMgr'");
        $dbc->close();
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $B . '/pages/login.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'csrf_token' => $token,
            'email'      => $testManagerEmail,
            'password'   => $testManagerPass,
            'remember_me'=> '1',
        ]),
        CURLOPT_COOKIEJAR  => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT    => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $redirect = str_contains($res, 'Location:');
    curl_close($ch);
    check('manager login POST', $code === 302 && $redirect, 'HTTP ' . $code);

    if (preg_match('/Set-Cookie:\s*PHPSESSID=([a-z0-9]+);/i', $res, $sess)) {
        file_put_contents($jar, "# Netscape HTTP Cookie File\n#HttpOnly_localhost\tFALSE\t/\tFALSE\t0\tPHPSESSID\t{$sess[1]}\n");
    }

    $rolePages = [
        '/index.php'                    => [200, 302],
        '/pages/my_bookings.php'        => [200, 302],
        '/pages/notifications.php'      => [200],
        '/pages/profile.php'            => [200],
        '/manager/dashboard.php'        => [200],
        '/manager/bookings.php'         => [200],
        '/manager/grounds.php'          => [200],
        '/manager/promos.php'           => [200],
    ];
    foreach ($rolePages as $url => $allowed) {
        $out = get_scope($B . $url, $jar);
        check($url, in_array($out['code'], $allowed, true), 'HTTP ' . $out['code']);
    }

    // player login: My Bookings must render with the new simple header
    $pJar = sys_get_temp_dir() . '/gs_check_player.txt';
    @unlink($pJar);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $B . '/pages/login.php', CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $pJar, CURLOPT_TIMEOUT => 30,
    ]);
    $pBody = curl_exec($ch);
    curl_close($ch);
    if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $pBody, $pt)) {
        $testPlayerEmail = (string)(env('TEST_PLAYER_EMAIL') ?: 'john@example.com');
        $dbc = @new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), (string)env('DB_PASS', ''), env('DB_NAME', 'futsal_booking'), (int)env('DB_PORT', '3306'));
        if (!$dbc->connect_errno) {
            $escP = $dbc->real_escape_string($testPlayerEmail);
            $dbc->query("UPDATE users SET login_count = 0 WHERE email = '$escP'");
            $dbc->close();
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $B . '/pages/login.php', CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $pt[1], 'email' => $testPlayerEmail, 'password' => $testManagerPass]),
            CURLOPT_COOKIEJAR => $pJar, CURLOPT_COOKIEFILE => $pJar, CURLOPT_TIMEOUT => 30,
        ]);
        $pRes = curl_exec($ch);
        curl_close($ch);
        if (preg_match('/Set-Cookie:\s*PHPSESSID=([a-z0-9]+);/i', $pRes, $ps)) {
            file_put_contents($pJar, "# Netscape HTTP Cookie File\n#HttpOnly_localhost\tFALSE\t/\tFALSE\t0\tPHPSESSID\t{$ps[1]}\n");
        }
        $out = get_scope($B . '/pages/my_bookings.php', $pJar);
        check('player my_bookings renders', $out['code'] === 200, 'HTTP ' . $out['code']);
        check('my_bookings uses simple header', str_contains($out['body'], 'bookings-head') && !str_contains($out['body'], 'bookings-hero'));
        @unlink($pJar);
    } else {
        check('player login CSRF', false, 'token regex failed');
    }
} else {
    check('login CSRF token present', false, 'token regex failed');
}
@unlink($jar);

fwrite(STDOUT, "\n== audits ==\n");
$root = __DIR__ . '/..';
$exclude = ['vendor', '.git', 'futsal_low'];

$phpClean = true;
$badPhp = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php' || strpos($f->getPathname(), '/assets/') !== false) {
        continue;
    }
    $p = $f->getPathname();
    if (strpos($p, str_replace('\\', '/', $root . '/vendor/')) !== false) {
        continue;
    }
    exec('php -l ' . escapeshellarg($p) . ' 2>&1', $o, $c);
    if ($c !== 0) {
        $badPhp[] = $p;
        $phpClean = false;
    }
}
check('php -l all php files', $phpClean, $badPhp ? implode(', ', array_map('basename', $badPhp)) : '');

$filesWithDashes = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') {
        continue;
    }
    $p = $f->getPathname();
    if (strpos($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $c = file_get_contents($p);
    if (strpos($c, "\xE2\x80\x94") !== false) {
        $filesWithDashes[] = substr($p, strlen($root) + 1);
    }
}
check('no em dashes in PHP', count($filesWithDashes) === 0, implode(', ', $filesWithDashes));

$css = file_get_contents($root . '/assets/css/style.css');
$glassSafe = preg_replace('/\.(?:popup-backdrop|sheet-backdrop)[^{]*\{[^}]*\}/s', '', $css);
check('CSS: blur only on popup backdrops', stripos($glassSafe, 'backdrop-filter') === false);
check('CSS: no blue/purple remnants', preg_match('/#2563eb|#4f46e5|#5b5bd6|#60a5fa|#667eea|#764ba2|#6366f1|#eef1ff|#eef2ff|#e8f1fd|#dfe4ff/i', $css) === 0);
check('CSS: body font = Manrope', strpos($css, "'Manrope'") !== false);
check('CSS: heading font = Barlow Condensed', strpos($css, '--font-display') !== false && strpos($css, "'Barlow Condensed'") !== false);
$gradCount = substr_count($css, 'gradient(');
    check('CSS: only functional gradients remain', $gradCount <= 14, "gradient() x$gradCount");

// DB
$db = @new mysqli(env('DB_HOST', 'localhost'), env('DB_USER', 'root'), (string)env('DB_PASS', ''), env('DB_NAME', 'futsal_booking'), (int)env('DB_PORT', '3306'));
check('DB connect', $db->connect_errno === 0, $db->connect_error ?? '');
if ($db->connect_errno === 0) {
    foreach (['users', 'grounds', 'ground_images', 'bookings', 'reviews', 'notifications'] as $t) {
        $r = $db->query('SELECT COUNT(*) c FROM ' . $t);
        check("table $t exists", (bool)$r, $r ? 'rows=' . $r->fetch_assoc()['c'] : 'missing');
    }
    $db->close();
}

fwrite(STDOUT, implode("\n", $rows) . "\n");
fwrite(STDOUT, "\n== RESULT: $PASS passed, $FAIL failed ==\n");
exit($FAIL > 0 ? 1 : 0);