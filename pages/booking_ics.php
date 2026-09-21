<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_player();

$booking_id = (int)($_GET['id'] ?? ($_GET['booking_id'] ?? 0));
if (!$booking_id) {
    http_error_page(400, 'Missing booking', 'No booking ID provided.');
}

$stmt = $conn->prepare('SELECT b.*, g.name AS ground_name, g.location FROM bookings b JOIN grounds g ON g.id = b.ground_id WHERE b.id = ? AND b.user_id = ?');
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    http_error_page(404, 'Booking not found', 'We couldn\'t find that booking.');
}

$dtStart = new DateTime($b['booking_date'] . ' ' . $b['start_time']);
$dtEnd = new DateTime($b['booking_date'] . ' ' . $b['end_time']);
$uid = 'booking-' . $b['booking_ref'] . '@goalspace.app';
$dtStamp = new DateTime();
$desc = "Booking at {$b['ground_name']} ({$b['location']})\nRef: {$b['booking_ref']}\nPrice: " . format_price((float)$b['total_price']) . "\nStatus: {$b['status']}\nPayment: {$b['payment_status']}";

// Sanitize CR/LF from DB fields so managers cannot inject iCal lines.
$groundName = str_replace(["\r", "\n"], ' ', $b['ground_name']);
$location   = str_replace(["\r", "\n"], ' ', $b['location']);

$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//GoalSpace//Booking Calendar//EN\r\n";
$ics .= "CALSCALE:GREGORIAN\r\n";
$ics .= "METHOD:PUBLISH\r\n";
$ics .= "BEGIN:VEVENT\r\n";
$ics .= "UID:" . $uid . "\r\n";
$ics .= "DTSTAMP:" . $dtStamp->format('Ymd\THis\Z') . "\r\n";
$ics .= "DTSTART:" . $dtStart->format('Ymd\THis') . "\r\n";
$ics .= "DTEND:" . $dtEnd->format('Ymd\THis') . "\r\n";
$ics .= "SUMMARY:GoalSpace - " . $groundName . "\r\n";
$ics .= "DESCRIPTION:" . str_replace(["\r", "\n"], "\\n", $desc) . "\r\n";
$ics .= "LOCATION:" . $groundName . ", " . $location . "\r\n";
$ics .= "STATUS:CONFIRMED\r\n";
$ics .= "END:VEVENT\r\n";
$ics .= "END:VCALENDAR\r\n";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="goalspace-booking-' . $b['booking_ref'] . '.ics"');
echo $ics;