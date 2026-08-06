<?php
require_once __DIR__ . '/../config/db.php';

require_player();

if (isset($_GET['cancel'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $booking_id = (int)$_GET['cancel'];
    $stmt = $conn->prepare(
        'SELECT ground_id, booking_date, start_time, amount_paid, repeat_of, repeat_weeks FROM bookings WHERE id = ? AND user_id = ? AND status = "confirmed"'
    );
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    $cancel_series = !empty($_GET['cancel_series']);

    if (!$target) {
        set_flash_error(
            'This booking could not be cancelled.',
            'It may already be cancelled, or the link may be out of date.',
            'Refresh My Bookings to see the current status.',
            'pages/my_bookings.php'
        );
    } else {
        $policy = booking_refund_policy($target['booking_date'], $target['start_time'], (float)$target['amount_paid']);
        if (!$policy['allowed']) {
            set_flash_error(
                'This booking can\'t be cancelled right now.',
                $policy['label'],
                'If the slot has passed, it\'s already done — nothing more to do.',
                'pages/my_bookings.php'
            );
        } else {
            if ($cancel_series && ((int)$target['repeat_weeks'] > 1 || (int)$target['repeat_of'] > 0)) {
                $rootId = (int)$target['repeat_of'] > 0 ? (int)$target['repeat_of'] : $booking_id;
                $stmt = $conn->prepare('UPDATE bookings SET status = "cancelled" WHERE user_id = ? AND status = "confirmed" AND (id = ? OR repeat_of = ?)');
                $stmt->bind_param('iii', $_SESSION['user_id'], $rootId, $rootId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    notify_waitlist_freed((int)$target['ground_id'], $target['booking_date'], $target['start_time']);
                    notify_user((int)$_SESSION['user_id'], 'Weekly series cancelled', 'All ' . $stmt->affected_rows . ' bookings in your series were cancelled.', 'fa-circle-xmark', 'pages/booking_details.php?id=' . $rootId);
                    set_flash('success', 'Your whole weekly series (' . $stmt->affected_rows . ' bookings) was cancelled.');
                    redirect('pages/my_bookings.php');
                }
            }
            $stmt = $conn->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ? AND user_id = ? AND status = "confirmed"');
            $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                notify_waitlist_freed((int)$target['ground_id'], $target['booking_date'], $target['start_time']);
                notify_user((int)$_SESSION['user_id'], 'Booking cancelled', 'Your booking was cancelled.', 'fa-circle-xmark', 'pages/booking_details.php?id=' . $booking_id);
                if ($policy['refund'] > 0) {
                    set_flash('success', 'Booking cancelled. A refund of Rs ' . number_format($policy['refund'], 0) . ' will be returned to you.');
                } else {
                    set_flash('success', 'Booking cancelled.');
                }
            } else {
                set_flash_error(
                    'This booking could not be cancelled.',
                    'It was probably already cancelled, so there was nothing left to cancel.',
                    'Check My Bookings to see your current slots.',
                    'pages/my_bookings.php'
                );
            }
        }
    }
    redirect('pages/my_bookings.php');
}

$today = date('Y-m-d');

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.discount, b.promo_code, b.repeat_weeks,
            g.name AS ground_name, g.location
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE b.user_id = ?
     ORDER BY b.booking_date ASC, b.start_time ASC'
);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$upcoming = array_values(array_filter($bookings, function ($b) use ($today) {
    return $b['booking_date'] >= $today && $b['status'] !== 'cancelled';
}));
$past = array_values(array_filter($bookings, function ($b) use ($today) {
    return !($b['booking_date'] >= $today && $b['status'] !== 'cancelled');
}));

