<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['id'] ?? ($_GET['booking_id'] ?? 0));
if ($booking_id <= 0) {
    redirect('pages/my_bookings.php');
}

$stmt = $conn->prepare(
    'SELECT b.*, g.name AS ground_name, g.location, g.address, g.court_number, g.capacity, g.image,
            u.name AS user_name, u.email AS user_email, u.phone AS user_phone
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     WHERE b.id = ?'
);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    http_error_page(404, 'Booking not found', 'We could not find the booking confirmation you were looking for.', 'View Bookings', 'pages/my_bookings.php');
}

// Ownership / authorization verification
$isOwner = (int)$b['user_id'] === (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$isManager = ($_SESSION['role'] ?? '') === 'manager';

if (!$isOwner && !$isAdmin && !$isManager) {
    http_error_page(403, 'Access denied', 'You do not have permission to view this booking confirmation.');
}

$netTotal = round((float)$b['total_price'] - (float)$b['discount'], 2);
$isPaid = ($b['payment_status'] === 'paid');
$isPartial = ($b['payment_status'] === 'partial');
$isAtCourt = ($b['payment_method'] === 'at_court');
$isQr = ($b['payment_method'] === 'qr');

$refCode = !empty($b['booking_ref']) ? $b['booking_ref'] : ('GS-' . str_pad($b['id'], 6, '0', STR_PAD_LEFT));
$courtName = !empty($b['court_number']) ? $b['court_number'] : 'Pitch 1';
$durationHours = max(1, round((strtotime($b['end_time']) - strtotime($b['start_time'])) / 3600, 1));
$matchUrl = absolute_url('pages/match.php?' . (!empty($b['booking_ref']) ? ('ref=' . urlencode($b['booking_ref'])) : ('id=' . (int)$b['id'])));

$page_title = 'Booking Confirmed · ' . $b['ground_name'];
$page_description = 'Your futsal booking at ' . $b['ground_name'] . ' is confirmed. Review your match details and match pass on GoalSpace.';
require __DIR__ . '/../includes/header.php';
?>

<div class="container ty-container reveal">
    <div class="ty-card">
        <div class="ty-header">
            <div class="ty-icon-circle">
                <i class="fa-solid fa-check"></i>
            </div>
            <span class="ty-kicker">Booking Confirmed</span>
            <h1 class="ty-title">Thank You, <?php echo e(explode(' ', trim($b['user_name']))[0]); ?>!</h1>
            <p class="ty-lead">Your kickoff slot at <strong><?php echo e($b['ground_name']); ?></strong> is locked in. We've notified the venue team.</p>
        </div>

        <div class="ty-summary-box">
            <div class="ty-summary-head">
                <div class="ty-ref-wrap">
                    <span class="ty-ref-label">Booking Reference</span>
                    <strong class="ty-ref-value"><?php echo e($refCode); ?></strong>
                </div>
                <button type="button" class="btn btn-ghost btn-xs ty-copy-btn" id="tyCopyRefBtn" data-ref="<?php echo e($refCode); ?>" aria-label="Copy booking reference">
                    <i class="fa-regular fa-copy"></i> <span>Copy</span>
                </button>
            </div>

            <div class="ty-grid">
                <div class="ty-grid-item">
                    <span class="ty-label"><i class="fa-solid fa-futbol"></i> Venue &amp; Pitch</span>
                    <span class="ty-val"><strong><?php echo e($b['ground_name']); ?></strong></span>
                    <span class="ty-sub"><?php echo e($courtName); ?> &middot; <?php echo e($b['location']); ?></span>
                </div>

                <div class="ty-grid-item">
                    <span class="ty-label"><i class="fa-regular fa-calendar"></i> Date &amp; Kickoff</span>
                    <span class="ty-val"><strong><?php echo date('l, M j, Y', strtotime($b['booking_date'])); ?></strong></span>
                    <span class="ty-sub"><?php echo substr($b['start_time'], 0, 5); ?> &ndash; <?php echo substr($b['end_time'], 0, 5); ?> (<?php echo $durationHours; ?> hr)</span>
                </div>

                <div class="ty-grid-item">
                    <span class="ty-label"><i class="fa-solid fa-credit-card"></i> Payment Status</span>
                    <?php if ($isPaid): ?>
                        <span class="ty-val"><span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Paid in Full</span></span>
                        <span class="ty-sub">Rs <?php echo number_format($netTotal, 0); ?> settled</span>
                    <?php elseif ($isPartial): ?>
                        <span class="ty-val"><span class="badge badge-info"><i class="fa-solid fa-clock"></i> 20% Advance Paid</span></span>
                        <span class="ty-sub">Rs <?php echo number_format((float)$b['amount_paid'], 0); ?> paid &middot; Rs <?php echo number_format(max(0, $netTotal - (float)$b['amount_paid']), 0); ?> due at court</span>
                    <?php elseif ($isQr): ?>
                        <span class="ty-val"><span class="badge badge-warning"><i class="fa-solid fa-qrcode"></i> Verification Pending</span></span>
                        <span class="ty-sub">Court will confirm QR transfer receipt shortly</span>
                    <?php else: ?>
                        <span class="ty-val"><span class="badge badge-info"><i class="fa-solid fa-hand-holding-dollar"></i> Pay at Court</span></span>
                        <span class="ty-sub">Rs <?php echo number_format($netTotal, 0); ?> payable at the counter</span>
                    <?php endif; ?>
                </div>

                <div class="ty-grid-item">
                    <span class="ty-label"><i class="fa-solid fa-receipt"></i> Total Amount</span>
                    <span class="ty-val ty-price">Rs <?php echo number_format($netTotal, 0); ?></span>
                    <?php if ((float)$b['discount'] > 0): ?>
                        <span class="ty-sub text-success"><i class="fa-solid fa-tag"></i> Includes Rs <?php echo number_format((float)$b['discount'], 0); ?> discount</span>
                    <?php else: ?>
                        <span class="ty-sub">Standard court rate</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ty-actions">
            <a href="<?php echo base_url('pages/booking_details.php?id=' . (int)$b['id']); ?>" class="btn btn-primary btn-lg ty-btn-primary">
                <i class="fa-solid fa-ticket"></i> View Match Pass &amp; QR
            </a>
            <div class="ty-secondary-actions">
                <a href="<?php echo base_url('pages/booking_ics.php?id=' . (int)$b['id']); ?>" class="btn btn-outline btn-sm">
                    <i class="fa-regular fa-calendar-plus"></i> Add to Calendar
                </a>
                <button type="button" class="btn btn-outline btn-sm" id="tyShareMatchBtn" data-url="<?php echo e($matchUrl); ?>">
                    <i class="fa-solid fa-share-nodes"></i> Share with Team
                </button>
            </div>
        </div>

        <div class="ty-tips">
            <div class="ty-tip">
                <i class="fa-solid fa-stopwatch ty-tip-ico"></i>
                <div class="ty-tip-text">
                    <strong>Arrive 10 minutes early</strong>
                    <span>Allows time for warm-ups, gear checks, and smooth pitch handoff.</span>
                </div>
            </div>
            <div class="ty-tip">
                <i class="fa-solid fa-shoe-prints ty-tip-ico"></i>
                <div class="ty-tip-text">
                    <strong>Proper footwear required</strong>
                    <span>Rubberized futsal boots or flat turf soles. Metal studs are strictly forbidden.</span>
                </div>
            </div>
        </div>

        <div class="ty-foot">
            <a href="<?php echo base_url('pages/courts.php'); ?>" class="ty-foot-link">
                <i class="fa-solid fa-arrow-left"></i> Book another court
            </a>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="ty-foot-link">
                My Bookings <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var copyBtn = document.getElementById('tyCopyRefBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var ref = this.getAttribute('data-ref');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(ref).then(function() {
                    copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                    setTimeout(function() {
                        copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i> <span>Copy</span>';
                    }, 2000);
                });
            }
        });
    }

    var shareBtn = document.getElementById('tyShareMatchBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            var url = this.getAttribute('data-url');
            if (navigator.share) {
                navigator.share({
                    title: 'Futsal Match at <?php echo e($b['ground_name']); ?>',
                    text: 'Join our futsal match at <?php echo e($b['ground_name']); ?> on <?php echo date('M j', strtotime($b['booking_date'])); ?>!',
                    url: url
                }).catch(function() {});
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function() {
                    shareBtn.innerHTML = '<i class="fa-solid fa-check"></i> Link Copied!';
                    setTimeout(function() {
                        shareBtn.innerHTML = '<i class="fa-solid fa-share-nodes"></i> Share with Team';
                    }, 2000);
                });
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
