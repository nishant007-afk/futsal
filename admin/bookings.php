<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    verify_csrf();
    $id = (int)$_POST['cancel_booking'];
    $stmt = $conn->prepare('SELECT user_id, ground_id, booking_date, start_time FROM bookings WHERE id = ? AND status != "cancelled"');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cancelTarget = $stmt->get_result()->fetch_assoc();

    // Do not cancel games that already started.
    if ($cancelTarget && strtotime($cancelTarget['booking_date'] . ' ' . $cancelTarget['start_time']) < time()) {
        set_flash('info', 'That game already started, so it cannot be cancelled.');
        redirect('admin/bookings.php');
    }

    $stmt = $conn->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ? AND status != "cancelled"');
    $stmt->bind_param('i', $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        if ($cancelTarget) {
            notify_user((int)$cancelTarget['user_id'], 'Booking cancelled', 'Your booking was cancelled by the platform. Any advance will be refunded.', 'fa-circle-xmark', 'pages/booking_details.php?id=' . $id);
            notify_waitlist_freed((int)$cancelTarget['ground_id'], $cancelTarget['booking_date'], $cancelTarget['start_time']);
        }
        set_flash('success', 'Booking cancelled.');
    } else {
        set_flash_error(
            'This booking could not be cancelled.',
            'It may already be cancelled, so there was nothing left to cancel.',
            'Refresh the bookings list to see the current status.',
            'admin/bookings.php'
        );
    }
    redirect('admin/bookings.php');
}

$bookingsAll = $conn->query(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.discount, b.status,
            b.payment_status, b.amount_paid, b.payment_method, b.created_at,
            g.name AS ground_name, g.location, u.name AS user_name, u.email AS user_email,
            m.name AS manager_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     LEFT JOIN users m ON m.id = g.manager_id
     ORDER BY b.booking_date DESC, b.start_time ASC'
)->fetch_all(MYSQLI_ASSOC);

// Toolbar filters (same names/UX as the user-facing My Bookings + manager views)
$f_search = trim($_GET['search'] ?? '');
if (mb_strlen($f_search) > 60) { $f_search = mb_substr($f_search, 0, 60); }
$f_status = trim($_GET['status'] ?? '');
if (!in_array($f_status, ['', 'confirmed', 'pending', 'cancelled'], true)) { $f_status = ''; }
$f_payment = trim($_GET['payment'] ?? '');
if (!in_array($f_payment, ['', 'unpaid', 'partial', 'paid'], true)) { $f_payment = ''; }

$rows = $bookingsAll;
if ($f_search !== '') {
    $needle = mb_strtolower($f_search);
    $rows = array_values(array_filter($rows, function ($b) use ($needle) {
        $hay = mb_strtolower(($b['ground_name'] ?? '') . ' ' . ($b['manager_name'] ?? '') . ' '
            . ($b['user_name'] ?? '') . ' ' . ($b['user_email'] ?? '') . ' ' . ($b['booking_ref'] ?? ''));
        return mb_strpos($hay, $needle) !== false;
    }));
}
if ($f_status !== '') {
    $rows = array_values(array_filter($rows, function ($b) use ($f_status) {
        return $b['status'] === $f_status;
    }));
}
if ($f_payment !== '') {
    $rows = array_values(array_filter($rows, function ($b) use ($f_payment) {
        return $b['payment_status'] === $f_payment;
    }));
}

// Same split logic as the player's My Bookings page.
$today = date('Y-m-d');
$upcoming = array_values(array_filter($rows, function ($b) use ($today) {
    return $b['booking_date'] >= $today && $b['status'] !== 'cancelled';
}));
$unpaid = array_values(array_filter($rows, function ($b) {
    return in_array($b['payment_status'], ['unpaid', 'partial'], true) && $b['status'] !== 'cancelled';
}));
$past = array_values(array_filter($rows, function ($b) use ($today) {
    return $b['booking_date'] < $today || $b['status'] === 'cancelled';
}));
usort($past, function ($a, $b) {
    return strcmp($b['booking_date'], $a['booking_date']) ?: strcmp($b['start_time'], $a['start_time']);
});

$view = $_GET['view'] ?? 'all';
if (!in_array($view, ['all', 'upcoming', 'unpaid', 'past'], true)) { $view = 'all'; }

$showRows = $rows;
if ($view === 'unpaid') {
    $showRows = $unpaid;
} elseif ($view === 'upcoming') {
    $showRows = $upcoming;
} elseif ($view === 'past') {
    $showRows = $past;
} elseif ($view === 'all') {
    $showRows = array_merge($upcoming, $past);
}

