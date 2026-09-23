<?php

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    // Cache per request to avoid N+1 queries
    if (isset($GLOBALS['__current_user'])) {
        return $GLOBALS['__current_user'];
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id, name, email, phone, avatar, role, email_verified, created_at, notify_bookings, notify_promo, notify_expiry, notify_sms FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $GLOBALS['__current_user'] = $result->fetch_assoc();
    return $GLOBALS['__current_user'];
}

function is_admin(): bool
{
    return is_role('admin');
}

function is_manager(): bool
{
    return is_role('manager');
}

function is_player(): bool
{
    return is_role('user');
}

function is_role(string $role): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === $role;
}

function user_owns_ground(int $ground_id): bool
{
    if (is_admin()) {
        return true;
    }
    if (!is_manager()) {
        return false;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM grounds WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function remember_return_path(): void
{
    if (is_logged_in()) {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri === '' || isset($_SESSION['return_path'])) {
        return;
    }
    $path = parse_url($uri, PHP_URL_PATH);
    $query = parse_url($uri, PHP_URL_QUERY);
    if (!is_string($path) || $path === '') {
        return;
    }
    $script = basename($path);
    // Never bounce back to auth entry points (avoids loops).
    if (in_array($script, ['login.php', 'register.php', 'logout.php', 'forgot_password.php', 'otp_verify.php', 'verify.php'], true)) {
        return;
    }
    $rel = $path;
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    if ($base !== '' && str_starts_with($path, $base)) {
        $rel = substr($path, strlen($base)) ?: '/';
    }
    if ($rel === '' || $rel[0] !== '/') {
        $rel = '/' . ltrim($rel, '/');
    }
    $_SESSION['return_path'] = $rel . ($query ? '?' . $query : '');
}

function require_login(): void
{
    if (!is_logged_in()) {
        remember_return_path();
        header('Location: ' . base_url('pages/login.php'));
        exit;
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }

    $configured = trim((string)env('ADMIN_IPS', ''));
    if ($configured !== '') {
        $allowed = array_filter(array_map('trim', explode(',', $configured)));
        $current = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocal = in_array($current, ['127.0.0.1', '::1'], true) && (in_array('127.0.0.1', $allowed, true) || in_array('::1', $allowed, true));
        if (!$isLocal && !in_array($current, $allowed, true)) {
            header('Location: ' . base_url('index.php'));
            exit;
        }
    }
}

function require_manager(): void
{
    if (!is_manager() && !is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }

    $configured = trim((string)env('MANAGER_IPS', ''));
    if ($configured !== '') {
        $allowed = array_filter(array_map('trim', explode(',', $configured)));
        $current = $_SERVER['REMOTE_ADDR'] ?? '';
        $isLocal = in_array($current, ['127.0.0.1', '::1'], true) && (in_array('127.0.0.1', $allowed, true) || in_array('::1', $allowed, true));
        if (!$isLocal && !in_array($current, $allowed, true)) {
            header('Location: ' . base_url('index.php'));
            exit;
        }
    }
}

function require_player(): void
{
    if (!is_logged_in()) {
        remember_return_path();
        header('Location: ' . base_url('pages/login.php'));
        exit;
    }
}
