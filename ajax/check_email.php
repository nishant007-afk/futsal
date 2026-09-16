<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

// Rate-limit email oracle: max 10 checks / 10 min per IP (DB-backed, prevents enumeration).
if (rate_limit_exceeded('emailchk', 10, 600)) {
    http_response_code(429);
    echo json_encode(['error' => 'too many requests']);
    exit;
}

$token = $_GET['csrf'] ?? '';
if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid request']);
    exit;
}

$email = trim((string)($_GET['email'] ?? ''));
$mode = $_GET['mode'] ?? 'exists';
if (!in_array($mode, ['exists', 'available'], true)) {
    $mode = 'exists';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false]);
    exit;
}

$exclude = (int)($_GET['exclude'] ?? 0);

$sql = 'SELECT COUNT(*) AS c FROM users WHERE email = ?';
$types = 's';
$params = [$email];
if ($exclude > 0) {
    $sql .= ' AND id <> ?';
    $types .= 'i';
    $params[] = $exclude;
}
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false]);
    exit;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count = (int)$stmt->get_result()->fetch_assoc()['c'];

if ($mode === 'available') {
    echo json_encode(['ok' => $count === 0]);
} else {
    echo json_encode(['ok' => $count > 0]);
}