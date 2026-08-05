<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.payment_type, b.amount_paid, b.paid_at, b.discount, b.promo_code, b.created_at,
            g.name AS ground_name, g.location, g.price_per_hour, u.name AS owner_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     LEFT JOIN users u ON u.id = g.manager_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status'] === 'cancelled') {
    set_flash('error', 'Booking not found.');
    redirect('pages/my_bookings.php');
}

$page_title = 'Payment receipt';
require __DIR__ . '/../includes/header.php';
?>

<div class="receipt-wrap reveal">
    <div class="receipt-card">
        <div class="receipt-brand">
            <span class="brand-mark"><i class="fa-solid fa-futbol"></i></span>
        </div>
        <div class="receipt-meta">
            <span class="eyebrow">Payment receipt</span>
            <h1>Receipt of payment</h1>
            <p class="muted">Show this to the court manager when you arrive.</p>
        </div>

        <div class="receipt-box">
            <div class="receipt-row">
                <span>Booking reference</span>
                <strong><?php echo e($booking['booking_ref']); ?></strong>
            </div>
            <div class="receipt-row">
                <span>Court</span>
                <strong><?php echo e($booking['ground_name']); ?></strong>
            </div>
            <?php if ($booking['owner_name']): ?>
                <div class="receipt-row">
                    <span>Managed by</span>
                    <strong><?php echo e($booking['owner_name']); ?></strong>
                </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span>Location</span>
                <strong><?php echo e($booking['location']); ?></strong>
            </div>
            <div class="receipt-row">
                <span>Booking date &amp; time</span>
                <strong><?php echo e(date('l, M j, Y', strtotime($booking['booking_date']))); ?><br><?php echo e(substr($booking['start_time'], 0, 5)); ?> - <?php echo e(substr($booking['end_time'], 0, 5)); ?></strong>
            </div>
            <div class="receipt-row">
                <span>Payment received</span>
                <strong>
                    <?php if ($booking['payment_status'] === 'paid'): ?>
                        <span class="ok-text"><i class="fa-solid fa-circle-check"></i> Rs <?php echo number_format((float)$booking['total_price'] - (float)$booking['discount'], 0); ?> &middot; Paid in full</span>
                    <?php elseif ($booking['payment_status'] === 'partial'): ?>
                        <span class="warn-text"><i class="fa-solid fa-coins"></i> Rs <?php echo number_format((float)$booking['amount_paid'], 0); ?> &middot; Advance</span>
                    <?php else: ?>
                        <span class="muted"><i class="fa-solid fa-clock"></i> Not yet paid</span>
                    <?php endif; ?>
                </strong>
            </div>
            <?php if ($booking['paid_at']): ?>
                <div class="receipt-row">
                    <span>Received on</span>
                    <strong><?php echo e(date('M j, Y \a\t g:i A', strtotime($booking['paid_at']))); ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <div class="receipt-total">
            <div class="receipt-row">
                <span>Booking total</span>
                <strong>Rs <?php echo number_format((float)$booking['total_price'], 2); ?></strong>
            </div>
            <?php if ((float)$booking['discount'] > 0): ?>
                <div class="receipt-row">
                    <span>Promo <em><?php echo e($booking['promo_code']); ?></em></span>
                    <strong class="ok-text">&minus; Rs <?php echo number_format((float)$booking['discount'], 2); ?></strong>
                </div>
            <?php endif; ?>
            <div class="receipt-row receipt-grand">
                <span><?php echo $booking['payment_status'] === 'paid' ? 'Amount paid' : 'Amount due'; ?></span>
                <strong><?php echo $booking['payment_status'] === 'paid'
                    ? 'Rs ' . number_format((float)$booking['total_price'] - (float)$booking['discount'], 2)
                    : 'Rs ' . number_format((float)$booking['total_price'] - (float)$booking['discount'] - (float)$booking['amount_paid'], 2); ?></strong>
            </div>
        </div>

        <div class="receipt-actions">
            <button type="button" class="btn btn-outline" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <a href="<?php echo base_url('pages/receipt_pdf.php?booking_id=' . (int)$booking['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> Download PDF</a>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-ghost">Back to bookings</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
