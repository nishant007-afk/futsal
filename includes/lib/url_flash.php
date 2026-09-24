<?php

function base_url(string $path = ''): string
{
    // Works at any install location: computes the app's URL subdirectory from
    // its folder on disk relative to the web root ('' at the domain root,
    // '/futsal' under htdocs/futsal, etc.). An explicit BASE_PATH in .env wins.
    $root = '';
    $override = env('BASE_PATH');
    if ($override !== null) {
        $root = '/' . trim($override, '/');
    } else {
        $docRoot = str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        // File lives in includes/lib/  -  app root is two levels up.
        $appDir  = str_replace('\\', '/', (string)realpath(dirname(__DIR__, 2)));
        if ($docRoot !== '' && $appDir !== '' && strpos($appDir . '/', $docRoot . '/') === 0) {
            $root = rtrim(substr($appDir, strlen($docRoot)), '/');
        }
    }
    return rtrim($root, '/') . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $scheme = env('APP_SCHEME');
    if ($scheme === null) {
        $fwd = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $scheme = ($fwd === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . base_url($path);
}

function grounds_list_url(): string
{
    return base_url('pages/courts.php');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function http_error_page(int $code, string $title, string $message, ?string $cta_label = null, ?string $cta_url = null): void
{
    global $conn;
    http_response_code($code);
    $page_title = $title;
    require dirname(__DIR__) . '/header.php';
    require dirname(__DIR__) . '/views/error_page.php';
    require dirname(__DIR__) . '/footer.php';
    exit;
}

function set_flash(string $type, string $message, ?array $detail = null): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message, 'detail' => $detail];
}

function set_flash_error(string $what, ?string $why = null, ?string $how = null, ?string $how_url = null): void
{
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $what,
        'detail' => ['why' => $why, 'how' => $how, 'how_url' => $how_url],
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        set_flash('error', 'Invalid request. Please go back and try again.');
        $ref = $_SERVER['HTTP_REFERER'] ?? base_url('index.php');
        if (!is_string($ref) || $ref === '' || !preg_match('#^(https?://|/)#', $ref)) {
            $ref = base_url('index.php');
        }
        header('Location: ' . $ref, true, 303);
        exit;
    }
}

/**
 * Render a small inline POST form for a destructive action (vanilla, no framework).
 * Replaces state-changing GET links (?delete=, ?cancel=, ...) to avoid CSRF token
 * in URL / logs / Referer. $confirm adds data-confirm modal support (core.js).
 */
function post_action_form(string $actionUrl, string $field, string $value, string $labelHtml, string $btnClass = 'btn btn-outline btn-sm', string $confirm = '', string $ariaLabel = '', array $extra = []): string
{
    $h = '<form method="post" action="' . e($actionUrl) . '" class="d-inline">';
    $h .= csrf_field();
    $h .= '<input type="hidden" name="' . e($field) . '" value="' . e($value) . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . e((string)$k) . '" value="' . e((string)$v) . '">';
    }
    $h .= '<button type="submit" class="' . e($btnClass) . '"';
    if ($confirm !== '') { $h .= ' data-confirm="' . e($confirm) . '"'; }
    if ($ariaLabel !== '') { $h .= ' aria-label="' . e($ariaLabel) . '"'; }
    $h .= '>' . $labelHtml . '</button></form>';
    return $h;
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function release_stale_bookings(): void
{
    $now = time();
    if (isset($_SESSION['last_cleanup']) && $now - (int)$_SESSION['last_cleanup'] < 300) {
        return;
    }
    $_SESSION['last_cleanup'] = $now;

    global $conn;
    // Use the same setting as the cron (default 60) so web + CLI agree.
    $timeout = 60;
    try {
        $s = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'unpaid_cancel_timeout_minutes' LIMIT 1");
        if ($s && ($row = $s->fetch_assoc()) && is_numeric($row['setting_value'])) {
            $timeout = max(5, min(1440, (int)$row['setting_value']));
        }
    } catch (Exception $e) {
        // fall back to 60
    }
    $stmt = $conn->prepare(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE status = 'confirmed'
           AND payment_status = 'unpaid'
           AND TIMESTAMP(booking_date, start_time) < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    if ($stmt) {
        $stmt->bind_param('i', $timeout);
        $stmt->execute();
        $stmt->close();
    }
}
