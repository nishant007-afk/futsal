<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

require_player();

verify_csrf();

$ground_id = (int)($_POST['ground_id'] ?? 0);
$booking_date = $_POST['booking_date'] ?? '';
$slot = $_POST['selected_slot'] ?? '';

$parts = explode('|', $slot);
$start_time = $parts[0] ?? '';
$end_time = $parts[1] ?? '';

$errors = [];

if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
    $errors[] = 'Please select a valid time slot.';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
    $errors[] = [
        'what' => 'That date doesn\'t look valid.',
        'why' => 'We need a real calendar date to check the court\'s schedule.',
        'how' => 'Pick a date from the calendar and try again.',
    ];
}
if ($booking_date < date('Y-m-d')) {
    $errors[] = [
        'what' => 'That date is in the past.',
        'why' => 'Slots can only be booked for today or a future date.',
        'how' => 'Choose today or a later date.',
    ];
}

if (isset($_POST['join_waitlist']) && !$errors) {
    if (!slot_is_taken($ground_id, $booking_date, $start_time)) {
        set_flash('info', 'That slot is free now. You can book it right away.');
        redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
    }
    $stmt = $conn->prepare('INSERT INTO waitlist (ground_id, booking_date, start_time, user_id) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $_SESSION['user_id']);
    if ($stmt->execute()) {
        set_flash('success', 'You\'re on the waitlist. We\'ll ping you the moment this slot frees up.');
    } else {
        set_flash('info', 'You\'re already on the waitlist for this slot.');
    }
    redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
}

$stmt = $conn->prepare('SELECT id, name, price_per_hour, manager_id FROM grounds WHERE id = ? AND is_active = 1');
$stmt->bind_param('i', $ground_id);
$stmt->execute();
$ground = $stmt->get_result()->fetch_assoc();

if (!$ground) {
    $errors[] = [
        'what' => 'We couldn\'t find that court.',
        'why' => 'The court may have been removed or is no longer taking bookings.',
        'how' => 'Browse the courts list to pick one that\'s available.',
        'how_url' => 'pages/courts.php',
    ];
}

if (!$errors && date_is_blocked($ground_id, $booking_date)) {
    $errors[] = [
        'what' => 'This court is closed on that day.',
        'why' => 'The manager has blocked this date.',
        'how' => 'Pick another date to see open slots.',
    ];
}

if (!$errors) {
    $stmt = $conn->prepare(
        'SELECT id FROM bookings
         WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND status != "cancelled"'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = [
            'what' => 'That time slot was just taken.',
            'why' => 'Someone else booked it while you were choosing.',
            'how' => 'Pick a different time - there are usually plenty of open slots.',
        ];
    }
}

if ($errors) {
    foreach ($errors as $err) {
        set_flash_error(
            $err['what'],
            $err['why'] ?? null,
            $err['how'] ?? null,
            $err['how_url'] ?? null
        );
    }
    redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
}

$conn->begin_transaction();

$repeat_weeks = (int)($_POST['repeat_weeks'] ?? 1);
if ($repeat_weeks < 1 || $repeat_weeks > 8) {
    $repeat_weeks = 1;
}

$createdCount = 0;
$skipped = [];
$first_id = 0;
$repeat_of = 0;

for ($w = 0; $w < $repeat_weeks; $w++) {
    $week_date = date('Y-m-d', strtotime($booking_date . " +{$w} week"));
    if ($week_date < date('Y-m-d') || date_is_blocked($ground_id, $week_date)) {
        continue;
    }
    if (slot_is_taken($ground_id, $week_date, $start_time)) {
        $skipped[] = date('M j', strtotime($week_date));
        continue;
    }
    $week_price = ground_price_for_date($ground_id, (float)$ground['price_per_hour'], $week_date);
    $booking_ref = 'GS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $stmt = $conn->prepare(
        'INSERT INTO bookings (booking_ref, user_id, ground_id, booking_date, start_time, end_time, total_price, status, repeat_of, repeat_weeks)
         VALUES (?, ?, ?, ?, ?, ?, ?, "confirmed", ?, ?)'
    );
    $stmt->bind_param(
        'siisssdii',
        $booking_ref,
        $_SESSION['user_id'],
        $ground_id,
        $week_date,
        $start_time,
        $end_time,
        $week_price,
        $repeat_of,
        $repeat_weeks
    );
    if (!$stmt->execute()) {
        $conn->rollback();
        set_flash_error(
            'That time slot was just taken.',
            'Someone else booked it while you were paying.',
            'Pick a different time - there are usually plenty of open slots.',
            'pages/ground.php?id=' . $ground_id . '&date=' . $booking_date
        );
        redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
    }
    if ($createdCount === 0) {
        $first_id = (int)$stmt->insert_id;
        $repeat_of = $first_id;
    }
    $createdCount++;
}

if ($createdCount === 0) {
    $conn->rollback();
    set_flash_error(
        'None of those slots could be booked.',
        'Every selected date was either already taken or closed by the court.',
        'Try a different day or a later time.',
        'pages/ground.php?id=' . $ground_id . '&date=' . $booking_date
    );
    redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
}

$conn->commit();
$booking_id = $first_id;

notify_user(
    (int)$_SESSION['user_id'],
    $createdCount > 1 ? $createdCount . ' bookings confirmed' : 'Booking confirmed',
    $ground['name'] . ' on ' . date('D, M j', strtotime($booking_date)) . ' at ' . substr($start_time, 0, 5),
    'fa-calendar-check',
    'pages/booking_details.php?id=' . $booking_id
);
if ((int)$ground['manager_id'] > 0) {
    notify_user(
        (int)$ground['manager_id'],
        $createdCount > 1 ? $createdCount . ' new bookings on your court' : 'New booking on your court',
        $ground['name'] . ' &middot; ' . date('M j', strtotime($booking_date)) . ' at ' . substr($start_time, 0, 5),
        'fa-store',
        'pages/booking_details.php?id=' . $booking_id
    );
}

if ($createdCount > 1) {
    $msg = 'You booked ' . $createdCount . ' weekly slots at ' . $ground['name'] . '.';
    if ($skipped) {
        $msg .= ' Skipped ' . implode(', ', $skipped) . ' (already taken or closed).';
    }
    set_flash('success', $msg);
} else {
    set_flash('success', 'Your slot at ' . $ground['name'] . ' is locked in!');
}
redirect('pages/payment.php?booking_id=' . $booking_id);
