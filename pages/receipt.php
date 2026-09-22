<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.payment_type, b.amount_paid, b.paid_at, b.discount, b.promo_code, b.created_at,
            g.name AS ground_name, g.location, g.price_per_hour, g.slug AS slug, u.name AS owner_name
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     LEFT JOIN users u ON u.id = g.manager_id
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

$page_title = 'Payment receipt';
require __DIR__ . '/../includes/header.php';
?>

<div class="receipt-wrap reveal">
    <div class="receipt-card match-pass-card">
        <div class="match-pass-top">
            <span class="match-pass-kicker"><i class="fa-solid fa-futbol"></i> OFFICIAL MATCH PASS &amp; RECEIPT</span>
            <h1><?php echo e($booking['ground_name']); ?></h1>
            <p class="match-pass-ref">Reference: <strong><?php echo e($booking['booking_ref']); ?></strong></p>
        </div>

        <div class="match-pass-schedule">
            <div class="mps-col">
                <span class="mps-label">Match Date</span>
                <strong class="mps-value"><?php echo e(date('l, M j, Y', strtotime($booking['booking_date']))); ?></strong>
            </div>
            <div class="mps-divider"></div>
            <div class="mps-col">
                <span class="mps-label">Kickoff Time</span>
                <strong class="mps-value"><?php echo e(substr($booking['start_time'], 0, 5)); ?> &ndash; <?php echo e(substr($booking['end_time'], 0, 5)); ?></strong>
            </div>
            <div class="mps-divider"></div>
            <div class="mps-col">
                <span class="mps-label">Payment Status</span>
                <strong class="mps-value">
                    <?php if ($booking['payment_status'] === 'paid'): ?>
                        <span class="status-pill status-pill--paid"><i class="fa-solid fa-circle-check"></i> Paid in Full</span>
                    <?php elseif ($booking['payment_status'] === 'partial'): ?>
                        <span class="status-pill status-pill--partial"><i class="fa-solid fa-coins"></i> 20% Advance Paid</span>
                    <?php else: ?>
                        <span class="status-pill status-pill--unpaid"><i class="fa-solid fa-clock"></i> Pay at Court</span>
                    <?php endif; ?>
                </strong>
            </div>
        </div>

        <div class="receipt-box">
            <div class="receipt-row">
                <span>Location</span>
                <strong><i class="fa-solid fa-location-dot"></i> <?php echo e($booking['location']); ?></strong>
            </div>
            <?php $ownerLabel = ground_owner_label($booking); if ($ownerLabel !== ''): ?>
                <div class="receipt-row">
                    <span>Venue Management</span>
                    <strong><?php echo e($ownerLabel); ?></strong>
                </div>
            <?php endif; ?>
            <div class="receipt-row">
                <span>Booking Total</span>
                <strong>Rs <?php echo number_format((float)$booking['total_price'], 2); ?></strong>
            </div>
            <?php if ((float)$booking['discount'] > 0): ?>
                <div class="receipt-row">
                    <span>Promo Applied (<?php echo e($booking['promo_code']); ?>)</span>
                    <strong class="ok-text">&minus; Rs <?php echo number_format((float)$booking['discount'], 2); ?></strong>
                </div>
            <?php endif; ?>
            <div class="receipt-row receipt-grand">
                <span><?php echo $booking['payment_status'] === 'paid' ? 'Total Settled' : 'Balance Due at Kickoff'; ?></span>
                <strong class="text-brand"><?php echo $booking['payment_status'] === 'paid'
                    ? 'Rs ' . number_format((float)$booking['total_price'] - (float)$booking['discount'], 2)
                    : 'Rs ' . number_format((float)$booking['total_price'] - (float)$booking['discount'] - (float)$booking['amount_paid'], 2); ?></strong>
            </div>
            <?php if ($booking['paid_at']): ?>
                <div class="receipt-row receipt-subtle">
                    <span>Payment timestamp</span>
                    <small><?php echo e(date('M j, Y \a\t g:i A', strtotime($booking['paid_at']))); ?></small>
                </div>
            <?php endif; ?>
        </div>

        <div class="receipt-actions">
            <a href="<?php echo base_url('pages/receipt_pdf.php?booking_id=' . (int)$booking['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> Download PDF Pass</a>
            <button type="button" class="btn btn-outline" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-ghost">My Bookings</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
