<?php

function base_url(string $path = ''): string
{
    // If $path is already an absolute HTTP(S) URL or protocol-relative URL, return as-is
    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }

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

    $cleanPath = '/' . ltrim($path, '/');
    // Prevent duplicate root prefix (e.g. /futsal/futsal/... or base_url(base_url(...)))
    if ($root !== '' && (strpos($cleanPath, $root . '/') === 0 || $cleanPath === $root)) {
        return $cleanPath;
    }

    return rtrim($root, '/') . $cleanPath;
}

function absolute_url(string $path = ''): string
{
    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }
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
    // Ensure page scrolls to main content on load, not the bottom
    if (strpos($path, '#') === false) {
        $path .= '#mainContent';
    }
    header('Location: ' . base_url($path));
    exit;
}

/**
 * Consume the post-login destination saved in $_SESSION['return_path']
 * (set by pages/ground.php while a logged-out player picks court/date/time).
 * Redirects there when it is a safe same-site app path; falls through
 * silently otherwise so the caller can apply its own fallback.
 */
function consume_return_path(): void
{
    $returnPath = $_SESSION['return_path'] ?? '';
    unset($_SESSION['return_path']);
    if (!is_string($returnPath) || $returnPath === '') {
        return;
    }
    if ($returnPath[0] !== '/') {
        $returnPath = '/' . ltrim($returnPath, '/');
    }
    if ($returnPath[0] === '/'
        && (!isset($returnPath[1]) || $returnPath[1] !== '/')
        && !preg_match('#^//[^/]#', $returnPath)
        && preg_match('#^/(pages|admin|manager|ajax|auth|index\.php)#', $returnPath)
    ) {
        redirect(ltrim($returnPath, '/'));
    }
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

/**
 * Keeps a redirect target on this site. Absolute URLs are rejected outright so a
 * spoofed HTTP_REFERER can never bounce a user off-site (open redirect).
 */
function safe_same_site_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || !is_string($url)) {
        return '';
    }
    // Protocol-relative (//evil.com) and absolute (https://evil.com) are both out.
    if (strpos($url, '//') === 0 || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
        return '';
    }
    // Must be a root-relative path.
    return strpos($url, '/') === 0 ? $url : '';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        set_flash('error', 'Invalid request. Please go back and try again.');
        $ref = safe_same_site_url($_SERVER['HTTP_REFERER'] ?? '');
        header('Location: ' . ($ref !== '' ? $ref : base_url('index.php')), true, 303);
        exit;
    }
}

/**
 * Render a small inline POST form for a destructive action (vanilla, no framework).
 * Replaces state-changing GET links (?delete=, ?cancel=, ...) to avoid CSRF token
 * in URL / logs / Referer. $confirm adds data-confirm modal support (core.js).
 */
function post_action_form(string $actionUrl, string $field, string $value, string $labelHtml, string $btnClass = 'btn btn-outline btn-sm', string $confirm = '', string $ariaLabel = '', array $extra = [], string $confirmTitle = ''): string
{
    $h = '<form method="post" action="' . e($actionUrl) . '" class="d-inline">';
    $h .= csrf_field();
    $h .= '<input type="hidden" name="' . e($field) . '" value="' . e($value) . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . e((string)$k) . '" value="' . e((string)$v) . '">';
    }
    $h .= '<button type="submit" class="' . e($btnClass) . '"';
    if ($confirm !== '') { $h .= ' data-confirm="' . e($confirm) . '"'; }
    if ($confirmTitle !== '') { $h .= ' data-confirm-title="' . e($confirmTitle) . '"'; }
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
