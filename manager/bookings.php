<?php
require_once __DIR__ . '/../config/db.php';
require_manager();

if (isset($_GET['cancel'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $booking_id = (int)$_GET['cancel'];
    $stmt = $conn->prepare(
        'SELECT b.user_id, b.ground_id, b.booking_date, b.start_time FROM bookings b
         JOIN grounds g ON g.id = b.ground_id
         WHERE b.id = ? AND g.manager_id = ? AND b.status != "cancelled"'
    );
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

$myGrounds = $conn->query(
    'SELECT id, name FROM grounds WHERE manager_id = ' . (int)$_SESSION['user_id'] . ' ORDER BY name'
)->fetch_all(MYSQLI_ASSOC);

$f_date_from = trim($_GET['date_from'] ?? '');
$f_date_to = trim($_GET['date_to'] ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_payment = trim($_GET['payment'] ?? '');
$f_ground = (int)($_GET['ground'] ?? 0);

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

$sql = 'SELECT b.id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.created_at,
            g.name AS ground_name, u.name AS user_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY b.booking_date DESC, b.start_time ASC';

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $csv = [['Reference', 'Ground', 'Customer', 'Date', 'Start', 'End', 'Total (Rs)', 'Status', 'Payment', 'Paid (Rs)', 'Booked At']];
    foreach ($bookings as $b) {
        $csv[] = [
            $b['booking_ref'] ?? '',
            $b['ground_name'],
            $b['user_name'],
            $b['booking_date'],
            substr($b['start_time'], 0, 5),
            substr($b['end_time'], 0, 5),
            number_format((float)$b['total_price'], 2),
            $b['status'],
            $b['payment_status'],
            number_format((float)$b['amount_paid'], 2),
            $b['created_at'],
        ];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($csv, 'bookings.xlsx');
    }
    export_csv($csv, 'bookings.csv');
}

$hasFilters = $f_date_from !== '' || $f_date_to !== '' || $f_status !== '' || $f_payment !== '' || $f_ground > 0;

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <h2><i class="fa-solid fa-list-check"></i> Bookings on My Grounds</h2>
    <a href="<?php echo base_url('manager/dashboard.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
</div>
<p class="muted" style="margin-bottom:8px;">Bookings confirm instantly. Cancel any slot you can no longer host.</p>

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
        <a href="<?php echo base_url('manager/bookings.php?export_excel=1' . ($hasFilters ? '&' . http_build_query(['ground' => $f_ground, 'date_from' => $f_date_from, 'date_to' => $f_date_to, 'status' => $f_status, 'payment' => $f_payment]) : '')); ?>" class="btn btn-outline"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="<?php echo base_url('manager/bookings.php?export=1' . ($hasFilters ? '&' . http_build_query(['ground' => $f_ground, 'date_from' => $f_date_from, 'date_to' => $f_date_to, 'status' => $f_status, 'payment' => $f_payment]) : '')); ?>" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <?php if ($hasFilters): ?>
            <a href="<?php echo base_url('manager/bookings.php'); ?>" class="btn btn-outline"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!$bookings): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-calendar-xmark"></i></span><h3><?php echo $hasFilters ? 'No bookings match your filters' : 'No bookings on your grounds yet'; ?></h3><p><?php echo $hasFilters ? 'Try adjusting or clearing the filters above.' : 'When players reserve a slot, it will appear here.'; ?></p></div>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($bookings as $b): ?>
            <div class="mbooking">
                <div class="mbooking-date">
                    <span class="bd-month"><?php echo e(strtoupper(date('M', strtotime($b['booking_date'])))); ?></span>
                    <span class="bd-day"><?php echo (int)date('d', strtotime($b['booking_date'])); ?></span>
                    <span class="bd-year"><?php echo e(date('Y', strtotime($b['booking_date']))); ?></span>
                </div>
                <div class="mbooking-main">
                    <div class="mbooking-head">
                        <h3><?php echo e($b['ground_name']); ?></h3>
                        <span class="mbooking-status">
                            <?php if ($b['status'] !== 'cancelled'): ?>
                                <span class="badge badge-<?php echo $b['payment_status'] === 'paid' ? 'paid' : ($b['payment_status'] === 'partial' ? 'partial' : 'unpaid'); ?>">
                                    <?php echo $b['payment_status'] === 'paid' ? 'Paid' : ($b['payment_status'] === 'partial' ? 'Advance paid' : 'Unpaid'); ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge badge-<?php echo e($b['status']); ?>"><?php echo e($b['status']); ?></span>
                        </span>
                    </div>
                    <div class="mbooking-meta">
                        <span><i class="fa-regular fa-clock"></i> <?php echo e(substr($b['start_time'], 0, 5)); ?> - <?php echo e(substr($b['end_time'], 0, 5)); ?></span>
                        <span class="mprice"><i class="fa-solid fa-tag"></i> <?php echo format_price($b['total_price']); ?></span>
                    </div>
                    <?php if ($b['status'] !== 'cancelled' && $b['payment_status'] !== 'paid'): ?>
                        <?php $due = (float)$b['total_price'] - (float)$b['amount_paid']; ?>
                        <div class="paybar <?php echo $b['payment_status'] === 'partial' ? 'partial' : 'unpaid'; ?>">
                            <span class="paybar-info">
                                <i class="fa-solid fa-<?php echo $b['payment_status'] === 'partial' ? 'hourglass-half' : 'circle-exclamation'; ?>"></i>
                                <span><?php echo $b['payment_status'] === 'partial'
                                    ? 'Advance received &middot; <strong>' . format_price($due) . '</strong> still to collect'
                                    : '<strong>' . format_price($due) . '</strong> due at the court &middot; unpaid'; ?></span>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mbooking-side">
                    <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-eye"></i> View details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

