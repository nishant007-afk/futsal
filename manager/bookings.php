<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

if (isset($_GET['cancel'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $booking_id = (int)$_GET['cancel'];
    $stmt = $conn->prepare('SELECT b.user_id, b.ground_id, b.booking_ref, b.booking_date, b.start_time, b.total_price, b.payment_status, b.payment_method FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND g.manager_id = ? AND b.status = "confirmed"');
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $cancelTarget = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare(
        'UPDATE bookings b
         JOIN grounds g ON g.id = b.ground_id
         SET b.status = "cancelled"
         WHERE b.id = ? AND g.manager_id = ? AND b.status != "cancelled"'
    );
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        if ($cancelTarget) {
            notify_user((int)$cancelTarget['user_id'], 'Booking cancelled by the court', 'The court cancelled your booking. Any advance will be refunded.', 'fa-circle-xmark', 'pages/booking_details.php?id=' . $booking_id);
            notify_waitlist_freed((int)$cancelTarget['ground_id'], $cancelTarget['booking_date'], $cancelTarget['start_time']);
        }
        set_flash('success', 'Booking cancelled.');
    } else {
        set_flash_error(
            'You can only cancel bookings on your own grounds.',
            'This booking belongs to a court that isn\'t linked to your account.',
            'Manage the bookings from your own courts instead.',
            'manager/bookings.php'
        );
    }
    redirect('manager/bookings.php');
}

if (isset($_GET['mark_paid'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $booking_id = (int)$_GET['mark_paid'];
    $stmt = $conn->prepare('SELECT b.user_id, b.ground_id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.amount_paid FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND g.manager_id = ? AND b.status = "confirmed" AND b.payment_status IN ("unpaid", "partial")');
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$target) {
        set_flash_error('That booking could not be marked as paid.', 'It may already be fully paid, or it belongs to another court.', 'Return to your bookings list to pick another one.', 'manager/bookings.php');
    } else {
        $update = $conn->prepare('UPDATE bookings SET payment_status = "paid", payment_type = "full", amount_paid = total_price, payment_method = "at_court", paid_at = NOW() WHERE id = ? AND status = "confirmed"');
        $update->bind_param('i', $booking_id);
        if ($update->execute() && $update->affected_rows > 0) {
            notify_user(
                (int)$target['user_id'],
                'Payment confirmed by the court',
                'Your booking ' . $target['booking_ref'] . ' is now marked as paid at court.',
                'fa-sack-dollar',
                'pages/booking_details.php?id=' . $booking_id
            );
            $playerEmail = $conn->prepare('SELECT email FROM users WHERE id = ?');
            $playerEmail->bind_param('i', $target['user_id']);
            $playerEmail->execute();
            $playerRow = $playerEmail->get_result()->fetch_assoc();
            if ($playerRow) {
                send_booking_email(
                    $playerRow['email'],
                    'Payment confirmed by the court',
                    'Your booking is now paid (paid at court)',
                    [
                        'Booking ref' => $target['booking_ref'],
                        'Court' => '',
                        'Date' => date('D, M j, Y', strtotime($target['booking_date'])),
                        'Time' => substr($target['start_time'], 0, 5) . ' - ' . substr($target['end_time'], 0, 5),
                        'Paid' => 'Rs ' . number_format((float)$target['total_price'], 0) . ' (at court)',
                    ]
                );
            }
            notify_user((int)$_SESSION['user_id'], 'Payment confirmed', 'You marked booking ' . $target['booking_ref'] . ' as paid at court.', 'fa-sack-dollar', 'manager/bookings.php');
            set_flash('success', 'Booking marked as paid at court. The player can see it on their receipt.');
        } else {
            set_flash_error('That booking could not be marked as paid.', 'It was probably updated by another manager.', 'Try again from your bookings list.', 'manager/bookings.php');
        }
    }
    redirect('manager/bookings.php');
}

$myGrounds = $conn->query(
    'SELECT id, name FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'] . ' ORDER BY name'
)->fetch_all(MYSQLI_ASSOC);

$f_date_from = trim($_GET['date_from'] ?? '');
$f_date_to = trim($_GET['date_to'] ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_payment = trim($_GET['payment'] ?? '');
$f_ground = (int)($_GET['ground'] ?? 0);
$view = trim($_GET['view'] ?? 'bookings'); // 'bookings' or 'waitlist'

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date_from)) {
    $f_date_from = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date_to)) {
    $f_date_to = '';
}
if (!in_array($f_status, ['', 'confirmed', 'cancelled'], true)) {
    $f_status = '';
}
if (!in_array($f_payment, ['', 'unpaid', 'partial', 'paid'], true)) {
    $f_payment = '';
}
if (!in_array($view, ['bookings', 'waitlist'], true)) {
    $view = 'bookings';
}

