<?php
/**
 * Auto-cancel unpaid bookings older than configured timeout.
 * Run via cron every 15 minutes.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// CLI/cron only – never via web.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: cron/CLI only.');
}

$timeoutMinutes = (int)(setting('unpaid_cancel_timeout_minutes') ?? '60');

$stmt = $conn->prepare(
    "UPDATE bookings
     SET status = 'cancelled'
     WHERE status = 'confirmed'
       AND payment_status = 'unpaid'
       AND TIMESTAMP(booking_date, start_time) < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
);
$stmt->bind_param('i', $timeoutMinutes);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo "Cancelled $affected unpaid booking(s) older than $timeoutMinutes minutes.\n";
} else {
    echo "No stale unpaid bookings to cancel.\n";
}