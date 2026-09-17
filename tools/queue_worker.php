<?php
/**
 * GoalSpace Email Queue Worker
 * Processes queued background emails asynchronously without blocking web requests.
 *
 * Usage:
 *   php tools/queue_worker.php              # Process pending queue items
 *   php tools/queue_worker.php --limit=100  # Process up to 100 items
 */

require_once __DIR__ . '/../config/db.php';

$limit = 50;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = max(1, min(500, (int)substr($arg, 8)));
    }
}

global $conn;

// Clean up old sent emails older than 30 days
$conn->query("DELETE FROM email_queue WHERE status = 'sent' AND sent_at < NOW() - INTERVAL 30 DAY");

// Fetch pending items
$stmt = $conn->prepare("
    SELECT id, recipient, subject, body_html, attempts, max_attempts
    FROM email_queue
    WHERE status = 'pending' AND attempts < max_attempts
    ORDER BY created_at ASC
    LIMIT ?
");
$stmt->bind_param('i', $limit);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    if (PHP_SAPI === 'cli') {
        echo "Email queue is empty. No pending items.\n";
    }
    exit(0);
}

$processed = 0;
$success = 0;
$failed = 0;

foreach ($items as $item) {
    $id = (int)$item['id'];
    $attempts = (int)$item['attempts'] + 1;

    // Lock item as processing
    $lockStmt = $conn->prepare("UPDATE email_queue SET status = 'processing', attempts = ? WHERE id = ? AND status = 'pending'");
    $lockStmt->bind_param('ii', $attempts, $id);
    $lockStmt->execute();
    if ($lockStmt->affected_rows === 0) {
        continue; // Handled by concurrent worker
    }

    $ok = @send_mail($item['recipient'], $item['subject'], $item['body_html'], true);
    $processed++;

    if ($ok) {
        $success++;
        $doneStmt = $conn->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE id = ?");
        $doneStmt->bind_param('i', $id);
        $doneStmt->execute();
    } else {
        $failed++;
        $newStatus = ($attempts >= (int)$item['max_attempts']) ? 'failed' : 'pending';
        $err = 'SMTP transport failure';
        $failStmt = $conn->prepare("UPDATE email_queue SET status = ?, last_error = ? WHERE id = ?");
        $failStmt->bind_param('ssi', $newStatus, $err, $id);
        $failStmt->execute();
    }
}

if (PHP_SAPI === 'cli') {
    echo "Queue worker processed $processed items ($success sent, $failed failed).\n";
}
