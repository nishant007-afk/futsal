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
    $taken[] = substr($row['start_time'], 0, 2);
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
    if ($new_date < date('Y-m-d')) {
        $errors[] = [
            'what' => 'That date is in the past.',
            'why' => 'Slots can only be moved to today or a future date.',
            'how' => 'Choose today or a later date.',
        ];
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
                'how' => 'Pick a different time — there are usually plenty of open slots.',
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
    $new_price = ground_price_for_date($booking['ground_id'], (float)$booking['price_per_hour'], $new_date);
    $stmt = $conn->prepare(
        'UPDATE bookings SET booking_date = ?, start_time = ?, end_time = ?, total_price = ?
         WHERE id = ? AND user_id = ? AND status = "confirmed"'
    );
    $stmt->bind_param('sssdii', $new_date, $start_time, $end_time, $new_price, $booking_id, $_SESSION['user_id']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $conn->commit();
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

<div class="page-head">
    <h2><i class="fa-solid fa-arrows-rotate"></i> Reschedule</h2>
    <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> My bookings</a>
</div>
<p class="muted" style="margin-bottom:8px;">Pick a new day and hour for <strong><?php echo e($booking['ground_name']); ?></strong>. Your payment is carried over.</p>

<div class="ground-detail">
    <div class="detail-box booking-panel reveal">
        <h1><?php echo e($booking['ground_name']); ?></h1>
        <p class="price-line"><strong>Rs <?php echo number_format((float)$booking['total_price'], 0); ?></strong> total</p>
        <p class="muted" style="font-size:13px;margin-bottom:16px;">
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
                <?php $isTaken = in_array(substr($slot['start'], 0, 2), $taken, true); ?>
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
