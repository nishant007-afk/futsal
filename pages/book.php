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
    $errors[] = ['what' => 'Please select a time slot.'];
}
if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
    if ($end_time <= $start_time) {
        $errors[] = ['what' => 'End time must be after start time.'];
    }
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
    $errors[] = ['what' => 'Please pick a valid date.'];
}
if ($booking_date < date('Y-m-d')) {
    $errors[] = ['what' => 'Please pick today or a future date.'];
}
// Max 60 days ahead – prevents far-future abuse and keeps schedule sane.
if ($booking_date > date('Y-m-d', strtotime('+60 days'))) {
    $errors[] = ['what' => 'Bookings open up to 60 days ahead. Pick an earlier date.'];
}
// Past time today is not bookable (30-min buffer).
if ($booking_date === date('Y-m-d') && preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time)) {
    if (strtotime($booking_date . ' ' . $start_time) < time() + 1800) {
        $errors[] = ['what' => 'That slot already started. Pick a later time today.'];
    }
}

if (isset($_POST['join_waitlist']) && !$errors) {
    if (!slot_is_taken($ground_id, $booking_date, $start_time)) {
        set_flash('info', 'That slot is free now. You can book it right away.');
        redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
    }
    $stmt = $conn->prepare('INSERT INTO waitlist (ground_id, booking_date, start_time, user_id) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $pos = waitlist_position($ground_id, $booking_date, $start_time, (int)$_SESSION['user_id']);
        $user = current_user();
        $groundName = '';
        if ($ground_id > 0) {
            $gn = $conn->prepare('SELECT name FROM grounds WHERE id = ?');
            $gn->bind_param('i', $ground_id);
            $gn->execute();
            $groundName = $gn->get_result()->fetch_assoc()['name'] ?? 'the court';
        }
        if ($user && $pos !== null) {
            send_booking_email(
                $user['email'],
                'You are in line at ' . $groundName,
                'You are on the waitlist at ' . $groundName,
                [
                    'Ground'  => $groundName,
                    'Date'    => date('D, M j, Y', strtotime($booking_date)),
                    'Time'    => substr($start_time, 0, 5),
                    'Position' => (int)$pos,
                ],
                'The moment this slot frees up, we will let you know right away. In the meantime, plenty more courts are waiting to be explored.',
                $user['name'] ?? ''
            );
        }
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
        'how_url' => 'pages/courts.php',
    ];
}

// Validate slot against court hours/interval (prevents 00:00/23:59 POST forgery).
if (!$errors) {
    $validSlots = slots_for_day($booking_date, $ground_id);
    $slotOk = false;
    foreach ($validSlots as $vs) {
        if ($vs['start'] === $start_time && $vs['end'] === $end_time) { $slotOk = true; break; }
    }
    if (!$slotOk) {
        $errors[] = ['what' => 'That time is outside this court\'s opening hours.'];
    }
}

if (!$errors && date_is_blocked($ground_id, $booking_date)) {
    $errors[] = ['what' => 'This court is closed on that day.'];
}

if (!$errors) {
    if (slot_is_taken($ground_id, $booking_date, $start_time)) {
        $errors[] = ['what' => 'That time slot was just taken.'];
    } elseif (is_slot_held($ground_id, $booking_date, $start_time, (int)$_SESSION['user_id'])) {
        $errors[] = ['what' => 'Another player is currently checking out this slot. Try again in a couple of minutes.'];
    }
}

if ($errors) {
    foreach ($errors as $err) {
        set_flash_error(
            $err['what'],
            null,
            null,
            $err['how_url'] ?? null
        );
    }
    redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
}

$hold = acquire_slot_hold($ground_id, $booking_date, $start_time, (int)$_SESSION['user_id'], 300);
if (!$hold['ok']) {
    set_flash_error($hold['error'] ?? 'That slot is currently held. Please try another one.', null, null, 'pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
    redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
}

$conn->begin_transaction();

$repeat_weeks = isset($_POST['repeat_booking']) ? (int)($_POST['repeat_weeks'] ?? 1) : 1;
if ($repeat_weeks < 1 || $repeat_weeks > 8) {
    $repeat_weeks = 1;
}

$createdCount = 0;
$skipped = [];
$first_id = 0;
$first_ref = '';
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
    $hourly = ground_price_for_date($ground_id, (float)$ground['price_per_hour'], $week_date);
    $durHrs = max(0.25, (strtotime($end_time) - strtotime($start_time)) / 3600);
    $week_price = round($hourly * $durHrs, 2);
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
        // Race on UNIQUE slot: skip this week instead of aborting the whole series.
        if ((int)$stmt->errno === 1062) {
            $skipped[] = date('M j', strtotime($week_date));
            continue;
        }
        $conn->rollback();
        release_slot_hold($ground_id, $booking_date, $start_time, (int)$_SESSION['user_id']);
        set_flash_error(
            'That time slot was just taken.',
            null,
            null,
            'pages/ground.php?id=' . $ground_id . '&date=' . $booking_date
        );
        redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
    }
    if ($createdCount === 0) {
        $first_id = (int)$stmt->insert_id;
        $first_ref = $booking_ref;
        $repeat_of = $first_id;
    }
    $createdCount++;
}

if ($createdCount === 0) {
    $conn->rollback();
    release_slot_hold($ground_id, $booking_date, $start_time, (int)$_SESSION['user_id']);
    set_flash_error(
        'None of those slots could be booked.',
        null,
        null,
        'pages/ground.php?id=' . $ground_id . '&date=' . $booking_date
    );
    redirect('pages/ground.php?id=' . $ground_id . '&date=' . $booking_date);
}

$conn->commit();
release_slot_hold($ground_id, $booking_date, $start_time, (int)$_SESSION['user_id']);
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

$user = current_user();
if ($user) {
    send_booking_email(
        $user['email'],
        $createdCount > 1 ? 'Your weekly bookings are confirmed' : 'Your booking is confirmed',
        $createdCount > 1 ? $createdCount . ' bookings confirmed at ' . $ground['name'] : 'Booking confirmed at ' . $ground['name'],
        [
            'Booking ref' => $first_ref,
            'Court' => $ground['name'],
            'Date' => date('D, M j, Y', strtotime($booking_date)),
            'Time' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
            'Price' => format_price($week_price),
        ],
        $createdCount > 1
            ? 'This is a weekly repeat for the same slot. You can view all upcoming sessions under My Bookings.'
            : 'You can pay online to secure your slot, or settle when you arrive at the court. Enjoy your match!',
        $user['name'] ?? ''
    );
}
if ((int)$ground['manager_id'] > 0) {
    $mgrLookup = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
    $mgrLookup->bind_param('i', $ground['manager_id']);
    $mgrLookup->execute();
    $mgrRow = $mgrLookup->get_result()->fetch_assoc();
    if ($mgrRow) {
        send_booking_email(
            $mgrRow['email'],
            'A new booking came in at ' . $ground['name'],
            $createdCount > 1 ? $createdCount . ' new bookings on your court' : 'You have a new booking',
            [
                'Court' => $ground['name'],
                'Date' => date('D, M j, Y', strtotime($booking_date)),
                'Time' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
            ],
            'Log in to your manager dashboard to view player details and manage the schedule.',
            $mgrRow['name'] ?? ''
        );
    }
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
