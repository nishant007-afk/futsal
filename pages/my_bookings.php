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
                    $cancelEmail = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
                    $cancelEmail->bind_param('i', $_SESSION['user_id']);
                    $cancelEmail->execute();
                    $cancelRow = $cancelEmail->get_result()->fetch_assoc();
                    if ($cancelRow) {
                        send_booking_email(
                            $cancelRow['email'],
                            'Your weekly series has been cancelled',
                            'Your weekly booking series was cancelled',
                            [
                                'Court' => $target['ground_name'] ?? 'the court',
                                'First date' => date('D, M j, Y', strtotime($target['booking_date'])),
                                'Time' => substr($target['start_time'], 0, 5),
                                'Cancelled' => $stmt->affected_rows . ' bookings',
                            ],
                            'Sorry to see you go. If you change your mind, the same slots may still be open for you to book again.',
                            $cancelRow['name'] ?? ''
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
                $cancelEmail = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
                $cancelEmail->bind_param('i', $_SESSION['user_id']);
                $cancelEmail->execute();
                $cancelRow = $cancelEmail->get_result()->fetch_assoc();
                if ($cancelRow) {
                    send_booking_email(
                        $cancelRow['email'],
                        'Your booking has been cancelled',
                        'Your booking was cancelled',
                        [
                            'Booking ref' => $target['booking_ref'] ?? $booking_id,
                            'Court' => $target['ground_name'] ?? 'the court',
                            'Date' => date('D, M j, Y', strtotime($target['booking_date'])),
                            'Time' => substr($target['start_time'], 0, 5) . ' onwards',
                        ],
                        $policy['refund'] > 0
                            ? 'A refund of Rs ' . number_format($policy['refund'], 0) . ' is on its way back to you. We hope to see you on another court soon!'
                            : 'The freed slot will show up as available again. We hope to see you on another court soon!',
                        $cancelRow['name'] ?? ''
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
$unpaid = array_values(array_filter($bookings, function ($b) use ($today) {
    return $b['payment_status'] === 'unpaid' && $b['status'] !== 'cancelled';
}));

// View filter: all / upcoming / unpaid / past
$view = $_GET['view'] ?? 'all';
if (!in_array($view, ['all', 'upcoming', 'unpaid', 'past'], true)) {
    $view = 'all';
}
function mb_view_url(string $v): string
{
    return $v === 'all' ? base_url('pages/my_bookings.php') : base_url('pages/my_bookings.php?view=' . $v);
}

$page_title = 'My Bookings';
$page_description = 'View your upcoming and past futsal court bookings on GoalSpace, check payment status, and manage your schedule.';
require __DIR__ . '/../includes/header.php';
?>

<div class="container">
<div class="page-head bookings-head reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
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
    <?php empty_state('fa-regular fa-calendar-xmark', 'Nothing booked yet', '', 'index.php#grounds', 'Find a ground & grab a slot', 'btn btn-primary btn-sm'); ?>
<?php else: ?>
    <div class="view-tabs reveal">
        <a href="<?php echo mb_view_url('all'); ?>" class="view-tab <?php echo $view === 'all' ? 'active' : ''; ?>">All (<?php echo count($bookings); ?>)</a>
        <a href="<?php echo mb_view_url('upcoming'); ?>" class="view-tab <?php echo $view === 'upcoming' ? 'active' : ''; ?>">Upcoming (<?php echo count($upcoming); ?>)</a>
        <a href="<?php echo mb_view_url('unpaid'); ?>" class="view-tab <?php echo $view === 'unpaid' ? 'active' : ''; ?>">Unpaid (<?php echo count($unpaid); ?>)</a>
        <a href="<?php echo mb_view_url('past'); ?>" class="view-tab <?php echo $view === 'past' ? 'active' : ''; ?>">Past &amp; cancelled (<?php echo count($past); ?>)</a>
    </div>

    <?php
    $showUpcoming = ($view === 'all' || $view === 'upcoming') && $upcoming;
    $showUnpaid = $view === 'unpaid' && $unpaid;
    $showPast = ($view === 'all' || $view === 'past') && $past;

    if ($view === 'unpaid' && !$unpaid): ?>
        <?php empty_state('fa-solid fa-circle-check', "You're all paid up", '', 'pages/my_bookings.php', 'View all bookings'); ?>
    <?php elseif ($view === 'upcoming' && !$upcoming): ?>
        <?php empty_state('fa-regular fa-calendar-xmark', 'Nothing upcoming', '', 'index.php#grounds', 'Find a ground & grab a slot', 'btn btn-primary btn-sm'); ?>
    <?php elseif ($view === 'past' && !$past): ?>
        <?php empty_state('fa-regular fa-calendar-xmark', 'No past bookings yet', '', 'index.php#grounds', 'Find a ground & grab a slot', 'btn btn-primary btn-sm'); ?>
    <?php else: ?>
        <?php if ($showUpcoming): ?>
            <div class="section-head reveal push tight">
                <h2 class="section-title sm">Upcoming</h2>
            </div>
            <div class="mbookings reveal">
                <?php foreach ($upcoming as $b) { booking_card($b); } ?>
            </div>
        <?php endif; ?>

        <?php if ($showUnpaid): ?>
            <div class="section-head reveal gap tight">
                <h2 class="section-title sm">Needs payment</h2>
            </div>
            <div class="mbookings reveal">
                <?php foreach ($unpaid as $b) { booking_card($b); } ?>
            </div>
        <?php endif; ?>

        <?php if ($showPast): ?>
            <div class="section-head reveal gap tight">
                <h2 class="section-title sm">Past &amp; cancelled</h2>
            </div>
            <div class="mbookings reveal">
                <?php foreach ($past as $b) { booking_card($b); } ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

