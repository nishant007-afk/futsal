<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid request']);
    exit;
}

$rlKey = is_logged_in() ? ('u:' . $_SESSION['user_id']) : ('ip:' . ($_SERVER['REMOTE_ADDR'] ?? '0'));
if (rate_limit_exceeded('rsvp:' . $rlKey, 30, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'too many requests']);
    exit;
}

$status = (string)($_POST['status'] ?? '');
if (!in_array($status, ['in', 'out'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid status']);
    exit;
}

// Resolve the booking by ref or numeric id (same contract as the invite page).
$ref = trim((string)($_POST['ref'] ?? ''));
$id = (int)($_POST['id'] ?? 0);
if ($ref === '' && $id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'match not specified']);
    exit;
}

if ($ref !== '') {
    $stmt = $conn->prepare('SELECT b.id, b.status, g.capacity FROM bookings b JOIN grounds g ON g.id = b.ground_id WHERE b.booking_ref = ?');
    $stmt->bind_param('s', $ref);
} else {
    $stmt = $conn->prepare('SELECT b.id, b.status, g.capacity FROM bookings b JOIN grounds g ON g.id = b.ground_id WHERE b.id = ?');
    $stmt->bind_param('i', $id);
}
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'match not found']);
    exit;
}
if ($booking['status'] === 'cancelled') {
    http_response_code(410);
    echo json_encode(['ok' => false, 'error' => 'match cancelled']);
    exit;
}

// Identity: logged-in user, or a client-generated guest key stored in localStorage.
if (is_logged_in()) {
    $identity = 'u:' . (int)$_SESSION['user_id'];
} else {
    $guestKey = (string)($_POST['guest_key'] ?? '');
    if (!preg_match('/^[a-z0-9-]{8,64}$/i', $guestKey)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid guest key']);
        exit;
    }
    $identity = 'g:' . $guestKey;
}

$bookingId = (int)$booking['id'];
$up = $conn->prepare('INSERT INTO match_rsvps (booking_id, identity_key, status) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status)');
$up->bind_param('iss', $bookingId, $identity, $status);
$ok = $up->execute();
$up->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'could not save rsvp']);
    exit;
}

$cnt = $conn->prepare("SELECT COUNT(*) AS c FROM match_rsvps WHERE booking_id = ? AND status = 'in'");
$cnt->bind_param('i', $bookingId);
$cnt->execute();
$confirmed = (int)($cnt->get_result()->fetch_assoc()['c'] ?? 0);
$cnt->close();

$spots = max(1, (int)$booking['capacity']);
echo json_encode(['ok' => true, 'count' => $confirmed, 'spots' => $spots, 'my' => $status]);
