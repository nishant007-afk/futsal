<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid,
            g.id AS ground_id, g.name AS ground_name, g.price_per_hour, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status'] !== 'confirmed') {
    set_flash_error(
        'We couldn\'t find that booking.',
        'It may have been cancelled, or the link may be out of date.',
        'Open the booking from My Bookings to see its current status.',
        'pages/my_bookings.php'
    );
    redirect('pages/my_bookings.php');
}

$selected_date = $_GET['date'] ?? $booking['booking_date'];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = $booking['booking_date'];
}
$is_blocked = date_is_blocked((int)$booking['ground_id'], $selected_date);

$taken = [];
$stmt = $conn->prepare('SELECT start_time FROM bookings WHERE ground_id = ? AND booking_date = ? AND status != "cancelled"');
$stmt->bind_param('is', $booking['ground_id'], $selected_date);
$stmt->execute();
$rows = $stmt->get_result();
while ($row = $rows->fetch_assoc()) {
    $taken[] = $row['start_time'];
}

$slots = slots_for_day($selected_date, $booking['ground_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $new_date = $_POST['booking_date'] ?? '';
    $slot = $_POST['selected_slot'] ?? '';
    $parts = explode('|', $slot);
    $start_time = $parts[0] ?? '';
    $end_time = $parts[1] ?? '';

    $errors = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date)) {
        $errors[] = [
            'what' => 'That date doesn\'t look valid.',
            'why' => 'We need a real calendar date to check the court\'s schedule.',
            'how' => 'Pick a date from the calendar and try again.',
        ];
    }
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
        $errors[] = [
            'what' => 'No time slot was selected.',
            'why' => 'We need to know which hour you want before rescheduling.',
            'how' => 'Tap an available time slot, then save your changes.',
        ];
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
        if ($end_time <= $start_time) {
            $errors[] = [
                'what' => 'End time must be after start time.',
                'why' => 'The booking end time must be later than the start time.',
                'how' => 'Select a slot where the end time comes after the start time.',
            ];
        }
    }
    if ($new_date < date('Y-m-d')) {
        $errors[] = [
            'what' => 'That date is in the past.',
            'why' => 'Slots can only be moved to today or a future date.',
            'how' => 'Choose today or a later date.',
        ];
    }
    if ($new_date > date('Y-m-d', strtotime('+60 days'))) {
        $errors[] = [
            'what' => 'Bookings open up to 60 days ahead.',
            'why' => 'The schedule does not extend that far.',
            'how' => 'Pick a date within the next 60 days.',
        ];
    }
    if ($new_date === date('Y-m-d') && preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time)) {
        if (strtotime($new_date . ' ' . $start_time) < time() + 1800) {
            $errors[] = [
                'what' => 'That slot already started.',
                'why' => 'Past times today cannot be booked.',
                'how' => 'Pick a later time today.',
            ];
        }
    }
    // Slot must exist in court hours (prevents forged 00:00/23:59).
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
        $validSlots = slots_for_day($new_date, (int)$booking['ground_id']);
        $slotOk = false;
        foreach ($validSlots as $vs) {
            if ($vs['start'] === $start_time && $vs['end'] === $end_time) { $slotOk = true; break; }
        }
        if (!$slotOk) {
            $errors[] = [
                'what' => 'That time is outside opening hours.',
                'why' => 'Each court has fixed open/close times.',
                'how' => 'Choose a highlighted free slot.',
            ];
        }
    }
    if (date_is_blocked((int)$booking['ground_id'], $new_date)) {
        $errors[] = [
            'what' => 'This court is closed on that day.',
            'why' => 'The manager has blocked this date.',
            'how' => 'Pick another date to see open slots.',
        ];
    }
    $currentSlot = substr($booking['start_time'], 0, 2);
    if ($new_date === $booking['booking_date'] && $start_time === $booking['start_time']) {
        $errors[] = [
            'what' => 'That\'s your current slot.',
            'why' => 'Rescheduling needs a different day or time.',
            'how' => 'Pick a different slot, or press Back if you changed your mind.',
        ];
    }

    if (!$errors) {
        $stmt = $conn->prepare(
            'SELECT id FROM bookings
             WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND status != "cancelled"'
        );
        $stmt->bind_param('iss', $booking['ground_id'], $new_date, $start_time);
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
        redirect('pages/reschedule.php?booking_id=' . $booking_id . '&date=' . urlencode($new_date));
    }

    $conn->begin_transaction();
    $old_date = $booking['booking_date'];
    $old_start = $booking['start_time'];
    $old_ground_id = (int)$booking['ground_id'];

    $hourly = ground_price_for_date($booking['ground_id'], (float)$booking['price_per_hour'], $new_date);
    $durHrs = max(0.25, (strtotime($end_time) - strtotime($start_time)) / 3600);
    $new_price = round($hourly * $durHrs, 2);

    // Recalculate payment status if the court price changed (e.g. weekday to weekend)
    $amount_paid = (float)$booking['amount_paid'];
    $payment_status = $booking['payment_status'];
    if ($amount_paid >= $new_price && $new_price > 0) {
        $payment_status = 'paid';
    } elseif ($amount_paid > 0 && $amount_paid < $new_price) {
        $payment_status = 'partial';
    } elseif ($amount_paid <= 0) {
        $payment_status = 'unpaid';
    }

    $stmt = $conn->prepare(
        'UPDATE bookings SET booking_date = ?, start_time = ?, end_time = ?, total_price = ?, payment_status = ?
         WHERE id = ? AND user_id = ? AND status = "confirmed"'
    );
    $stmt->bind_param('sssdsii', $new_date, $start_time, $end_time, $new_price, $payment_status, $booking_id, $_SESSION['user_id']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $conn->commit();
        // Notify players on waitlist that the vacated slot is now open
        notify_waitlist_freed($old_ground_id, $old_date, $old_start);

        $reschedUser = current_user();
        if ($reschedUser) {
            send_booking_email(
                $reschedUser['email'],
                'Your booking has a new time',
                'You are all set: new slot confirmed',
                [
                    'Court' => $booking['ground_name'],
                    'New date' => date('D, M j, Y', strtotime($new_date)),
                    'New time' => substr($start_time, 0, 5) . ' - ' . substr($end_time, 0, 5),
                    'New price' => 'Rs ' . number_format($new_price, 0),
                    'Payment' => $payment_status === 'paid' ? 'Paid in full' : ($payment_status === 'partial' ? 'Partially paid (Rs ' . number_format($amount_paid, 0) . ' paid)' : 'Unpaid'),
                ],
                $payment_status === 'partial'
                    ? 'Your advance carried over. Please settle the remaining balance at payment or when you arrive.'
                    : 'Your payment and schedule carry straight over to the new slot. See you there!',
                $reschedUser['name'] ?? ''
            );
        }
        set_flash('success', 'Your booking was rescheduled. Check the new details below.');
        redirect('pages/my_bookings.php');
    } else {
        $conn->rollback();
        set_flash_error(
            'That time slot was just taken.',
            'Someone else booked it while you were saving your changes.',
            'Pick a different time and try again.',
            'pages/reschedule.php?booking_id=' . $booking_id . '&date=' . urlencode($new_date)
        );
        redirect('pages/reschedule.php?booking_id=' . $booking_id . '&date=' . urlencode($new_date));
    }
}

