<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.discount, b.promo_code, b.created_at,
            g.name AS ground_name, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status'] === 'cancelled') {
    set_flash_error(
        'We couldn\'t find that booking.',
        'It may have been cancelled, or the link may be out of date.',
        'Open the booking from My Bookings to see its current status.',
        'pages/my_bookings.php'
    );
    redirect('pages/my_bookings.php');
}

$date = date('Ymd\THis', strtotime($booking['booking_date'] . ' ' . $booking['start_time']));
$end = date('Ymd\THis', strtotime($booking['booking_date'] . ' ' . $booking['end_time']));
$gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
    . '&text=' . rawurlencode('Futsal at ' . $booking['ground_name'])
    . '&dates=' . $date . '/' . $end
    . '&location=' . rawurlencode($booking['location'])
    . '&details=' . rawurlencode('Booking reference ' . $booking['booking_ref']);

$page_title = 'Booking confirmed';
require __DIR__ . '/../includes/header.php';
?>

<div class="confirm-wrap reveal">
    <div class="confirm-card">
        <div class="confirm-check"><i class="fa-solid fa-circle-check"></i></div>
        <span class="eyebrow">Booking confirmed</span>
        <h1>You're booked in!</h1>
        <p class="muted">Keep this reference handy. Quote it at the court.</p>

        <div class="confirm-ref">
            <span>Booking reference</span>
            <strong><?php echo e($booking['booking_ref']); ?></strong>
        </div>

        <div class="payment-summary">
            <div class="payment-ground">
                <div class="payment-date">
                    <span class="bd-month"><?php echo e(strtoupper(date('M', strtotime($booking['booking_date'])))); ?></span>
                    <span class="bd-day"><?php echo (int)date('d', strtotime($booking['booking_date'])); ?></span>
                </div>
                <div>
                    <h3><?php echo e($booking['ground_name']); ?></h3>
                    <p><i class="fa-solid fa-location-dot"></i> <?php echo e($booking['location']); ?></p>
                    <p><i class="fa-regular fa-clock"></i> <?php echo e(substr($booking['start_time'], 0, 5)); ?> - <?php echo e(substr($booking['end_time'], 0, 5)); ?></p>
                </div>
            </div>
            <div class="payment-total">
                <span>Total</span>
                <strong>Rs <?php echo number_format((float)$booking['total_price'], 0); ?></strong>
                <?php if ((float)$booking['discount'] > 0): ?>
                    <span class="pay-line ok"><i class="fa-solid fa-tag"></i> <?php echo e($booking['promo_code']); ?> &minus; Rs <?php echo number_format((float)$booking['discount'], 0); ?></span>
                <?php endif; ?>
                <?php if ($booking['payment_status'] === 'paid'): ?>
                    <span class="pay-line ok">Fully paid</span>
                <?php elseif ($booking['payment_status'] === 'partial'): ?>
                    <span class="pay-line warn">Rs <?php echo number_format((float)$booking['amount_paid'], 0); ?> paid &middot; rest at court</span>
                <?php else: ?>
                    <span class="pay-line warn">Unpaid &middot; pay online or at court</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="confirm-actions">
            <?php if ($booking['payment_status'] === 'paid'): ?>
                <a href="<?php echo base_url('pages/receipt.php?booking_id=' . (int)$booking['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-file-invoice-dollar"></i> Payment receipt</a>
            <?php elseif ($booking['payment_status'] === 'unpaid'): ?>
                <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$booking['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-wallet"></i> Pay online now</a>
            <?php endif; ?>
            <a href="<?php echo e($gcal); ?>" target="_blank" rel="noopener" class="btn btn-outline"><i class="fa-regular fa-calendar-plus"></i> Add to Google Calendar</a>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-ghost"><i class="fa-solid fa-calendar-check"></i> My bookings</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