$where = ['g.manager_id = ' . (int)$_SESSION['user_id']];
$params = [];
$types = '';
if ($f_date_from !== '') {
    $where[] = 'b.booking_date >= ?';
    $params[] = $f_date_from;
    $types .= 's';
}
if ($f_date_to !== '') {
    $where[] = 'b.booking_date <= ?';
    $params[] = $f_date_to;
    $types .= 's';
}
if ($f_status !== '') {
    $where[] = 'b.status = ?';
    $params[] = $f_status;
    $types .= 's';
}
if ($f_payment !== '') {
    $where[] = 'b.payment_status = ?';
    $params[] = $f_payment;
    $types .= 's';
}
if ($f_ground > 0) {
    $where[] = 'b.ground_id = ?';
    $params[] = $f_ground;
    $types .= 'i';
}

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

if ($view === 'waitlist') {
    $waitWhere = ['g.manager_id = ' . (int)$_SESSION['user_id']];
    $waitParams = [];
    $waitTypes = '';
    if ($f_date_from !== '') {
        $waitWhere[] = 'w.booking_date >= ?';
        $waitParams[] = $f_date_from;
        $waitTypes .= 's';
    }
    if ($f_date_to !== '') {
        $waitWhere[] = 'w.booking_date <= ?';
        $waitParams[] = $f_date_to;
        $waitTypes .= 's';
    }
    if ($f_ground > 0) {
        $waitWhere[] = 'w.ground_id = ?';
        $waitParams[] = $f_ground;
        $waitTypes .= 'i';
    }
    $waitBaseSql = 'SELECT w.id, w.ground_id, w.booking_date, w.start_time, w.created_at,
                           g.name AS ground_name, u.name AS user_name, u.email AS user_email
                    FROM waitlist w
                    JOIN grounds g ON g.id = w.ground_id
                    JOIN users u ON u.id = w.user_id
                    WHERE ' . implode(' AND ', $waitWhere) . '
                    ORDER BY w.booking_date ASC, w.start_time ASC, w.created_at ASC';
    $totalSql = 'SELECT COUNT(*) c FROM waitlist w JOIN grounds g ON g.id = w.ground_id WHERE ' . implode(' AND ', $waitWhere);
    $stmt = $conn->prepare($totalSql);
    if ($waitTypes !== '') { $stmt->bind_param($waitTypes, ...$waitParams); }
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['c'];
    $totalPages = max(1, (int)ceil($total / $perPage));
    $stmt = $conn->prepare($waitBaseSql . ' LIMIT ? OFFSET ?');
    if ($waitTypes !== '') {
        $waitBindParams = array_merge($waitParams, [$perPage, $offset]);
        $stmt->bind_param($waitTypes . 'ii', ...$waitBindParams);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $isWaitlist = true;
} else {
    $isWaitlist = false;
    $baseSql = 'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
                b.payment_status, b.amount_paid, b.created_at,
                g.name AS ground_name, u.name AS user_name
             FROM bookings b
             JOIN grounds g ON g.id = b.ground_id
             JOIN users u ON u.id = b.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY b.booking_date DESC, b.start_time ASC';
    $totalSql = 'SELECT COUNT(*) c FROM bookings b JOIN grounds g ON g.id = b.ground_id WHERE ' . implode(' AND ', $where);
    $stmt = $conn->prepare($totalSql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['c'];
    $totalPages = max(1, (int)ceil($total / $perPage));
    $stmt = $conn->prepare($baseSql . ' LIMIT ? OFFSET ?');
    if ($types !== '') {
        $bookBindParams = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($types . 'ii', ...$bookBindParams);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$dataParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// count for pagination
$countSql = 'SELECT COUNT(*) c FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE ' . implode(' AND ', $where);
$stmt = $conn->prepare($countSql);
$countTypes = '';
$countParams = [];
if ($f_date_from !== '') { $countParams[] = $f_date_from; $countTypes .= 's'; }
if ($f_date_to !== '') { $countParams[] = $f_date_to; $countTypes .= 's'; }
if ($f_status !== '') { $countParams[] = $f_status; $countTypes .= 's'; }
if ($f_payment !== '') { $countParams[] = $f_payment; $countTypes .= 's'; }
if ($f_ground > 0) { $countParams[] = $f_ground; $countTypes .= 'i'; }
if ($countParams) {
    $stmt->bind_param($countTypes, ...$countParams);
}
$stmt->execute();
$totalRows = (int)$stmt->get_result()->fetch_assoc()['c'];
$totalPages = (int)ceil($totalRows / $perPage);

$hasFilters = $f_date_from !== '' || $f_date_to !== '' || $f_status !== '' || $f_payment !== '' || $f_ground > 0;

$baseFilters = [];
if ($f_date_from !== '') { $baseFilters['date_from'] = $f_date_from; }
if ($f_date_to !== '') { $baseFilters['date_to'] = $f_date_to; }
if ($f_status !== '') { $baseFilters['status'] = $f_status; }
if ($f_payment !== '') { $baseFilters['payment'] = $f_payment; }
if ($f_ground > 0) { $baseFilters['ground'] = $f_ground; }
$baseQuery = $baseFilters ? http_build_query($baseFilters) . '&' : '';

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="nav-back mob-back" aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h2>Bookings on My Grounds</h2>
        <div class="actions">
        </div>
    </div>
</div>

<div class="table-toolbar reveal">
    <div class="search-pill">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="managerSearch" placeholder="Search bookings by ground, player, ref…" autocomplete="off">
        <i class="fa-regular fa-circle-xmark" id="managerClear" role="button" aria-label="Clear search"></i>
    </div>
</div>

<div class="courts-toolbar reveal">
    <form method="get" action="<?php echo base_url('manager/bookings.php'); ?>" class="courts-search">
        <div class="search-field">
            <label for="fGround">Ground</label>
            <select id="fGround" name="ground">
                <option value="0">All grounds</option>
                <?php foreach ($myGrounds as $mg): ?>
                    <option value="<?php echo (int)$mg['id']; ?>" <?php echo $f_ground === (int)$mg['id'] ? 'selected' : ''; ?>><?php echo e($mg['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="search-field">
            <label for="fDateFrom">From</label>
            <input type="date" id="fDateFrom" name="date_from" value="<?php echo e($f_date_from); ?>">
        </div>
        <div class="search-field">
            <label for="fDateTo">To</label>
            <input type="date" id="fDateTo" name="date_to" value="<?php echo e($f_date_to); ?>">
        </div>
        <div class="search-field">
            <label for="fStatus">Status</label>
            <select id="fStatus" name="status">
                <option value="">All statuses</option>
                <option value="confirmed" <?php echo $f_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="cancelled" <?php echo $f_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="search-field">
            <label for="fPayment">Payment</label>
            <select id="fPayment" name="payment">
                <option value="">Any payment</option>
                <option value="unpaid" <?php echo $f_payment === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="partial" <?php echo $f_payment === 'partial' ? 'selected' : ''; ?>>Partial</option>
                <option value="paid" <?php echo $f_payment === 'paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
        </div>
<button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
        <a href="<?php echo base_url('manager/bookings.php?view=' . $view . '&export_excel=1' . ($hasFilters ? '&' . http_build_query(['ground' => $f_ground, 'date_from' => $f_date_from, 'date_to' => $f_date_to, 'status' => $f_status, 'payment' => $f_payment]) : '')); ?>" class="btn btn-outline"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="<?php echo base_url('manager/bookings.php?view=' . $view . '&export=1' . ($hasFilters ? '&' . http_build_query(['ground' => $f_ground, 'date_from' => $f_date_from, 'date_to' => $f_date_to, 'status' => $f_status, 'payment' => $f_payment]) : '')); ?>" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <?php if ($hasFilters): ?>
            <a href="<?php echo base_url('manager/bookings.php?view=' . $view); ?>" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="view-tabs reveal" style="margin:12px 0 18px;">
    <a href="<?php echo base_url('manager/bookings.php?view=bookings' . ($hasFilters ? '&' . http_build_query(['ground' => $f_ground, 'date_from' => $f_date_from, 'date_to' => $f_date_to, 'status' => $f_status, 'payment' => $f_payment]) : '')); ?>" class="view-tab <?php echo $view === 'bookings' ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check"></i> Bookings</a>
    <a href="<?php echo base_url('manager/bookings.php?view=waitlist' . ($hasFilters ? '&' . http_build_query(['ground' => $f_ground, 'date_from' => $f_date_from, 'date_to' => $f_date_to, 'status' => $f_status, 'payment' => $f_payment]) : '')); ?>" class="view-tab <?php echo $view === 'waitlist' ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Waitlist</a>
</div>

<?php if (empty($rows)): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular <?php echo $isWaitlist ? 'fa-clock-rotate-left' : 'fa-calendar-xmark'; ?>"></i></span><h3><?php echo $hasFilters ? 'No ' . ($isWaitlist ? 'waitlist entries' : 'bookings') . ' match your filters' : 'No ' . ($isWaitlist ? 'waitlist entries on your grounds yet' : 'bookings on your grounds yet'); ?></h3><p><?php echo $hasFilters ? 'Try adjusting or clearing the filters above.' : ($isWaitlist ? 'When players join a waitlist, it will appear here.' : 'When players reserve a slot, it will appear here.'); ?></p></div>
<?php else: ?>
    <?php if ($isWaitlist): ?>
        <div class="mbookings reveal">
            <?php foreach ($rows as $w): ?>
                <div class="mbooking waitlist-row">
                    <div class="mb-left">
                        <div class="mb-main">
                            <span class="mb-title"><?php echo e($w['ground_name']); ?></span>
                            <span class="mb-meta"><i class="fa-regular fa-clock"></i> <?php echo e(date('D, M j', strtotime($w['booking_date']))) . ' · ' . substr($w['start_time'], 0, 5); ?></span>
                        </div>
                        <div class="mb-sub"><i class="fa-solid fa-user"></i> <?php echo e($w['user_name']); ?> <span class="muted">(<?php echo e($w['user_email']); ?>)</span></div>
                        <div class="mb-sub muted"><i class="fa-solid fa-clock-rotate-left"></i> Joined <?php echo e(date('M j, Y g:i A', strtotime($w['created_at']))); ?></div>
                    </div>
                    <div class="mb-actions">
                        <a href="<?php echo base_url('pages/booking_details.php?ground=' . (int)$w['ground_id'] . '&date=' . e($w['booking_date']) . '&time=' . e($w['start_time'])); ?>" class="btn btn-outline btn-sm" title="View slot" target="_blank"><i class="fa-solid fa-external-link-alt"></i></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="mbookings reveal">
            <?php foreach ($rows as $b): ?>
                <?php
                $canMarkPaid = $b['status'] === 'confirmed' && in_array($b['payment_status'], ['unpaid', 'partial'], true);
                $markPaidAction = '';
                if ($canMarkPaid) {
                    $markPaidAction = '<a href="' . base_url('manager/bookings.php?mark_paid=' . (int)$b['id'] . '&csrf=' . csrf_token())
                        . '" class="mb-cta mb-cta-mark" title="Mark as paid (paid at court)" data-confirm="Mark this booking as paid at court?"'
                        . ' data-confirm-ok="Yes, mark paid" data-confirm-cancel="Cancel">'
                        . '<i class="fa-solid fa-coins"></i> Mark paid</a>';
                }
                booking_card_mini($b, 'user', $b['ground_name'] . ' ' . $b['user_name'] . ' ' . substr($b['start_time'], 0, 5) . ' ' . $b['booking_ref'] . ' ' . $b['status'] . ' ' . $b['payment_status'], $markPaidAction);
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Bookings pages">
            <?php if ($page > 1): ?>
                <a class="page-link" href="<?php echo base_url('manager/bookings.php?' . $baseQuery . 'page=' . ($page - 1)); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo base_url('manager/bookings.php?' . $baseQuery . 'page=' . $i); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="page-link" href="<?php echo base_url('manager/bookings.php?' . $baseQuery . 'page=' . ($page + 1)); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