$page_title = 'Reschedule booking';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head settings-page-head">
    <div class="ps-head-row">
        <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="page-back-arrow" data-back aria-label="Back to my bookings"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>Reschedule</h2>
    </div>
</div>
<p class="muted mb-8">Pick a new day and hour for <strong><?php echo e($booking['ground_name']); ?></strong>. Your payment is carried over.</p>

<div class="ground-detail">
    <div class="detail-box booking-panel reveal">
        <h1><?php echo e($booking['ground_name']); ?></h1>
        <p class="price-line"><strong>Rs <?php echo number_format((float)$booking['total_price'], 0); ?></strong> total</p>
        <p class="muted text-sm mb-16">
            Currently booked for <strong><?php echo e(date('D, M j', strtotime($booking['booking_date']))); ?> at <?php echo e(substr($booking['start_time'], 0, 5)); ?></strong>.
        </p>

        <form method="get" action="">
            <div class="form-group">
                <label for="bookingDate">Pick a new day</label>
                <input type="date" id="bookingDate" name="date" value="<?php echo e($selected_date); ?>" min="<?php echo e(date('Y-m-d')); ?>">
            </div>
        </form>

        <p class="slot-hint" id="priceHint">Choose a free hour on this day</p>
        <?php if ($is_blocked): ?>
            <div class="role-lock">
                <i class="fa-solid fa-ban"></i>
                <span>The court is closed on <strong><?php echo e(date('D, M j', strtotime($selected_date))); ?></strong>. Pick another day.</span>
            </div>
        <?php else: ?>
        <div class="slot-grid" id="slotGrid">
            <?php foreach ($slots as $slot): ?>
                <?php $isTaken = in_array($slot['start'], $taken, true); ?>
                <?php $isCurrent = $selected_date === $booking['booking_date'] && $slot['start'] === $booking['start_time']; ?>
                <button type="button" class="slot <?php echo $isTaken ? 'taken' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>"
                     data-start="<?php echo e($slot['start']); ?>"
                     data-end="<?php echo e($slot['end']); ?>"
                     data-label="<?php echo e($slot['label']); ?>" <?php echo $isTaken ? 'disabled' : ''; ?>>
                    <?php echo e($slot['label']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <form method="post" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="booking_date" value="<?php echo e($selected_date); ?>">
            <input type="hidden" name="selected_slot" id="selectedSlot" value="">
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="bookBtn" disabled><i class="fa-solid fa-arrows-rotate"></i> Confirm new slot</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
