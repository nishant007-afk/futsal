<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.payment_type, b.amount_paid, b.discount, b.promo_code, b.created_at,
            g.name AS ground_name, g.location, u.email AS user_email
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     LEFT JOIN users u ON u.id = b.user_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status'] === 'cancelled') {
    set_flash_error(
        'We couldn\'t find that booking.',
        'It may have been cancelled, or the link may be out of date.',
        'Open the booking from My Bookings to see its current status.',
        'pages/my_bookings.php'
    );
    redirect('pages/my_bookings.php');
}

$total = (float)$booking['total_price'];
$discount = (float)$booking['discount'];
$netDue = max(0, $total - $discount);
$hasDiscount = $discount > 0;
$amountPaid = (float)$booking['amount_paid'];

if (!empty($booking['payment_type'])) {
    $methodText = $booking['payment_type'] === 'full' ? 'Full' : 'Half';
} else {
    $methodText = $booking['payment_status'] === 'paid' ? 'Full' : 'Unpaid';
}
if ($booking['payment_status'] === 'paid') {
    if ($hasDiscount) {
        $amountCell = '<span class="price-orig">Rs ' . number_format($total, 0) . '</span><span class="pay-net"> Rs ' . number_format($netDue, 0) . '</span>';
    } else {
        $amountCell = '<span class="pay-net">Rs ' . number_format($netDue, 0) . '</span>';
    }
} elseif ($booking['payment_status'] === 'partial') {
    $amountCell = '<span class="pay-net">Rs ' . number_format($amountPaid, 0) . '</span> <span class="pay-subtext">Rs ' . number_format(max(0, $netDue - $amountPaid), 0) . ' due at court</span>';
} else {
    $amountCell = '<span class="muted">Not yet paid &middot; pay via QR or at the court</span>';
}
$bookingDate = date('M j, Y', strtotime($booking['booking_date']));

$page_title = 'Booking confirmed';
$page_description = 'Your futsal court booking on GoalSpace is confirmed. Review your slot details and get ready for the game.';
require __DIR__ . '/../includes/header.php';
?>

<div class="confirm-wrap reveal">
    <div class="confirm-card">
        <div class="confirm-check"><i class="fa-solid fa-circle-check"></i></div>
        <div class="title-back-row confirm-title-row">
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="nav-back mob-title-back confirm-back" aria-label="Back to my bookings"><i class="fa-solid fa-arrow-left"></i></a>
            <h1>Payment completed</h1>
        </div>
        <div class="confirm-ref">Booking reference: <strong><?php echo e($booking['booking_ref']); ?></strong></div>

        <table class="confirm-table">
            <tr><th>Date</th><td><?php echo e($bookingDate); ?> &middot; <?php echo e(substr($booking['start_time'], 0, 5)); ?> - <?php echo e(substr($booking['end_time'], 0, 5)); ?></td></tr>
            <tr><th>Name</th><td><?php echo e($booking['ground_name']); ?>, <?php echo e($booking['location']); ?></td></tr>
            <tr><th>Payment method</th><td><?php echo e($methodText); ?></td></tr>
            <tr><th>Amount</th><td><?php echo $amountCell; ?></td></tr>
            <tr><th>Email</th><td><?php echo e($booking['user_email'] ?? ''); ?></td></tr>
        </table>

        <div class="confirm-actions">
            <a href="<?php echo base_url('pages/receipt_pdf.php?booking_id=' . (int)$booking['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> Download invoice</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
