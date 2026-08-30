#!/usr/bin/env php
<?php
/**
 * Send 24-hour booking reminder emails.
 * Run via cron every hour: 0 * * * * /usr/bin/php /path/to/futsal/tools/send_reminders.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$windowStart = date('Y-m-d H:i:s', strtotime('+23 hours'));
$windowEnd   = date('Y-m-d H:i:s', strtotime('+25 hours'));

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price,
            b.ground_id, b.user_id,
            g.name AS ground_name, u.email, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.status = "confirmed"
       AND b.reminder_sent = 0
       AND CONCAT(b.booking_date, " ", b.start_time) BETWEEN ? AND ?'
);
$stmt->bind_param('ss', $windowStart, $windowEnd);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$bookings) {
    echo "No reminders to send.\n";
    exit(0);
}

$sent = 0;
foreach ($bookings as $b) {
    $success = send_booking_email(
        $b['email'],
        'Reminder: your game is tomorrow',
        'See you tomorrow at ' . $b['ground_name'],
        [
            'Booking ref' => $b['booking_ref'],
            'Court'       => $b['ground_name'],
            'Date'        => date('D, M j, Y', strtotime($b['booking_date'])),
            'Time'        => substr($b['start_time'], 0, 5) . ' - ' . substr($b['end_time'], 0, 5),
        ],
        'Everything is set for your game. Arrive a few minutes early, warm up well, and enjoy the match!',
        $b['user_name'] ?? ''
    );
    if ($success) {
        $upd = $conn->prepare('UPDATE bookings SET reminder_sent = 1 WHERE id = ?');
        $upd->bind_param('i', $b['id']);
        $upd->execute();
        $upd->close();
        $sent++;
        echo "Sent reminder for booking {$b['booking_ref']} to {$b['email']}\n";
    } else {
        echo "FAILED to send reminder for booking {$b['booking_ref']}\n";
    }
}

echo "Done. $sent reminder(s) sent.\n";