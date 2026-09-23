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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    verify_csrf();
    $booking_id = (int)$_POST['cancel_booking'];
    $stmt = $conn->prepare(
        'SELECT b.ground_id, b.booking_ref, b.booking_date, b.start_time, b.amount_paid, b.repeat_of, b.repeat_weeks, g.name AS ground_name
         FROM bookings b JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND b.user_id = ? AND b.status = "confirmed"'
    );
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    $cancel_series = !empty($_POST['cancel_series']);

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
                // Only future bookings in the series – never touch past games.
                $stmt = $conn->prepare('UPDATE bookings SET status = "cancelled" WHERE user_id = ? AND status = "confirmed" AND (id = ? OR repeat_of = ?) AND TIMESTAMP(booking_date, start_time) > NOW()');
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
                            ? 'If eligible, a refund of Rs ' . number_format($policy['refund'], 0) . ' will be returned to your original payment method after the court confirms the cancellation. We hope to see you on another court soon!'
                            : 'The freed slot will show up as available again. We hope to see you on another court soon!',
                        $cancelRow['name'] ?? ''
                    );
                }
                if ($policy['refund'] > 0) {
                    set_flash('success', 'Booking cancelled. Refund of Rs ' . number_format($policy['refund'], 0) . ' will be processed after confirmation.');
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
$unpaid = array_values(array_filter($bookings, function ($b) {
    return in_array($b['payment_status'], ['unpaid', 'partial'], true) && $b['status'] !== 'cancelled';
}));
// Past = date passed or cancelled (cancelled future slots still show under Past).
$past = array_values(array_filter($bookings, function ($b) use ($today) {
    return $b['booking_date'] < $today || $b['status'] === 'cancelled';
}));
// Most recent first for past/cancelled rows.
usort($past, fn($a, $b) => strcmp($b['booking_date'], $a['booking_date']) ?: strcmp($b['start_time'], $a['start_time']));

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

<div class="page-head bookings-head reveal">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1 class="page-title">My Bookings</h1>
    </div>
    <div class="actions">
        <a href="<?php echo base_url('pages/my_bookings.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    </div>
</div>

<?php
$showRows = $bookings;
if ($view === 'unpaid') {
    $showRows = $unpaid;
} elseif ($view === 'upcoming') {
    $showRows = $upcoming;
} elseif ($view === 'past') {
    $showRows = $past;
}
// All view: upcoming soonest-first, then past most-recent-first.
if ($view === 'all') {
    $showRows = array_merge($upcoming, $past);
}
?>

<?php if (!$bookings): ?>
    <?php empty_state('fa-regular fa-calendar-xmark', 'Nothing booked yet', '', grounds_list_url(), 'Browse courts', 'btn btn-primary btn-sm'); ?>
<?php else: ?>
    <div class="view-tabs reveal">
        <a href="<?php echo mb_view_url('all'); ?>" class="view-tab <?php echo $view === 'all' ? 'active' : ''; ?>">All (<?php echo count($bookings); ?>)</a>
        <a href="<?php echo mb_view_url('upcoming'); ?>" class="view-tab <?php echo $view === 'upcoming' ? 'active' : ''; ?>">Upcoming (<?php echo count($upcoming); ?>)</a>
        <a href="<?php echo mb_view_url('unpaid'); ?>" class="view-tab <?php echo $view === 'unpaid' ? 'active' : ''; ?>">Unpaid (<?php echo count($unpaid); ?>)</a>
        <a href="<?php echo mb_view_url('past'); ?>" class="view-tab <?php echo $view === 'past' ? 'active' : ''; ?>">Past &amp; cancelled (<?php echo count($past); ?>)</a>
    </div>

    <?php if (!$showRows): ?>
        <?php if ($view === 'unpaid'): ?>
            <?php empty_state('fa-solid fa-circle-check', "You're all paid up", '', base_url('pages/my_bookings.php'), 'View all bookings'); ?>
        <?php elseif ($view === 'upcoming'): ?>
            <?php empty_state('fa-regular fa-calendar-xmark', 'Nothing upcoming', '', grounds_list_url(), 'Browse courts', 'btn btn-primary btn-sm'); ?>
        <?php else: ?>
            <?php empty_state('fa-regular fa-calendar-xmark', 'No past bookings yet', '', grounds_list_url(), 'Browse courts', 'btn btn-primary btn-sm'); ?>
        <?php endif; ?>
    <?php else: ?>
        <div class="table-wrap reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Court</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th class="num">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($showRows as $b): ?>
                        <?php
                        $needsPayment = $b['status'] === 'confirmed' && $b['payment_status'] !== 'paid';
                        $netDue = max(0, (float)$b['total_price'] - (float)($b['discount'] ?? 0));
                        if ($b['status'] === 'cancelled') {
                            $statusText = 'Cancelled';
                            $statusIcon = 'fa-circle-xmark';
                            $statusClass = 'status-cancelled';
                        } elseif ($b['payment_status'] === 'paid') {
                            $statusText = 'Confirmed · Paid';
                            $statusIcon = 'fa-circle-check';
                            $statusClass = 'status-confirmed';
                        } elseif ($b['payment_status'] === 'partial') {
                            $statusText = 'Partially paid';
                            $statusIcon = 'fa-circle-half-stroke';
                            $statusClass = 'status-pending';
                        } elseif ($needsPayment || $b['status'] === 'pending') {
                            $statusText = 'Payment pending';
                            $statusIcon = 'fa-clock';
                            $statusClass = 'status-pending';
                        } else {
                            $statusText = 'Confirmed';
                            $statusIcon = 'fa-circle-check';
                            $statusClass = 'status-confirmed';
                        }
                        ?>
                        <tr>
                            <td data-label="Court">
                                <div class="mbt-court">
                                    <strong><?php echo e($b['ground_name']); ?></strong>
                                    <span class="muted"><i class="fa-solid fa-location-dot"></i> <?php echo e($b['location']); ?></span>
                                </div>
                            </td>
                            <td data-label="Date">
                                <?php echo e(date('D, M j', strtotime($b['booking_date']))); ?><br>
                                <span class="muted" style="font-size:12px;"><?php echo e(date('Y', strtotime($b['booking_date']))); ?></span>
                            </td>
                            <td class="mbt-time" data-label="Time"><?php echo e(substr($b['start_time'], 0, 5)); ?> &ndash; <?php echo e(substr($b['end_time'], 0, 5)); ?></td>
                            <td data-label="Status"><span class="status-badge <?php echo $statusClass; ?>"><i class="fa-solid <?php echo $statusIcon; ?>"></i> <?php echo e($statusText); ?></span></td>
                            <td class="num strong" data-label="Total">
                                Rs <?php echo number_format($netDue, 0); ?>
                                <?php if ((float)$b['discount'] > 0): ?>
                                    <br><span class="muted" style="font-size:12px;text-decoration:line-through;">Rs <?php echo number_format((float)$b['total_price'], 0); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="mbt-actions" data-label="">
                                <div class="mbt-actions-row">
                                    <?php if ($needsPayment && $b['status'] !== 'cancelled'): ?>
                                        <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$b['id']); ?>" class="mb-cta mb-cta-pay"><i class="fa-solid fa-wallet"></i> Pay</a>
                                    <?php endif; ?>
                                    <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="btn btn-outline btn-sm" title="View details"><i class="fa-solid fa-chevron-right"></i><span class="sr-only">Details</span></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

