<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid request']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!isset($_POST['id']) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid ground']);
    exit;
}

$saved = favorite_exists($id);
$ok = toggle_favorite($id);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'could not update']);
    exit;
}

echo json_encode(['ok' => true, 'id' => $id, 'saved' => $saved ? 0 : 1]);