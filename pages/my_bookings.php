<?php
require_once __DIR__ . '/../config/db.php';

require_player();

if (isset($_GET['export'])) {
    $stmt = $conn->prepare(
        'SELECT b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
                b.payment_status, b.amount_paid, b.discount, b.promo_code, b.repeat_weeks, b.payment_method,
                g.name AS ground_name, g.location
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.user_id = ?
         ORDER BY b.booking_date ASC, b.start_time ASC'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $csv = [['Reference', 'Ground', 'Location', 'Date', 'Start', 'End', 'Total (Rs)', 'Status', 'Payment', 'Method', 'Paid (Rs)', 'Discount (Rs)', 'Promo', 'Repeat Weeks']];
    foreach ($rows as $b) {
        $csv[] = [
            $b['booking_ref'],
            $b['ground_name'],
            $b['location'],
            $b['booking_date'],
            substr($b['start_time'], 0, 5),
            substr($b['end_time'], 0, 5),
            number_format((float)$b['total_price'], 2),
            $b['status'],
            $b['payment_status'],
            $b['payment_method'] ?? '',
            number_format((float)$b['amount_paid'], 2),
            number_format((float)$b['discount'], 2),
            $b['promo_code'] ?? '',
            (int)$b['repeat_weeks'],
        ];
    }
    export_csv($csv, 'my-bookings.csv');
}

if (isset($_GET['cancel'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $booking_id = (int)$_GET['cancel'];
    $stmt = $conn->prepare(
        'SELECT b.ground_id, b.booking_ref, b.booking_date, b.start_time, b.amount_paid, b.repeat_of, b.repeat_weeks, g.name AS ground_name
         FROM bookings b JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND b.user_id = ? AND b.status = "confirmed"'
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
                'If the slot has passed, it\'s already done - nothing more to do.',
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
                    $cancelEmail = $conn->prepare('SELECT email FROM users WHERE id = ?');
                    $cancelEmail->bind_param('i', $_SESSION['user_id']);
                    $cancelEmail->execute();
                    $cancelRow = $cancelEmail->get_result()->fetch_assoc();
                    if ($cancelRow) {
                        send_booking_email(
                            $cancelRow['email'],
                            'Your weekly series at GoalSpace was cancelled',
                            'Your weekly booking series was cancelled',
                            [
                                'Court' => $target['ground_name'] ?? 'the court',
                                'First date' => date('D, M j, Y', strtotime($target['booking_date'])),
                                'Time' => substr($target['start_time'], 0, 5),
                                'Cancelled' => $stmt->affected_rows . ' bookings',
                            ]
                        );
                    }
                    set_flash('success', 'Your whole weekly series (' . $stmt->affected_rows . ' bookings) was cancelled.');
                    redirect('pages/my_bookings.php');
                }
            }
            $stmt = $conn->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ? AND user_id = ? AND status = "confirmed"');
            $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                notify_waitlist_freed((int)$target['ground_id'], $target['booking_date'], $target['start_time']);
                notify_user((int)$_SESSION['user_id'], 'Booking cancelled', 'Your booking was cancelled.', 'fa-circle-xmark', 'pages/booking_details.php?id=' . $booking_id);
                $cancelEmail = $conn->prepare('SELECT email FROM users WHERE id = ?');
                $cancelEmail->bind_param('i', $_SESSION['user_id']);
                $cancelEmail->execute();
                $cancelRow = $cancelEmail->get_result()->fetch_assoc();
                if ($cancelRow) {
                    send_booking_email(
                        $cancelRow['email'],
                        'Your booking was cancelled',
                        'Your booking at the court was cancelled',
                        [
                            'Booking ref' => $target['booking_ref'] ?? $booking_id,
                            'Court' => $target['ground_name'] ?? 'the court',
                            'Date' => date('D, M j, Y', strtotime($target['booking_date'])),
                            'Time' => substr($target['start_time'], 0, 5) . ' onwards',
                        ],
                        $policy['refund'] > 0
                            ? 'A refund of Rs ' . number_format($policy['refund'], 0) . ' is on its way back to your account.'
                            : 'The freed slot will show up as available again.'
                    );
                }
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
            b.payment_status, b.amount_paid, b.discount, b.promo_code, b.repeat_weeks, b.payment_method,
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

$page_title = 'My Bookings';
$page_description = 'View your upcoming and past futsal court bookings on GoalSpace, check payment status, and manage your schedule.';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head bookings-head reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h2>My Bookings</h2>
    </div>
    <div class="actions">
        <a href="<?php echo base_url('pages/my_bookings.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
</div>
<div class="bookings-stats reveal">
        <div class="bstat">
            <i class="fa-solid fa-calendar-day"></i>
            <span class="bstat-in"><strong><?php echo count($upcoming); ?></strong><span class="bstat-lbl">Upcoming</span></span>
        </div>
        <div class="bstat">
            <i class="fa-solid fa-hourglass-half"></i>
            <span class="bstat-in"><strong><?php echo count(array_filter($bookings, fn($b) => $b['payment_status'] === 'unpaid' && $b['status'] !== 'cancelled')); ?></strong><span class="bstat-lbl">Unpaid</span></span>
        </div>
        <div class="bstat">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span class="bstat-in"><strong><?php echo count($past); ?></strong><span class="bstat-lbl">Past</span></span>
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
        <div class="section-head reveal push tight">
            <h2 class="section-title sm">Upcoming</h2>
        </div>
        <div class="mbookings reveal">
            <?php foreach ($upcoming as $b) { booking_card($b); } ?>
        </div>
    <?php endif; ?>

    <?php if ($past): ?>
        <div class="section-head reveal gap tight">
            <h2 class="section-title sm">Past &amp; cancelled</h2>
        </div>
        <div class="mbookings reveal">
            <?php foreach ($past as $b) { booking_card($b); } ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

