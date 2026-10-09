<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.payment_type, b.amount_paid, b.paid_at, b.discount, b.promo_code, b.created_at,
            b.ground_id,
            g.name AS ground_name, g.location, g.address, g.price_per_hour, g.slug AS slug, u.name AS owner_name
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

$page_title = 'Payment Receipt · ' . (!empty($booking['booking_ref']) ? $booking['booking_ref'] : ('#' . $booking['id']));
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
                    <span>Managed by</span>
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
                    ? 'Rs ' . number_format(max(0, (float)$booking['total_price'] - (float)$booking['discount']), 2)
                    : 'Rs ' . number_format(max(0, (float)$booking['total_price'] - (float)$booking['discount'] - (float)$booking['amount_paid']), 2); ?></strong>
            </div>
            <?php if ($booking['paid_at']): ?>
                <div class="receipt-row receipt-subtle">
                    <span>Payment timestamp</span>
                    <small><?php echo e(date('M j, Y \a\t g:i A', strtotime($booking['paid_at']))); ?></small>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($booking['status'] !== 'cancelled'):
            $matchUrl = absolute_url('pages/match.php?' . (!empty($booking['booking_ref']) ? ('ref=' . urlencode($booking['booking_ref'])) : ('id=' . (int)$booking['id'])));
            $destQuery = !empty($booking['address']) ? ($booking['ground_name'] . ', ' . $booking['address']) : ($booking['ground_name'] . ', ' . $booking['location']);
            $mapsDirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destQuery);
            $shareText = "⚽ Futsal Match Invitation!\n"
                . "🏟️ " . $booking['ground_name'] . "\n"
                . "📅 " . date('D, M j, Y', strtotime($booking['booking_date'])) . "\n"
                . "⏰ " . substr($booking['start_time'], 0, 5) . " - " . substr($booking['end_time'], 0, 5) . "\n"
                . "📍 " . $booking['location'] . (!empty($booking['address']) ? " (" . $booking['address'] . ")" : "") . "\n"
                . "🗺️ Directions: " . $mapsDirUrl . "\n"
                . "🔗 Match Pass: " . $matchUrl . "\n\n"
                . "See you on the pitch!";
            $whatsappUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($shareText);
            $viberUrl = 'viber://forward?text=' . rawurlencode($shareText);
            $netPrice = max(0, (float)$booking['total_price'] - (float)($booking['discount'] ?? 0));
            $split10 = (int)ceil($netPrice / 10);
            $split12 = (int)ceil($netPrice / 12);
        ?>
        <div class="receipt-share-box">
            <h4><i class="fa-solid fa-users"></i> Invite Teammates</h4>
            <div class="rsb-actions">
                <a href="<?php echo e($whatsappUrl); ?>" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
                    <i class="fa-brands fa-whatsapp"></i> WhatsApp
                </a>
                <a href="<?php echo e($viberUrl); ?>" class="btn btn-viber btn-sm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-2px"><path d="M19.78 4.22A11.94 11.94 0 0 0 11.96 0C5.35 0 0 5.35 0 11.96c0 2.21.6 4.36 1.74 6.24L.13 23.32a.75.75 0 0 0 .93.93l5.22-1.63A11.92 11.92 0 0 0 11.96 24c6.61 0 11.96-5.35 11.96-11.96 0-3.2-1.25-6.2-3.51-8.47zM12 21.6c-1.87 0-3.7-.52-5.28-1.5a.75.75 0 0 0-.58-.08l-3.8 1.18 1.2-3.72a.75.75 0 0 0-.08-.6A9.56 9.56 0 0 1 2.4 12c0-5.3 4.3-9.6 9.6-9.6s9.6 4.3 9.6 9.6-4.3 9.6-9.6 9.6zm5.1-6.8l-1.8-.8a1.2 1.2 0 0 0-1.4.3l-.6.7a8.6 8.6 0 0 1-3.6-3.6l.7-.6a1.2 1.2 0 0 0 .3-1.4l-.8-1.8a1.2 1.2 0 0 0-1.4-.7c-.8.2-1.6.9-1.8 1.7-.4 1.8.4 4.5 3.3 7.4s5.6 3.7 7.4 3.3c.8-.2 1.5-1 1.7-1.8a1.2 1.2 0 0 0-.7-1.4z"/></svg>
                    Viber
                </a>
                <button type="button" class="btn btn-outline btn-sm" id="copyReceiptInviteBtn" onclick="copyReceiptInvite()">
                    <i class="fa-solid fa-copy"></i> <span id="copyReceiptInviteLabel">Copy Info</span>
                </button>
                <a href="<?php echo e($matchUrl); ?>" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Match Pass
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="receipt-actions">
            <a href="<?php echo base_url('pages/receipt_pdf.php?booking_id=' . (int)$booking['id']); ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> Download PDF Pass</a>
            <button type="button" class="btn btn-outline" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            <a href="<?php echo base_url('pages/my_bookings.php'); ?>" class="btn btn-ghost">My Bookings</a>
            <?php if ($booking['user_id'] == $_SESSION['user_id'] && $booking['status'] === 'completed'): ?>
                <a href="<?php echo base_url('pages/ground.php?id=' . $booking['ground_id']); ?>" class="btn btn-success"><i class="fa-solid fa-star"></i> Rate This Court</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyReceiptInvite() {
    var text = <?php echo isset($shareText) ? json_encode($shareText, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) : "''"; ?>;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onCopied).catch(fallback);
    } else {
        fallback();
    }
    function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); onCopied(); } catch(e) {}
        document.body.removeChild(ta);
    }
    function onCopied() {
        var label = document.getElementById('copyReceiptInviteLabel');
        var btn = document.getElementById('copyReceiptInviteBtn');
        if (label) label.textContent = 'Copied! ✓';
        if (btn) btn.classList.add('btn-success-temporary');
        setTimeout(function() {
            if (label) label.textContent = 'Copy Info';
            if (btn) btn.classList.remove('btn-success-temporary');
        }, 2200);
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
