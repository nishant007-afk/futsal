<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$me = current_user();
$booking_id = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare(
        'SELECT b.id, b.user_id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
                b.payment_status, b.payment_type, b.amount_paid, b.payment_method, b.discount, b.promo_code, b.created_at, b.repeat_weeks,
                g.name AS ground_name, g.location, g.address, g.court_number, g.manager_id,
                u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
                m.name AS manager_name
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         JOIN users u ON u.id = b.user_id
         LEFT JOIN users m ON m.id = g.manager_id
         WHERE b.id = ?'
    );
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

$allowed = false;
if ($b) {
    if ($me['role'] === 'admin') {
        $allowed = true;
    } elseif ($me['role'] === 'manager') {
        $allowed = (int)$b['manager_id'] === (int)$me['id'];
    } else {
        $allowed = (int)$b['user_id'] === (int)$me['id'];
    }
}

if (!$b || !$allowed) {
    $back = $me['role'] === 'admin' ? 'admin/bookings.php' : ($me['role'] === 'manager' ? 'manager/bookings.php' : 'pages/my_bookings.php');
    http_error_page(404, 'Booking not found', 'We couldn\'t find that booking. It may have been cancelled, or you may not have access to it.', 'Back to bookings', $back);
}

$policy = booking_refund_policy($b['booking_date'], $b['start_time'], (float)$b['amount_paid']);
$netDue = (float)$b['total_price'] - (float)$b['discount'];
$balance = max(0, $netDue - (float)$b['amount_paid']);

$page_title = 'Booking details';
$page_description = 'Review the full details of your futsal booking on GoalSpace - court, date, time, pricing, and payment status.';
require __DIR__ . '/../includes/header.php';
?>

