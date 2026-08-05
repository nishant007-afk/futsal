<?php
$remember = !empty($_POST['remember_me']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($remember) {
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
}
session_set_cookie_params([
    'lifetime' => $remember ? 30 * 24 * 60 * 60 : 0,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'path' => '/',
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'futsal_booking');
define('DB_PORT', 3306);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

mysqli_set_charset($conn, 'utf8mb4');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/email_check.php';

release_stale_bookings();
sync_subscription_grounds();
