<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

if (isset($_GET['cancel'])) {
    if (!isset($_GET['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'])) {
        exit('Invalid request.');
    }
    $id = (int)$_GET['cancel'];
    $stmt = $conn->prepare('SELECT user_id, ground_id, booking_date, start_time FROM bookings WHERE id = ? AND status != "cancelled"');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cancelTarget = $stmt->get_result()->fetch_assoc();

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
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.created_at,
            g.name AS ground_name, u.name AS user_name, u.email AS user_email,
            m.name AS manager_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     LEFT JOIN users m ON m.id = g.manager_id
     ORDER BY b.booking_date DESC, b.start_time ASC'
)->fetch_all(MYSQLI_ASSOC);

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalRows = count($bookingsAll);
$totalPages = (int)ceil($totalRows / $perPage);
$bookings = array_slice($bookingsAll, $offset, $perPage);

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $csv = [['Reference', 'Ground', 'Manager', 'Customer', 'Customer Email', 'Date', 'Start', 'End', 'Total (Rs)', 'Status', 'Payment', 'Paid (Rs)', 'Booked At']];
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
            number_format((float)$b['amount_paid'], 2),
            $b['created_at'],
        ];
    }
    if (isset($_GET['export_excel'])) {
        export_excel($csv, 'all-bookings.xlsx');
    }
    export_csv($csv, 'all-bookings.csv');
}

$page_title = 'Manage Bookings';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head dash-page-head">
    <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="nav-back mob-back" aria-label="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
    <div class="dash-head-main">
        <h2>All Bookings</h2>
        <div class="actions">
            <a href="<?php echo base_url('admin/bookings.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
            <a href="<?php echo base_url('admin/bookings.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        </div>
    </div>
</div>

<div class="table-toolbar reveal">
    <div class="search-pill">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="adminSearch" placeholder="Search bookings by ground, player, ref…" autocomplete="off">
        <i class="fa-regular fa-circle-xmark" id="adminClear" role="button" aria-label="Clear search"></i>
    </div>
</div>

<?php if (!$bookings): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-calendar-xmark"></i></span><h3>No bookings yet</h3><p>Once players start reserving, everything lands here.</p></div>
<?php else: ?>
    <div class="mbookings reveal">
        <?php foreach ($bookings as $b): ?>
            <?php booking_card_mini($b, 'user', $b['ground_name'] . ' ' . $b['user_name'] . ' ' . substr($b['start_time'], 0, 5) . ' ' . $b['booking_ref'] . ' ' . $b['status'] . ' ' . $b['payment_status']); ?>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Bookings pages">
            <?php if ($page > 1): ?>
                <a class="page-link" href="<?php echo base_url('admin/bookings.php?page=' . ($page - 1)); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo base_url('admin/bookings.php?page=' . $i); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="page-link" href="<?php echo base_url('admin/bookings.php?page=' . ($page + 1)); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>