$perPage = 15;
$totalRows = count($showRows);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;
$bookings = array_slice($showRows, $offset, $perPage);

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $csv = [['Reference', 'Ground', 'Manager', 'Customer', 'Customer Email', 'Date', 'Start', 'End', 'Total (Rs)', 'Status', 'Payment', 'Method', 'Paid (Rs)', 'Booked At']];
    foreach ($bookingsAll as $b) {
        $csv[] = [
            $b['booking_ref'] ?? '',
            $b['ground_name'],
            $b['manager_name'] ?? '',
            $b['user_name'],
            $b['user_email'],
            $b['booking_date'],
            substr($b['start_time'], 0, 5),
            substr($b['end_time'], 0, 5),
            number_format((float)$b['total_price'], 2),
            $b['status'],
            $b['payment_status'],
            $b['payment_method'] ?? '',
            number_format((float)$b['amount_paid'], 2),
            $b['created_at'],
        ];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($csv, 'all-bookings.xlsx');
    }
    export_csv($csv, 'all-bookings.csv');
}

function ab_url(array $over = []): string
{
    $q = array_merge($_GET, $over);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) { unset($q[$k]); }
    }
    unset($q['page'], $q['export'], $q['export_excel']);
    return base_url('admin/bookings.php') . ($q ? '?' . http_build_query($q) : '');
}

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="page-back-arrow" data-back aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h1 class="page-title">All Bookings</h1>
        <div class="actions">
            <a href="<?php echo base_url('admin/bookings.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
            <a href="<?php echo base_url('admin/bookings.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
    </div>
</div>

<div class="courts-toolbar reveal">
    <form method="get" action="<?php echo base_url('admin/bookings.php'); ?>" class="courts-search" data-ajax-results="bookingsResults">
        <div class="courts-search-main courts-search-main--4">
            <div class="search-field sf-grow">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="text" id="adminSearch" name="search" placeholder="Court, player, manager or ref..." value="<?php echo e($f_search); ?>" autocomplete="off" aria-label="Search bookings">
            </div>
            <div class="search-field">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <select id="fStatus" name="status" aria-label="Filter by status">
                    <option value="">All statuses</option>
                    <option value="confirmed" <?php echo $f_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="pending" <?php echo $f_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
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
                <?php if ($f_search !== '' || $f_status !== '' || $f_payment !== ''): ?>
                    <a href="<?php echo base_url('admin/bookings.php'); ?>" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div id="bookingsResults">
    <?php if (!$bookingsAll): ?>
        <?php empty_state('fa-regular fa-calendar-xmark', 'No bookings yet', 'Once players start reserving, everything lands here.'); ?>
    <?php else: ?>
        <div class="view-tabs reveal">
            <a href="<?php echo ab_url(['view' => '']); ?>" class="view-tab <?php echo $view === 'all' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">All (<?php echo count($rows); ?>)</a>
            <a href="<?php echo ab_url(['view' => 'upcoming']); ?>" class="view-tab <?php echo $view === 'upcoming' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">Upcoming (<?php echo count($upcoming); ?>)</a>
            <a href="<?php echo ab_url(['view' => 'unpaid']); ?>" class="view-tab <?php echo $view === 'unpaid' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">Unpaid (<?php echo count($unpaid); ?>)</a>
            <a href="<?php echo ab_url(['view' => 'past']); ?>" class="view-tab <?php echo $view === 'past' ? 'active' : ''; ?>" data-ajax-link="bookingsResults">Past (<?php echo count($past); ?>)</a>
        </div>

        <?php if (!$showRows): ?>
            <?php if ($view === 'unpaid'): ?>
                <?php empty_state('fa-solid fa-circle-check', "You're all paid up", '', ab_url(['view' => '']), 'View all bookings'); ?>
            <?php elseif ($view === 'upcoming'): ?>
                <?php empty_state('fa-regular fa-calendar-xmark', 'Nothing upcoming', ''); ?>
            <?php else: ?>
                <?php empty_state('fa-regular fa-calendar-xmark', 'No past bookings yet', ''); ?>
            <?php endif; ?>
        <?php else: ?>
            <?php bookings_table_html($bookings, ['show_player' => true, 'show_ref' => true, 'drawer' => true]); ?>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Bookings pages" data-ajax-link="bookingsResults">
                <?php if ($page > 1): ?>
                    <a class="page-link" href="<?php echo ab_url(['page' => $page - 1]); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo ab_url(['page' => $i]); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="page-link" href="<?php echo ab_url(['page' => $page + 1]); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>