<div class="bd-wrap reveal">

    <div class="bd-head">
        <div class="title-back-row bd-title-row">
            <a href="<?php echo base_url($me['role'] === 'admin' ? 'admin/bookings.php' : ($me['role'] === 'manager' ? 'manager/bookings.php' : 'pages/my_bookings.php')); ?>" class="page-back-arrow" data-back aria-label="Back to bookings"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <span class="eyebrow">Booking details</span>
                <h1><?php echo e($b['ground_name']); ?></h1>
                <p class="muted"><i class="fa-solid fa-location-dot"></i> <?php echo e($b['location']); ?></p>
            </div>
        </div>
        <div class="bd-head-badges">
            <span class="status-badge status-<?php echo e($b['status']); ?>">
                <i class="fa-solid fa-<?php echo $b['status'] === 'confirmed' ? 'circle-check' : 'circle-xmark'; ?>"></i>
                <?php echo ucfirst(e($b['status'])); ?>
            </span>
            <span class="status-badge status-<?php echo $b['payment_status'] === 'paid' ? 'confirmed' : ($b['payment_status'] === 'partial' ? 'pending' : 'cancelled'); ?>">
                <i class="fa-solid fa-<?php echo $b['payment_status'] === 'paid' ? 'circle-check' : ($b['payment_status'] === 'partial' ? 'coins' : 'clock'); ?>"></i>
                <?php echo ucfirst(e($b['payment_status'])); ?>
            </span>
            <?php if ($b['payment_method'] === 'at_court' && $b['payment_status'] === 'paid'): ?>
                <span class="status-badge status-at-court"><i class="fa-solid fa-coins"></i> Paid at court</span>
            <?php elseif ($b['payment_method'] === 'qr' && $b['payment_status'] === 'paid'): ?>
                <span class="status-badge status-qr"><i class="fa-solid fa-qrcode"></i> Paid via QR</span>
            <?php elseif ($b['payment_method'] === 'qr' && $b['payment_status'] === 'partial'): ?>
                <span class="status-badge status-qr"><i class="fa-solid fa-qrcode"></i> Partially paid via QR</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($b['status'] === 'confirmed' || $b['status'] === 'pending') && (($_GET['paid'] ?? '') === '1')): ?>
        <div class="notice bd-notice notice-success mb-14">
            <i class="fa-solid fa-circle-check"></i>
            <span><strong>Payment details submitted.</strong> The court will confirm verification shortly  -  your slot stays reserved.</span>
        </div>
    <?php endif; ?>

    <?php if ($b['status'] === 'confirmed' || $b['status'] === 'pending'): ?>
        <div class="step-note bd-note <?php echo $b['payment_status'] === 'paid' ? 'success' : ($b['payment_status'] === 'partial' ? '' : 'urgent'); ?>">
            <i class="fa-solid fa-<?php echo $b['payment_status'] === 'paid' ? 'circle-check' : 'lightbulb'; ?>"></i>
            <?php if ($me['role'] === 'user'): ?>
                <?php if ($b['payment_status'] === 'paid'): ?>
                    <span><strong>All set.</strong> Your slot is confirmed and fully paid &middot; nothing left to do.</span>
                <?php elseif ($b['payment_status'] === 'partial'): ?>
                    <span>You've paid <strong>Rs <?php echo number_format((float)$b['amount_paid'], 0); ?></strong> as an advance. Pay <strong>Rs <?php echo number_format($balance, 0); ?></strong> to settle the rest.</span>
                <?php else: ?>
                    <span>Your slot is <strong>reserved but not paid</strong>. <a href="#paymentCard" class="inline-link note-pay">Pay now</a> to lock it in &middot; it takes a minute.</span>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($b['payment_status'] === 'paid'): ?>
                    <span><strong>Paid in full.</strong> Nothing left to collect for this booking.</span>
                <?php elseif ($b['payment_status'] === 'partial'): ?>
                    <span>Player paid <strong>Rs <?php echo number_format((float)$b['amount_paid'], 0); ?></strong> as an advance &middot; <strong>Rs <?php echo number_format($balance, 0); ?></strong> due at the court.</span>
                <?php else: ?>
                    <span><strong>Unpaid.</strong> This slot is only reserved &middot; remind the player to pay before the slot is released.</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="bd-grid">
        <section class="bd-section">
            <h2>Schedule</h2>
            <dl class="bd-list">
                <div><dt>Date</dt><dd><?php echo e(date('D, M j, Y', strtotime($b['booking_date']))); ?></dd></div>
                <div><dt>Time</dt><dd><?php echo e(substr($b['start_time'], 0, 5)); ?> - <?php echo e(substr($b['end_time'], 0, 5)); ?></dd></div>
                <div><dt>Duration</dt><dd><?php echo max(1, (strtotime($b['end_time']) - strtotime($b['start_time'])) / 3600); ?> hour<?php echo max(1, (strtotime($b['end_time']) - strtotime($b['start_time'])) / 3600) > 1 ? 's' : ''; ?></dd></div>
                <?php if ((int)$b['repeat_weeks'] > 1): ?>
                    <div><dt>Weekly series</dt><dd>Every week &times;<?php echo (int)$b['repeat_weeks']; ?></dd></div>
                <?php endif; ?>
                <div><dt>Booked on</dt><dd><?php echo e(date('M j, Y g:i A', strtotime($b['created_at']))); ?></dd></div>
                <?php if (!empty($b['court_number'])): ?><div><dt>Court</dt><dd><?php echo e($b['court_number']); ?></dd></div><?php endif; ?>
            </dl>
        </section>

        <?php if (!empty($b['address'])): ?>
        <section class="bd-section bd-map">
            <h2>Venue location</h2>
            <p class="muted"><?php echo e($b['address']); ?></p>
            <iframe
                width="100%" height="210" class="map-frame" style="border-radius:var(--r-sm)" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://maps.google.com/maps?q=<?php echo rawurlencode($b['address']); ?>&t=&z=16&ie=UTF8&iwloc=B&output=embed">
            </iframe>
        </section>
        <?php endif; ?>

        <?php if ($me['role'] !== 'user'): ?>
            <section class="bd-section">
                <h2>Customer</h2>
                <dl class="bd-list">
                    <div><dt>Name</dt><dd><?php echo e($b['user_name']); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo e($b['user_email']); ?></dd></div>
                    <?php if ($b['user_phone']): ?><div><dt>Phone</dt><dd><?php echo e($b['user_phone']); ?></dd></div><?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>

        <?php if ($b['status'] !== 'cancelled' && ($balance > 0 || $b['payment_status'] !== 'paid')): ?>
        <section class="bd-section" id="paymentCard" <?php if ($b['payment_method'] === 'qr'): ?>data-payment-qr="1"<?php endif; ?>>
            <h2>Payment</h2>
            <div class="bd-price">
                <div class="bd-price-row"><span>Subtotal</span><strong>Rs <?php echo number_format((float)$b['total_price'], 0); ?></strong></div>
                <?php if ((float)$b['discount'] > 0): ?>
                    <div class="bd-price-row discount"><span>Promo <?php echo e($b['promo_code']); ?></span><strong>&minus; Rs <?php echo number_format((float)$b['discount'], 0); ?></strong></div>
                <?php endif; ?>
                <div class="bd-price-row total"><span>Total due</span><strong>Rs <?php echo number_format($netDue, 0); ?></strong></div>
                <?php if ((float)$b['amount_paid'] > 0): ?>
                    <div class="bd-price-row"><span>Paid</span><strong class="ok">Rs <?php echo number_format((float)$b['amount_paid'], 0); ?></strong></div>
                <?php endif; ?>
                <?php if ($b['status'] !== 'cancelled' && $balance > 0): ?>
                    <div class="bd-price-row"><span>Balance at court</span><strong class="warn">Rs <?php echo number_format($balance, 0); ?></strong></div>
                <?php endif; ?>
            </div>
            <?php if ($me['role'] === 'user' && $b['status'] === 'confirmed' && $balance > 0): ?>
                <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$b['id']); ?>" class="btn btn-primary btn-block bd-pay"><i class="fa-solid fa-wallet"></i> Pay Rs <?php echo number_format($balance, 0); ?></a>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    </div>

    <?php if ($b['status'] === 'confirmed'): ?>
        <?php if (!$policy['allowed']): ?>
            <div class="notice bd-notice notice-danger">
                <i class="fa-solid fa-circle-xmark"></i>
                <span><strong>This booking has already started</strong> and can no longer be cancelled online. Contact the court directly if you need help.</span>
            </div>
        <?php elseif ($policy['fee'] > 0): ?>
            <div class="notice bd-notice notice-strong">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>Less than 24 hours to kick-off.</strong> If you cancel, a <strong>50% cancellation fee (Rs <?php echo number_format($policy['fee'], 0); ?>)</strong> applies and you'd be refunded Rs <?php echo number_format($policy['refund'], 0); ?>.</span>
            </div>
        <?php else: ?>
            <div class="notice bd-notice">
                <i class="fa-solid fa-circle-info"></i>
                <span><strong>Free cancellation.</strong> You can cancel this booking any time up to 24 hours before your slot with a full refund.</span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bd-actions">
        <?php if ($me['role'] === 'user'): ?>
            <a href="<?php echo base_url('pages/receipt.php?booking_id=' . (int)$b['id']); ?>" class="btn btn-outline"><i class="fa-solid fa-file-invoice-dollar"></i> Receipt</a>
            <?php if ($b['status'] === 'confirmed' || $b['status'] === 'pending'): ?>
                <a href="<?php echo base_url('pages/reschedule.php?booking_id=' . (int)$b['id']); ?>" class="btn btn-outline"><i class="fa-solid fa-arrows-rotate"></i> Reschedule</a>
                <a href="<?php echo base_url('pages/booking_ics.php?id=' . (int)$b['id']); ?>" class="btn btn-outline"><i class="fa-solid fa-calendar-plus"></i> Add to Calendar</a>
                <?php if ((int)$b['repeat_weeks'] > 1): ?>
                    <?php echo post_action_form(base_url('pages/my_bookings.php'), 'cancel_booking', (string)(int)$b['id'], '<i class="fa-solid fa-calendar-xmark"></i> Cancel series', 'btn btn-danger', 'Cancel this whole weekly series of ' . (int)$b['repeat_weeks'] . '?', 'Cancel series', ['cancel_series' => '1']); ?>
                <?php else: ?>
                    <?php echo post_action_form(base_url('pages/my_bookings.php'), 'cancel_booking', (string)(int)$b['id'], '<i class="fa-solid fa-xmark"></i> Cancel booking', 'btn btn-danger', 'Cancel this booking?', 'Cancel booking'); ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php elseif ($me['role'] === 'manager' && $b['status'] !== 'cancelled'): ?>
            <?php if ($b['payment_status'] !== 'paid'): ?>
                <?php echo post_action_form(base_url('manager/bookings.php'), 'mark_paid', (string)(int)$b['id'], '<i class="fa-solid fa-coins"></i> Mark paid', 'btn btn-outline', 'Mark this booking as paid (verified at court)?', 'Mark paid'); ?>
            <?php endif; ?>
            <?php echo post_action_form(base_url('manager/bookings.php'), 'cancel_booking', (string)(int)$b['id'], '<i class="fa-solid fa-xmark"></i> Cancel booking', 'btn btn-danger', 'Cancel this booking?', 'Cancel booking'); ?>
        <?php elseif ($me['role'] === 'admin' && $b['status'] !== 'cancelled'): ?>
            <?php echo post_action_form(base_url('admin/bookings.php'), 'cancel_booking', (string)(int)$b['id'], '<i class="fa-solid fa-xmark"></i> Cancel booking', 'btn btn-danger', 'Cancel this booking?', 'Cancel booking'); ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
