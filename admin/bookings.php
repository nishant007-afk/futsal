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

$bookings = $conn->query(
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

if (isset($_GET['export']) || isset($_GET['export_excel'])) {
    $csv = [['Reference', 'Ground', 'Manager', 'Customer', 'Customer Email', 'Date', 'Start', 'End', 'Total (Rs)', 'Status', 'Payment', 'Paid (Rs)', 'Booked At']];
    foreach ($bookings as $b) {
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

<div class="page-head">
    <h2><i class="fa-solid fa-list-check"></i> All Bookings</h2>
    <div class="actions">
        <a href="<?php echo base_url('admin/bookings.php?export_excel=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</a>
        <a href="<?php echo base_url('admin/bookings.php?export=1'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
        <a href="<?php echo base_url('admin/dashboard.php'); ?>" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
    </div>
</div>
<p class="muted" style="margin-bottom:8px;">Every booking across the platform, newest first.</p>

<?php if (!$bookings): ?>
    <div class="empty reveal"><span class="big"><i class="fa-regular fa-calendar-xmark"></i></span><h3>No bookings yet</h3><p>Once players start reserving, everything lands here.</p></div>
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
                        <span><i class="fa-solid fa-tag"></i> <?php echo format_price($b['total_price']); ?></span>
                        <span><i class="fa-solid fa-user"></i> <?php echo e($b['user_name']); ?></span>
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

