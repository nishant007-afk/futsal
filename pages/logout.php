<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request. Please use the logout button.');
}
verify_csrf();

// Clear authenticated session state
$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

$__isHttpsLogout = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || env('APP_SCHEME') === 'https';
setcookie(session_name(), '', time() - 3600, '/', '', $__isHttpsLogout, true);

// Start a fresh, clean guest session to hold the logout confirmation toast
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $__isHttpsLogout,
        'path'     => '/',
    ]);
    session_start();
}
session_regenerate_id(true);

set_flash('success', 'You have been logged out successfully.');

redirect('index.php?logged_out=1');