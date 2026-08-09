<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_player();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Method not allowed');
}

verify_csrf();

$reviewId = (int)($_POST['review_id'] ?? 0);
if (!$reviewId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid review']);
    exit;
}

$stmt = $conn->prepare('SELECT 1 FROM review_votes WHERE review_id = ? AND user_id = ?');
$stmt->bind_param('ii', $reviewId, $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['ok' => false, 'error' => 'Already voted']);
    exit;
}

$conn->begin_transaction();

$stmt = $conn->prepare('INSERT INTO review_votes (review_id, user_id) VALUES (?, ?)');
$stmt->bind_param('ii', $reviewId, $_SESSION['user_id']);
$ok = $stmt->execute();

if ($ok) {
    $stmt = $conn->prepare('UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?');
    $stmt->bind_param('i', $reviewId);
    $stmt->execute();
    $conn->commit();

    $stmt = $conn->prepare('SELECT helpful_count FROM reviews WHERE id = ?');
    $stmt->bind_param('i', $reviewId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['helpful_count'];

    echo json_encode(['ok' => true, 'count' => $count]);
} else {
    $conn->rollback();
    echo json_encode(['ok' => false, 'error' => 'Could not vote']);
}