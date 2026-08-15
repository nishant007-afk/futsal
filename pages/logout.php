<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('index.php');
}

$token = $_GET['csrf'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    exit('Invalid request. Please try again.');
}

$_SESSION = [];
session_destroy();

// Clear the session cookie
setcookie(session_name(), '', time() - 60, '/', '', false, true);

redirect('index.php');