<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.booking_ref, b.booking_date, b.start_time, b.end_time,
            g.name AS ground_name, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    http_response_code(404);
    exit('Booking not found.');
}

$start = new DateTime($b['booking_date'] . ' ' . $b['start_time']);
$end = new DateTime($b['booking_date'] . ' ' . $b['end_time']);
$uid = $b['booking_ref'] . '@goalspace.com';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="goalspace-booking-' . $b['booking_ref'] . '.ics"');
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//GoalSpace//Booking//EN\r\n";
echo "BEGIN:VEVENT\r\n";
echo "UID:" . $uid . "\r\n";
echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
echo "DTSTART:" . $start->format('Ymd\THis') . "\r\n";
echo "DTEND:" . $end->format('Ymd\THis') . "\r\n";
echo "SUMMARY:Futsal at " . $b['ground_name'] . "\r\n";
echo "LOCATION:" . $b['location'] . "\r\n";
echo "DESCRIPTION:Booking reference " . $b['booking_ref'] . " on GoalSpace\r\n";
echo "END:VEVENT\r\n";
echo "END:VCALENDAR\r\n";
exit;
