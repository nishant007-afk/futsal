<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    verify_csrf();
    $booking_id = (int)$_POST['cancel_booking'];
    $stmt = $conn->prepare('SELECT b.user_id, b.ground_id, b.booking_ref, b.booking_date, b.start_time, b.total_price, b.payment_status, b.payment_method FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND g.manager_id = ? AND b.status != "cancelled"');
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $cancelTarget = $stmt->get_result()->fetch_assoc();

    if ($cancelTarget && strtotime($cancelTarget['booking_date'] . ' ' . $cancelTarget['start_time']) < time()) {
        set_flash('info', 'That game already started, so it cannot be cancelled.');
        redirect('manager/bookings.php');
    }

    $stmt = $conn->prepare(
        'UPDATE bookings b
         JOIN grounds g ON g.id = b.ground_id
         SET b.status = "cancelled"
         WHERE b.id = ? AND g.manager_id = ? AND b.status != "cancelled"'
    );
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        if ($cancelTarget) {
            notify_user((int)$cancelTarget['user_id'], 'Booking cancelled by the court', 'The court cancelled your booking. Any eligible advance will be refunded after confirmation.', 'fa-circle-xmark', 'pages/booking_details.php?id=' . $booking_id);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    verify_csrf();
    $booking_id = (int)$_POST['mark_paid'];
    $stmt = $conn->prepare('SELECT b.user_id, b.ground_id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.discount, b.amount_paid, g.name AS ground_name FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND g.manager_id = ? AND b.status = "confirmed" AND b.payment_status IN ("unpaid", "partial")');
    $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$target) {
        set_flash_error('That booking could not be marked as paid.', 'It may already be fully paid, or it belongs to another court.', 'Return to your bookings list to pick another one.', 'manager/bookings.php');
    } else {
        $netTotal = round((float)$target['total_price'] - (float)($target['discount'] ?? 0), 2);
        $update = $conn->prepare('UPDATE bookings SET payment_status = "paid", payment_type = "full", amount_paid = ?, payment_method = "at_court", paid_at = NOW() WHERE id = ? AND status = "confirmed"');
        $update->bind_param('di', $netTotal, $booking_id);
        if ($update->execute() && $update->affected_rows > 0) {
            notify_user(
                (int)$target['user_id'],
                'Payment confirmed by the court',
                'Your booking ' . $target['booking_ref'] . ' is now marked as paid at court.',
                'fa-sack-dollar',
                'pages/booking_details.php?id=' . $booking_id
            );
            $playerEmail = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
            $playerEmail->bind_param('i', $target['user_id']);
            $playerEmail->execute();
            $playerRow = $playerEmail->get_result()->fetch_assoc();
            if ($playerRow) {
                send_booking_email(
                    $playerRow['email'],
                    'Payment confirmed at ' . ($target['ground_name'] ?? 'the court'),
                    'Your booking is marked as paid',
                    [
                        'Booking ref' => $target['booking_ref'],
                        'Court' => $target['ground_name'] ?? 'the court',
                        'Date' => date('D, M j, Y', strtotime($target['booking_date'])),
                        'Time' => substr($target['start_time'], 0, 5) . ' - ' . substr($target['end_time'], 0, 5),
                        'Paid' => 'Rs ' . number_format((float)$target['total_price'], 0) . ' (at court)',
                    ],
                    'Nothing left to do: your booking is fully settled. Enjoy your game!',
                    $playerRow['name'] ?? ''
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

$subStatus = subscription_status((int)$_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_walkin'])) {
    verify_csrf();
    if (!$subStatus['active']) {
        set_flash_error(
            'Cannot create walk-in bookings.',
            'Your monthly subscription is ' . strtolower($subStatus['label']) . '.',
            'Renew your subscription first to create reservations and manage your courts.',
            'manager/subscription.php'
        );
        redirect('manager/bookings.php');
    }

    $ground_id = (int)($_POST['ground_id'] ?? 0);
    $booking_date = trim($_POST['booking_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $is_maintenance = isset($_POST['is_maintenance']) && $_POST['is_maintenance'] === '1';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_status = trim($_POST['payment_status'] ?? 'paid');
    if (!in_array($payment_status, ['paid', 'unpaid', 'partial'], true)) {
        $payment_status = 'paid';
    }

    // Format times HH:MM -> HH:MM:00
    if (preg_match('/^\d{2}:\d{2}$/', $start_time)) { $start_time .= ':00'; }
    if (preg_match('/^\d{2}:\d{2}$/', $end_time)) { $end_time .= ':00'; }

    // Ownership check
    $chkGround = $conn->prepare('SELECT id, name, price_per_hour FROM grounds WHERE id = ? AND manager_id = ?');
    $chkGround->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $chkGround->execute();
    $groundRow = $chkGround->get_result()->fetch_assoc();

    $wErrors = [];
    if (!$groundRow) {
        $wErrors[] = 'Please select a valid court.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
        $wErrors[] = 'Please select a valid booking date.';
    }
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end_time)) {
        $wErrors[] = 'Please select both start and end time.';
    } elseif ($end_time <= $start_time) {
        $wErrors[] = 'End time must be after start time.';
    }

    // Check slot collision
    if (empty($wErrors)) {
        $colCheck = $conn->prepare('SELECT id FROM bookings WHERE ground_id = ? AND booking_date = ? AND status != "cancelled" AND start_time < ? AND end_time > ?');
        $colCheck->bind_param('isss', $ground_id, $booking_date, $end_time, $start_time);
        $colCheck->execute();
        if ($colCheck->get_result()->num_rows > 0) {
            $wErrors[] = 'That time slot overlaps with an existing booking.';
        }
    }

    if ($wErrors) {
        set_flash_error('Could not create booking.', implode(' ', $wErrors), 'Please try selecting a different time slot.', 'manager/bookings.php');
        redirect('manager/bookings.php');
    }

    // Calculate price
    $total_price = 0.00;
    if (!$is_maintenance) {
        $hourly = ground_price_for_date($ground_id, (float)$groundRow['price_per_hour'], $booking_date);
        $durHrs = max(0.5, (strtotime($end_time) - strtotime($start_time)) / 3600);
        $total_price = round($hourly * $durHrs, 2);
    }
    $amount_paid = ($payment_status === 'paid') ? $total_price : 0.00;

    // Player account lookup / create guest player
    $target_user_id = (int)$_SESSION['user_id'];
    if (!$is_maintenance && $customer_name !== '') {
        $lookupEmail = 'walkin_' . preg_replace('/[^0-9]/', '', $customer_phone !== '' ? $customer_phone : bin2hex(random_bytes(3))) . '@atcourt.local';
        $userFind = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $userFind->bind_param('s', $lookupEmail);
        $userFind->execute();
        $foundUser = $userFind->get_result()->fetch_assoc();
        if ($foundUser) {
            $target_user_id = (int)$foundUser['id'];
        } else {
            $guestPass = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $rolePlayer = 'player';
            $insU = $conn->prepare('INSERT INTO users (name, email, password, phone, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())');
            $insU->bind_param('sssss', $customer_name, $lookupEmail, $guestPass, $customer_phone, $rolePlayer);
            if ($insU->execute()) {
                $target_user_id = (int)$conn->insert_id;
            }
        }
    }

    $refPrefix = $is_maintenance ? 'MAINT-' : 'WALK-';
    $booking_ref = $refPrefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    $payMethod = $is_maintenance ? 'none' : 'at_court';

    $insB = $conn->prepare(
        'INSERT INTO bookings (booking_ref, user_id, ground_id, booking_date, start_time, end_time, total_price, amount_paid, payment_status, payment_method, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "confirmed", NOW())'
    );
    $insB->bind_param('siisssddss', $booking_ref, $target_user_id, $ground_id, $booking_date, $start_time, $end_time, $total_price, $amount_paid, $payment_status, $payMethod);

    if ($insB->execute()) {
        $newBId = (int)$conn->insert_id;
        $successMsg = $is_maintenance ? 'Court slot reserved for maintenance.' : 'Walk-in booking ' . $booking_ref . ' created successfully.';
        set_flash('success', $successMsg);
    } else {
        set_flash_error('Failed to create booking.', 'A database error occurred while creating the reservation.', 'Try again.', 'manager/bookings.php');
    }
    redirect('manager/bookings.php');
}

$myGrounds = $conn->query(
    'SELECT id, name FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'] . ' ORDER BY name'
)->fetch_all(MYSQLI_ASSOC);

// Manager all-time revenue & payment stats for their grounds
$mgrFinStmt = $conn->prepare(
    'SELECT 
        COALESCE(SUM(CASE WHEN b.status = "confirmed" THEN (b.total_price - COALESCE(b.discount, 0)) ELSE 0 END), 0) AS gross_rev,
        COALESCE(SUM(CASE WHEN b.status = "confirmed" THEN b.amount_paid ELSE 0 END), 0) AS total_collected,
        COALESCE(SUM(CASE WHEN b.status = "confirmed" AND b.payment_status IN ("unpaid", "partial") THEN ((b.total_price - COALESCE(b.discount, 0)) - b.amount_paid) ELSE 0 END), 0) AS outstanding_due
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     WHERE g.manager_id = ?'
);
$mgrFinStmt->bind_param('i', $_SESSION['user_id']);
$mgrFinStmt->execute();
$mgrFin = $mgrFinStmt->get_result()->fetch_assoc();

$f_date_from = trim($_GET['date_from'] ?? '');
$f_date_to = trim($_GET['date_to'] ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_payment = trim($_GET['payment'] ?? '');
$f_ground = (int)($_GET['ground'] ?? 0);
$f_search = trim($_GET['search'] ?? '');
if (mb_strlen($f_search) > 60) { $f_search = mb_substr($f_search, 0, 60); }
$quick = trim($_GET['quick'] ?? ''); // 'today' | 'upcoming' | 'unpaid'
if (!in_array($quick, ['', 'today', 'upcoming', 'unpaid', 'past'], true)) {
    $quick = '';
}

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

$where = ['g.manager_id = ' . (int)$_SESSION['user_id']];
$params = [];
$types = '';
if ($f_search !== '') {
    $where[] = '(u.name LIKE ? OR g.name LIKE ? OR b.booking_ref LIKE ?)';
    $sLike = '%' . $f_search . '%';
    $params[] = $sLike;
    $params[] = $sLike;
    $params[] = $sLike;
    $types .= 'sss';
}
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

// Tab badge counts use toolbar filters only (no quick-tab), mirroring the user's My Bookings logic.
$tabCounts = ['all' => 0, 'upcoming' => 0, 'unpaid' => 0, 'past' => 0];
$countStmt = $conn->prepare('SELECT b.booking_date, b.status, b.payment_status FROM bookings b JOIN grounds g ON g.id = b.ground_id JOIN users u ON u.id = b.user_id WHERE ' . implode(' AND ', $where));
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$countRows = $countStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$today = date('Y-m-d');
$tabCounts['all'] = count($countRows);
foreach ($countRows as $cr) {
    $crCancelled = $cr['status'] === 'cancelled';
    if ($cr['booking_date'] >= $today && !$crCancelled) { $tabCounts['upcoming']++; }
    if (in_array($cr['payment_status'], ['unpaid', 'partial'], true) && !$crCancelled) { $tabCounts['unpaid']++; }
    if ($cr['booking_date'] < $today || $crCancelled) { $tabCounts['past']++; }
}

// Quick-tab condition applied to the listing query only (counts above intentionally exclude it).
if ($quick === 'today') {
    $where[] = 'b.booking_date = ?';
    $params[] = $today;
    $types .= 's';
}
if ($quick === 'upcoming') {
    $where[] = 'b.booking_date >= ?';
    $params[] = $today;
    $types .= 's';
    $where[] = "b.status != 'cancelled'";
}
if ($quick === 'unpaid') {
    $where[] = "b.payment_status IN ('unpaid','partial')";
    $where[] = "b.status = 'confirmed'";
}
if ($quick === 'past') {
    $where[] = "(b.booking_date < ? OR b.status = 'cancelled')";
    $params[] = $today;
    $types .= 's';
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$baseSql = 'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.payment_method, b.created_at,
            g.name AS ground_name, u.name AS user_name
         FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         JOIN users u ON u.id = b.user_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY b.booking_date DESC, b.start_time ASC';
$totalSql = 'SELECT COUNT(*) c FROM bookings b JOIN grounds g ON g.id = b.ground_id JOIN users u ON u.id = b.user_id WHERE ' . implode(' AND ', $where);
$stmt = $conn->prepare($totalSql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;
$stmt = $conn->prepare($baseSql . ' LIMIT ? OFFSET ?');
if ($types !== '') {
    $bookBindParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($types . 'ii', ...$bookBindParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['export'])) {
    // Build the same query as the list but for CSV
    $exportWhere = ['g.manager_id = ?'];
    $exportParams = [(int)$_SESSION['user_id']];
    $exportTypes = 'i';

    $statusFilter = $_GET['status'] ?? '';
    if (in_array($statusFilter, ['confirmed', 'cancelled', 'pending'], true)) {
        $exportWhere[] = 'b.status = ?';
        $exportParams[] = $statusFilter;
        $exportTypes .= 's';
    }

    $exportSql = 'SELECT b.id, b.booking_ref, u.name AS user_name, u.email AS user_email,
                    g.name AS ground_name, b.booking_date, b.start_time, b.end_time,
                    b.total_price, b.discount, b.status, b.payment_status, b.payment_method,
                    b.amount_paid, b.created_at
                  FROM bookings b
                  JOIN grounds g ON g.id = b.ground_id
                  JOIN users u ON u.id = b.user_id
                  WHERE ' . implode(' AND ', $exportWhere) . '
                  ORDER BY b.booking_date DESC, b.start_time ASC';
    $exportStmt = $conn->prepare($exportSql);
    $exportStmt->bind_param($exportTypes, ...$exportParams);
    $exportStmt->execute();
    $exportRows = $exportStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $exportStmt->close();

    $csvData = [['ID', 'Ref', 'Player', 'Email', 'Court', 'Date', 'Start', 'End', 'Total', 'Discount', 'Status', 'Payment', 'Method', 'Amount Paid', 'Created']];
    foreach ($exportRows as $r) {
        $csvData[] = [
            $r['id'], $r['booking_ref'], $r['user_name'], $r['user_email'],
            $r['ground_name'], $r['booking_date'], $r['start_time'], $r['end_time'],
            $r['total_price'], $r['discount'], $r['status'], $r['payment_status'],
            $r['payment_method'], $r['amount_paid'], $r['created_at']
        ];
    }
    export_csv($csvData, 'bookings.csv');
}

$hasFilters = $f_search !== '' || $f_date_from !== '' || $f_date_to !== '' || $f_status !== '' || $f_payment !== '' || $f_ground > 0;

$baseFilters = [];
if ($f_search !== '') { $baseFilters['search'] = $f_search; }
if ($f_date_from !== '') { $baseFilters['date_from'] = $f_date_from; }
if ($f_date_to !== '') { $baseFilters['date_to'] = $f_date_to; }
if ($f_status !== '') { $baseFilters['status'] = $f_status; }
if ($f_payment !== '') { $baseFilters['payment'] = $f_payment; }
if ($f_ground > 0) { $baseFilters['ground'] = $f_ground; }
$baseQuery = $baseFilters ? http_build_query($baseFilters) . '&' : '';
if ($quick !== '') { $baseQuery .= 'quick=' . rawurlencode($quick) . '&'; }

function mgr_b_url(?string $qTab = null, ?int $p = null): string
{
    $params = $_GET;
    if ($qTab !== null) {
        if ($qTab === '') {
            unset($params['quick']);
        } else {
            $params['quick'] = $qTab;
        }
        unset($params['page']);
    }
    if ($p !== null) {
        if ($p <= 1) {
            unset($params['page']);
        } else {
            $params['page'] = $p;
        }
    }
    unset($params['export']);
    $qs = http_build_query($params);
    return base_url('manager/bookings.php' . ($qs !== '' ? '?' . $qs : ''));
}

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h1 class="page-title">Bookings on My Grounds</h1>
        <div class="actions">
            <?php if ($subStatus['active']): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('walkinModal').showModal()"><i class="fa-solid fa-plus"></i> Walk-in Booking</button>
            <?php else: ?>
                <a href="<?php echo base_url('manager/subscription.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-triangle-exclamation"></i> Renew to Book</a>
            <?php endif; ?>
            <a href="<?php echo e(base_url('manager/bookings.php?export=1' . ($_SERVER['QUERY_STRING'] !== '' ? '&' . $_SERVER['QUERY_STRING'] : ''))); ?>" class="btn btn-outline btn-sm">Export CSV</a>
        </div>
    </div>
</div>

<?php if (!$subStatus['active']): ?>
    <div class="notice-alert reveal" role="status" style="margin-bottom: 16px;">
        <i class="fa-solid fa-triangle-exclamation notice-alert-icon" aria-hidden="true"></i>
        <div class="notice-alert-body">
            <strong>Monthly Subscription Required:</strong> Your account is currently <?php echo e(strtolower($subStatus['label'])); ?>. New walk-in bookings and public court listings remain suspended until your subscription is renewed.
        </div>
        <a href="<?php echo base_url('manager/subscription.php'); ?>" class="btn btn-primary btn-sm notice-alert-cta">Pay Fee</a>
    </div>
<?php endif; ?>

<div class="stat-grid reveal" style="margin-bottom: 18px;">
    <div class="stat">
        <h3>Total Revenue</h3>
        <p class="stat-amount"><?php echo format_price((float)$mgrFin['gross_rev']); ?></p>
        <span class="muted">All-time confirmed bookings</span>
    </div>
    <div class="stat">
        <h3>Collected at Court / Paid</h3>
        <p class="stat-amount" style="color: var(--brand);"><?php echo format_price((float)$mgrFin['total_collected']); ?></p>
        <span class="muted">Funds received &amp; confirmed</span>
    </div>
    <div class="stat">
        <h3>Outstanding Due</h3>
        <p class="stat-amount" style="color: <?php echo (float)$mgrFin['outstanding_due'] > 0 ? 'var(--danger)' : 'var(--ink)'; ?>;"><?php echo format_price((float)$mgrFin['outstanding_due']); ?></p>
        <span class="muted"><?php echo (float)$mgrFin['outstanding_due'] > 0 ? 'To be collected upon arrival' : 'All accounts settled'; ?></span>
    </div>
    <div class="stat">
        <h3>Subscription</h3>
        <p class="stat-amount" style="font-size: 20px;"><?php echo e($subStatus['label']); ?></p>
        <span class="muted">
            <?php if ($subStatus['sub'] && $subStatus['sub']['period_end']): ?>
                Valid until <?php echo date('M j, Y', strtotime($subStatus['sub']['period_end'])); ?>
            <?php else: ?>
                Inactive
            <?php endif; ?>
        </span>
    </div>
</div>

<!-- Walk-in / Slot Block Modal -->
<dialog id="walkinModal" class="app-dialog" style="max-width: 520px; width: 90vw; padding: 24px; border-radius: var(--r-lg); border: 1px solid var(--line); box-shadow: var(--s3); background: var(--bg);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
        <h3 style="margin:0; font-size:1.25rem; font-weight:700;"><i class="fa-solid fa-calendar-plus" style="color:var(--brand); margin-right:8px;"></i>New Walk-in / Hold Slot</h3>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('walkinModal').close()" style="border:none; font-size:1.2rem; cursor:pointer;" aria-label="Close">&times;</button>
    </div>
    <form method="post" action="<?php echo base_url('manager/bookings.php'); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="create_walkin" value="1">

        <div style="display:flex; flex-direction:column; gap:14px;">
            <div>
                <label for="wGround" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Select Court *</label>
                <select id="wGround" name="ground_id" required class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
                    <?php foreach ($myGrounds as $mg): ?>
                        <option value="<?php echo (int)$mg['id']; ?>"><?php echo e($mg['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="wDate" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Booking Date *</label>
                <input type="date" id="wDate" name="booking_date" value="<?php echo date('Y-m-d'); ?>" required class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                <div>
                    <label for="wStart" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Start Time *</label>
                    <input type="time" id="wStart" name="start_time" required class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
                </div>
                <div>
                    <label for="wEnd" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">End Time *</label>
                    <input type="time" id="wEnd" name="end_time" required class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
                </div>
            </div>

            <div style="background:var(--bg-soft); padding:10px 14px; border-radius:var(--r); border:1px solid var(--line);">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:0.9rem; font-weight:600;">
                    <input type="checkbox" name="is_maintenance" value="1" id="wMaint" onchange="document.getElementById('wCustomerFields').style.display = this.checked ? 'none' : 'block';">
                    Reserve slot for maintenance / court practice
                </label>
            </div>

            <div id="wCustomerFields">
                <div style="margin-bottom:12px;">
                    <label for="wName" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Player / Team Name</label>
                    <input type="text" id="wName" name="customer_name" placeholder="e.g. John Doe / Thunder FC" class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
                </div>
                <div style="margin-bottom:12px;">
                    <label for="wPhone" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Phone Number</label>
                    <input type="tel" id="wPhone" name="customer_phone" placeholder="98XXXXXXXX" class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
                </div>
                <div>
                    <label for="wPayStatus" style="display:block; font-size:0.85rem; font-weight:600; margin-bottom:6px;">Payment Status</label>
                    <select id="wPayStatus" name="payment_status" class="input" style="width:100%; height:40px; border-radius:var(--r); border:1px solid var(--line); padding:0 12px; background:var(--bg-soft);">
                        <option value="paid">Paid at court</option>
                        <option value="unpaid">Unpaid (Pay after game)</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('walkinModal').close()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Create Booking</button>
            </div>
        </div>
    </form>
</dialog>

<div class="courts-toolbar reveal">
    <form method="get" action="<?php echo base_url('manager/bookings.php'); ?>" class="courts-search" data-ajax-results="bookingsResults">
            <div class="toolbar-flex-main">
            <div class="search-field sf-grow">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" id="managerSearch" name="search" placeholder="Player, court, or ref..." value="<?php echo e($f_search); ?>" autocomplete="off" aria-label="Search bookings">
            </div>
            <div class="search-field">
                <i class="fa-solid fa-store" aria-hidden="true"></i>
                <select id="fGround" name="ground" aria-label="Filter by ground">
                    <option value="0">All grounds</option>
                    <?php foreach ($myGrounds as $mg): ?>
                        <option value="<?php echo (int)$mg['id']; ?>" <?php echo $f_ground === (int)$mg['id'] ? 'selected' : ''; ?>><?php echo e($mg['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-field">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <input type="date" id="fDateFrom" name="date_from" value="<?php echo e($f_date_from); ?>" aria-label="From date" title="From date">
            </div>
            <div class="search-field">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <input type="date" id="fDateTo" name="date_to" value="<?php echo e($f_date_to); ?>" aria-label="To date" title="To date">
            </div>
            <div class="search-field">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <select id="fStatus" name="status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="confirmed" <?php echo $f_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="cancelled" <?php echo $f_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="search-field">
                <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                <select id="fPayment" name="payment" aria-label="Filter by payment">
                    <option value="">Any payment</option>
                    <option value="unpaid" <?php echo $f_payment === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="partial" <?php echo $f_payment === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="paid" <?php echo $f_payment === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <div class="toolbar-actions">
                <?php if ($hasFilters): ?>
                    <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

</div>

<div id="bookingsResults">
<div class="view-tabs" style="margin:12px 0 6px;">
    <a href="<?php echo mgr_b_url(''); ?>" class="view-tab <?php echo $quick === '' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">All (<?php echo $tabCounts['all']; ?>)</a>
    <a href="<?php echo mgr_b_url('upcoming'); ?>" class="view-tab <?php echo $quick === 'upcoming' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">Upcoming (<?php echo $tabCounts['upcoming']; ?>)</a>
    <a href="<?php echo mgr_b_url('unpaid'); ?>" class="view-tab <?php echo $quick === 'unpaid' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">Unpaid (<?php echo $tabCounts['unpaid']; ?>)</a>
    <a href="<?php echo mgr_b_url('past'); ?>" class="view-tab <?php echo $quick === 'past' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">Past (<?php echo $tabCounts['past']; ?>)</a>
</div>

<?php if (empty($rows)): ?>
    <div class="empty"><span class="big"><i class="fa-regular fa-calendar-xmark"></i></span><h3><?php echo $hasFilters ? 'No bookings match your filters' : 'No bookings on your grounds yet'; ?></h3><p><?php echo $hasFilters ? 'Try adjusting or clearing the filters above.' : 'When players reserve a slot, it will appear here.'; ?></p></div>
<?php else: ?>

        <?php bookings_table_html($rows, ['show_player' => true, 'manager_action' => true, 'drawer' => true]); ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Bookings pages" data-ajax-link="bookingsResults">
            <?php if ($page > 1): ?>
                <a class="page-link" href="<?php echo mgr_b_url(null, $page - 1); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo mgr_b_url(null, $i); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="page-link" href="<?php echo mgr_b_url(null, $page + 1); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

