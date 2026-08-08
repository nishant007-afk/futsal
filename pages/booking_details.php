<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$me = current_user();
$booking_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.user_id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.discount, b.promo_code, b.created_at, b.repeat_weeks,
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
    set_flash_error(
        'We couldn\'t find that booking.',
        'It may have been cancelled, or you may not have access to it.',
        'Open the booking from your bookings list to see its current status.',
        $me['role'] === 'admin' ? 'admin/bookings.php' : ($me['role'] === 'manager' ? 'manager/bookings.php' : 'pages/my_bookings.php')
    );
    if ($me['role'] === 'admin') {
        redirect('admin/bookings.php');
    } elseif ($me['role'] === 'manager') {
        redirect('manager/bookings.php');
    }
    redirect('pages/my_bookings.php');
}

$policy = booking_refund_policy($b['booking_date'], $b['start_time'], (float)$b['amount_paid']);
$netDue = (float)$b['total_price'] - (float)$b['discount'];
$balance = max(0, $netDue - (float)$b['amount_paid']);

$page_title = 'Booking details';
$page_description = 'Review the full details of your futsal booking on GoalSpace — court, date, time, pricing, and payment status.';
require __DIR__ . '/../includes/header.php';
?>

<div class="bd-wrap reveal">
    <nav class="breadcrumb">
        <a href="<?php echo base_url($me['role'] === 'admin' ? 'admin/bookings.php' : ($me['role'] === 'manager' ? 'manager/bookings.php' : 'pages/my_bookings.php')); ?>">Bookings</a> &nbsp;/&nbsp;
        <span><?php echo e($b['booking_ref']); ?></span>
    </nav>

    <div class="bd-head">
        <div>
            <span class="eyebrow">Booking details</span>
            <h1><?php echo e($b['ground_name']); ?></h1>
            <p class="muted"><i class="fa-solid fa-location-dot"></i> <?php echo e($b['location']); ?></p>
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
        </div>
    </div>

    <?php if ($b['status'] === 'confirmed'): ?>
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
            <h2><i class="fa-solid fa-calendar-day"></i> Schedule</h2>
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
            <h2><i class="fa-solid fa-location-dot"></i> Venue location</h2>
            <p class="muted"><?php echo e($b['address']); ?></p>
            <iframe
                width="100%" height="210" style="border:0;border-radius:var(--r-sm)" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://maps.google.com/maps?q=<?php echo rawurlencode($b['address']); ?>&t=&z=16&ie=UTF8&iwloc=B&output=embed">
            </iframe>
        </section>
        <?php endif; ?>

        <?php if ($me['role'] !== 'user'): ?>
            <section class="bd-section">
                <h2><i class="fa-solid fa-user"></i> Customer</h2>
                <dl class="bd-list">
                    <div><dt>Name</dt><dd><?php echo e($b['user_name']); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo e($b['user_email']); ?></dd></div>
                    <?php if ($b['user_phone']): ?><div><dt>Phone</dt><dd><?php echo e($b['user_phone']); ?></dd></div><?php endif; ?>
                </dl>
            </section>
        <?php endif; ?>

        <?php if ($b['status'] !== 'cancelled' && ($balance > 0 || $b['payment_status'] !== 'paid')): ?>
        <section class="bd-section" id="paymentCard">
            <h2><i class="fa-solid fa-receipt"></i> Payment</h2>
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
        <div class="notice bd-notice">
            <i class="fa-solid fa-circle-info"></i>
            <span><?php echo e($policy['label']); ?></span>
        </div>
    <?php endif; ?>

    <div class="bd-actions">
        <?php if ($me['role'] === 'user'): ?>
            <?php if ($b['payment_status'] === 'paid'): ?>
                <a href="<?php echo base_url('pages/receipt.php?booking_id=' . (int)$b['id']); ?>" class="btn btn-outline"><i class="fa-solid fa-file-invoice-dollar"></i> Receipt</a>
            <?php endif; ?>
            <?php if ($b['status'] === 'confirmed'): ?>
                <a href="<?php echo base_url('pages/reschedule.php?booking_id=' . (int)$b['id']); ?>" class="btn btn-outline"><i class="fa-solid fa-arrows-rotate"></i> Reschedule</a>
                <?php if ((int)$b['repeat_weeks'] > 1): ?>
                    <a href="<?php echo base_url('pages/my_bookings.php?cancel=' . (int)$b['id'] . '&cancel_series=1&csrf=' . csrf_token()); ?>" class="btn btn-danger" data-confirm="Cancel this whole weekly series of <?php echo (int)$b['repeat_weeks']; ?>?" data-confirm-ok="Yes, cancel series" data-confirm-cancel="No"><i class="fa-solid fa-calendar-xmark"></i> Cancel series</a>
                <?php else: ?>
                    <a href="<?php echo base_url('pages/my_bookings.php?cancel=' . (int)$b['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-danger" data-confirm="Cancel this booking?" data-confirm-ok="Yes, cancel" data-confirm-cancel="No"><i class="fa-solid fa-xmark"></i> Cancel booking</a>
                <?php endif; ?>
            <?php endif; ?>
        <?php elseif ($me['role'] === 'manager' && $b['status'] !== 'cancelled'): ?>
            <a href="<?php echo base_url('manager/bookings.php?cancel=' . (int)$b['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-danger" data-confirm="Cancel this booking?" data-confirm-ok="Yes, cancel" data-confirm-cancel="No"><i class="fa-solid fa-xmark"></i> Cancel booking</a>
        <?php elseif ($me['role'] === 'admin' && $b['status'] !== 'cancelled'): ?>
            <a href="<?php echo base_url('admin/bookings.php?cancel=' . (int)$b['id'] . '&csrf=' . csrf_token()); ?>" class="btn btn-danger" data-confirm="Cancel this booking?" data-confirm-ok="Yes, cancel" data-confirm-cancel="No"><i class="fa-solid fa-xmark"></i> Cancel booking</a>
        <?php endif; ?>
        <a href="<?php echo base_url($me['role'] === 'admin' ? 'admin/bookings.php' : ($me['role'] === 'manager' ? 'manager/bookings.php' : 'pages/my_bookings.php')); ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back to bookings</a>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
