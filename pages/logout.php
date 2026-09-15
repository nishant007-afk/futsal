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

$_SESSION = [];
session_destroy();

// Clear the session cookie (mirror the Secure flag used at login)
$__isHttpsLogout = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || env('APP_SCHEME') === 'https';
setcookie(session_name(), '', time() - 60, '/', '', $__isHttpsLogout, true);

redirect('index.php');