function booking_card($b): void
{
    ?>
    <div class="mbooking">
        <div class="mbooking-date">
            <span class="bd-month"><?php echo e(strtoupper(date('M', strtotime($b['booking_date'])))); ?></span>
            <span class="bd-day"><?php echo (int)date('d', strtotime($b['booking_date'])); ?></span>
            <span class="bd-year"><?php echo e(date('Y', strtotime($b['booking_date']))); ?></span>
        </div>
        <div class="mbooking-main">
            <div class="mbooking-head">
                <div class="mbooking-head-row">
                    <h3><?php echo e($b['ground_name']); ?></h3>
                    <span class="badge badge-<?php echo e($b['status']); ?>">
                        <i class="fa-solid fa-<?php echo $b['status'] === 'confirmed' ? 'circle-check' : 'circle-xmark'; ?>"></i>
                        <?php echo ucfirst(e($b['status'])); ?>
                    </span>
                </div>
                <span class="mbooking-time"><i class="fa-regular fa-clock"></i> <?php echo e(substr($b['start_time'], 0, 5)); ?> - <?php echo e(substr($b['end_time'], 0, 5)); ?></span>
            </div>
            <div class="mbooking-meta">
                <span><i class="fa-solid fa-location-dot"></i> <?php echo e($b['location']); ?></span>
                <span class="mprice"><i class="fa-solid fa-tag"></i> Rs <?php echo number_format((float)$b['total_price'], 0); ?></span>
                <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="mbooking-link">View details <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <?php if ($b['status'] === 'confirmed' && $b['payment_status'] !== 'paid'): ?>
                <?php $balance = (float)$b['total_price'] - (float)$b['amount_paid']; ?>
                <div class="paybar <?php echo $b['payment_status'] === 'partial' ? 'partial' : 'unpaid'; ?>">
                    <span class="paybar-info">
                        <i class="fa-solid fa-<?php echo $b['payment_status'] === 'partial' ? 'hourglass-half' : 'circle-exclamation'; ?>"></i>
                        <span>
                            <?php if ($b['payment_status'] === 'partial'): ?>
                                Advance already paid &middot; <strong>Rs <?php echo number_format($balance, 0); ?></strong> left to pay
                            <?php else: ?>
                                <strong>Rs <?php echo number_format($balance, 0); ?></strong> due &middot; pay to lock in your slot
                            <?php endif; ?>
                        </span>
                    </span>
                    <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$b['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-wallet"></i> Pay Rs <?php echo number_format($balance, 0); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>

<?php
$page_title = 'My Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="bookings-hero reveal">
    <div>
        <span class="eyebrow">My schedule</span>
        <h1>My Bookings</h1>
        <p>See what's coming up and what you've already played.</p>
    </div>
    <div class="bookings-stats">
        <div class="bstat"><strong><?php echo count($upcoming); ?></strong><span>Upcoming</span></div>
        <div class="bstat"><strong><?php echo count(array_filter($bookings, fn($b) => $b['payment_status'] === 'unpaid' && $b['status'] !== 'cancelled')); ?></strong><span>Unpaid</span></div>
        <div class="bstat"><strong><?php echo count($past); ?></strong><span>Past</span></div>
    </div>
</div>

<?php if (!$bookings): ?>
    <div class="empty reveal">
        <span class="big"><i class="fa-regular fa-calendar-xmark"></i></span>
        <h3>Nothing booked yet</h3>
        <p>When you reserve a court, your games will show up here.</p>
        <a href="<?php echo base_url('index.php#grounds'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass-location"></i> Find a ground &amp; grab a slot</a>
    </div>
<?php else: ?>
    <?php if ($upcoming): ?>
        <div class="section-head reveal" style="margin-top:6px;">
            <h2 class="section-title" style="font-size:20px;margin-top:24px;">Upcoming</h2>
        </div>
        <div class="mbookings reveal">
            <?php foreach ($upcoming as $b) { booking_card($b); } ?>
        </div>
    <?php endif; ?>

    <?php if ($past): ?>
        <div class="section-head reveal" style="margin-top:26px;">
            <span class="eyebrow">Already played</span>
            <h2 class="section-title" style="font-size:20px;">Past &amp; cancelled</h2>
        </div>
        <div class="mbookings reveal">
            <?php foreach ($past as $b) { booking_card($b); } ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

