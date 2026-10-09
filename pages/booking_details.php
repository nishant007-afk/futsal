<?php
require_once __DIR__ . '/../config/db.php';

if (!is_logged_in()) {
    redirect('pages/login.php');
}

$me = current_user();
$booking_id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.ground_id, b.user_id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.payment_type, b.amount_paid, b.payment_method, b.discount, b.promo_code, b.created_at, b.repeat_weeks,
            g.name AS ground_name, g.location, g.address, g.court_number, g.manager_id, g.capacity,
            g.price_per_hour, g.discount_price, g.price_weekend,
            u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
            m.name AS manager_name, m.phone AS manager_phone
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
     JOIN users u ON u.id = b.user_id
     LEFT JOIN users m ON m.id = g.manager_id
     WHERE b.id = ?'
);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

$allowed = false;
if ($b) {
    if ($me['role'] === 'admin') {
        $allowed = true;
    } elseif ($me['role'] === 'manager') {
        $allowed = (int)$b['manager_id'] === (int)$me['id'];
    } else {
        $allowed = (int)$b['user_id'] === (int)$me['id'];
    }
}

/* Where "back" goes for this viewer. */
$backUrl = $me['role'] === 'admin' ? 'admin/bookings.php'
    : ($me['role'] === 'manager' ? 'manager/bookings.php' : 'pages/my_bookings.php');
$backLabel = $me['role'] === 'user' ? 'Back to My Bookings' : 'Back to Bookings';

if (!$b || !$allowed) {
    http_error_page(404, 'Booking not found', 'We couldn\'t find that booking. It may have been cancelled, or you may not have access to it.', 'Back to bookings', $backUrl);
}

$policy = booking_refund_policy($b['booking_date'], $b['start_time'], (float)$b['amount_paid']);
$netDue = (float)$b['total_price'] - (float)$b['discount'];
$balance = max(0, $netDue - (float)$b['amount_paid']);

// Duration calculation
$startTs = strtotime($b['start_time']);
$endTs = strtotime($b['end_time']);
if ($endTs <= $startTs) {
    $endTs += 86400; // Midnight crossing
}
$durationHours = max(1, round(($endTs - $startTs) / 3600, 1));

// Futsal specific attributes
$courtName = !empty($b['court_number']) ? $b['court_number'] : 'Pitch 1';
$capacity = (int)($b['capacity'] ?? 10);
$courtFormat = ($capacity >= 14) ? '7-A-Side' : (($capacity >= 12) ? '6-A-Side' : '5-A-Side');

$isPaid = ($b['payment_status'] === 'paid');
$isPartial = ($b['payment_status'] === 'partial');
$isUnpaid = ($b['payment_status'] === 'unpaid');

// Single Non-Contradictory Status Badge
if ($b['status'] === 'cancelled') {
    $singleStatusClass = 'status-cancelled';
    $singleStatusLabel = 'Cancelled';
} elseif ($isPaid) {
    $singleStatusClass = 'status-paid';
    $singleStatusLabel = 'Paid in Full';
} elseif ($isPartial) {
    $singleStatusClass = 'status-held';
    $singleStatusLabel = 'Awaiting Balance';
} else {
    $singleStatusClass = 'status-unpaid';
    $singleStatusLabel = 'Awaiting Payment';
}

// URLs and clean zero-emoji sharing text
$bookingRefCode = !empty($b['booking_ref']) ? $b['booking_ref'] : ('GS-' . str_pad($b['id'], 6, '0', STR_PAD_LEFT));
$matchUrl = absolute_url('pages/match.php?' . (!empty($b['booking_ref']) ? ('ref=' . urlencode($b['booking_ref'])) : ('id=' . (int)$b['id'])));
$destQuery = !empty($b['address']) ? ($b['ground_name'] . ', ' . $b['address']) : ($b['ground_name'] . ', ' . $b['location']);
$mapsDirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destQuery);

$shareText = "Futsal Match Invitation\n"
    . "Venue: " . $b['ground_name'] . " (" . $courtName . " - " . $courtFormat . ")\n"
    . "Date: " . date('D, M j, Y', strtotime($b['booking_date'])) . "\n"
    . "Time: " . substr($b['start_time'], 0, 5) . " - " . substr($b['end_time'], 0, 5) . "\n"
    . "Location: " . $b['location'] . (!empty($b['address']) ? " (" . $b['address'] . ")" : "") . "\n"
    . "Directions: " . $mapsDirUrl . "\n"
    . "Match Pass: " . $matchUrl . "\n\n"
    . "See you on the pitch.";

$whatsappUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($shareText);
$viberUrl = 'viber://forward?text=' . rawurlencode($shareText);

$page_title = 'Booking Details · ' . $b['ground_name'];
$page_description = 'Review full match schedule, pitch details, and payment summary for your booking at ' . $b['ground_name'] . '.';
require __DIR__ . '/../includes/header.php';
?>

<div class="bd-wrap reveal">

    <!-- 2. PAGE HEADER — title + status, meta, ghost utilities -->
    <header class="bd-ticket-header">
        <div class="bd-title-row">
            <a href="<?php echo base_url($backUrl); ?>" class="page-back-arrow" data-back aria-label="<?php echo e($backLabel); ?>"><i class="fa-solid fa-arrow-left"></i></a>
            <div class="bd-title-main">
                <div class="bd-title-line">
                    <h1 class="bd-venue-title"><?php echo e($b['ground_name']); ?></h1>
                    <span class="bd-status-badge <?php echo $singleStatusClass; ?>">
                        <span><?php echo $singleStatusLabel; ?></span>
                    </span>
                </div>

                <p class="bd-venue-subtitle">
                    <span class="bd-venue-loc"><?php echo e($b['address'] ?: $b['location']); ?></span>
                    <span class="bd-venue-court"><?php echo e($courtName); ?> (<?php echo e($courtFormat); ?>)</span>
                </p>
            </div>
        </div>

        <div class="bd-venue-actions">
            <a href="<?php echo e($mapsDirUrl); ?>" target="_blank" rel="noopener" class="bd-action-btn">
                <svg class="bd-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
                <span>Get Directions</span>
            </a>
            <?php if (!empty($b['manager_phone'])): ?>
                <a href="tel:<?php echo e($b['manager_phone']); ?>" class="bd-action-btn">
                    <svg class="bd-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span>Call Venue</span>
                </a>
            <?php else: ?>
                <a href="<?php echo base_url('pages/ground.php?id=' . (int)$b['ground_id']); ?>" class="bd-action-btn">
                    <svg class="bd-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>Venue Info</span>
                </a>
            <?php endif; ?>
            <a href="<?php echo base_url('pages/booking_ics.php?id=' . (int)$b['id']); ?>" class="bd-action-btn">
                <svg class="bd-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Add to Calendar</span>
            </a>
        </div>
    </header>

    <!-- MAIN TWO-COLUMN CONTAINER -->
    <div class="bd-main-grid">

        <!-- 3. LEFT COLUMN (Match Info & Team) -->
        <div class="bd-col-left">

            <!-- Match Schedule Card -->
            <section class="bd-card bd-card-schedule">
                <h2 class="bd-card-title">Match Schedule</h2>
                <div class="bd-subgrid">
                    <div class="bd-subbox">
                        <span class="bd-subbox-label">Date</span>
                        <span class="bd-subbox-value"><?php echo e(date('D, M j, Y', strtotime($b['booking_date']))); ?></span>
                    </div>
                    <div class="bd-subbox">
                        <span class="bd-subbox-label">Time &amp; Duration</span>
                        <span class="bd-subbox-value"><?php echo e(substr($b['start_time'], 0, 5)); ?> &ndash; <?php echo e(substr($b['end_time'], 0, 5)); ?> (<?php echo $durationHours; ?>h)</span>
                    </div>
                    <div class="bd-subbox">
                        <span class="bd-subbox-label">Pitch Assigned</span>
                        <span class="bd-subbox-value"><?php echo e($courtName); ?> &bull; <?php echo e($courtFormat); ?></span>
                    </div>
                    <div class="bd-subbox">
                        <span class="bd-subbox-label">Booking Reference</span>
                        <span class="bd-subbox-value font-mono"><?php echo e($bookingRefCode); ?></span>
                    </div>
                </div>

                <?php if ($b['status'] !== 'cancelled'): ?>
                    <div class="bd-card-divider"></div>
                    <h3 class="bd-card-title">Invite Teammates</h3>
                    <div class="bd-link-copy-row">
                        <input type="text" readonly value="<?php echo e($matchUrl); ?>" id="bdInviteInput" class="bd-copy-field" onclick="this.select()">
                        <button type="button" class="bd-copy-btn" id="bdCopyBtn" onclick="copyInviteLink()">
                            <span id="bdCopyLabel">Copy Link</span>
                        </button>
                    </div>
                    <div class="bd-quick-share-row">
                        <a href="<?php echo e($whatsappUrl); ?>" target="_blank" rel="noopener" class="bd-share-btn-wa">
                            <svg class="bd-icon" viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M19.05 4.91A9.82 9.82 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.23 8.23zm4.52-6.16c-.25-.12-1.47-.72-1.7-.81-.23-.09-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.15.17-.25.25-.42.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.4-.42-.56-.43h-.48c-.16 0-.43.06-.66.31-.22.25-.87.85-.87 2.07s.89 2.4 1.01 2.57c.12.17 1.75 2.67 4.23 3.74.59.25 1.05.4 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.3z"/></svg>
                            <span>WhatsApp</span>
                        </a>
                        <a href="<?php echo e($viberUrl); ?>" class="bd-share-btn-vi">
                            <svg class="bd-icon" viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M19.78 4.22A11.94 11.94 0 0 0 11.96 0C5.35 0 0 5.35 0 11.96c0 2.21.6 4.36 1.74 6.24L.13 23.32a.75.75 0 0 0 .93.93l5.22-1.63A11.92 11.92 0 0 0 11.96 24c6.61 0 11.96-5.35 11.96-11.96 0-3.2-1.25-6.2-3.51-8.47zM12 21.6c-1.87 0-3.7-.52-5.28-1.5a.75.75 0 0 0-.58-.08l-3.8 1.18 1.2-3.72a.75.75 0 0 0-.08-.6A9.56 9.56 0 0 1 2.4 12c0-5.3 4.3-9.6 9.6-9.6s9.6 4.3 9.6 9.6-4.3 9.6-9.6 9.6zm5.1-6.8l-1.8-.8a1.2 1.2 0 0 0-1.4.3l-.6.7a8.6 8.6 0 0 1-3.6-3.6l.7-.6a1.2 1.2 0 0 0 .3-1.4l-.8-1.8a1.2 1.2 0 0 0-1.4-.7c-.8.2-1.6.9-1.8 1.7-.4 1.8.4 4.5 3.3 7.4s5.6 3.7 7.4 3.3c.8-.2 1.5-1 1.7-1.8a1.2 1.2 0 0 0-.7-1.4z"/></svg>
                            <span>Viber</span>
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Manager / Admin View -->
            <?php if ($me['role'] !== 'user'): ?>
                <section class="bd-card bd-card-admin">
                    <h2 class="bd-card-title">Customer Info</h2>
                    <div class="bd-subgrid">
                        <div class="bd-subbox">
                            <span class="bd-subbox-label">Player</span>
                            <span class="bd-subbox-value"><?php echo e($b['user_name']); ?></span>
                        </div>
                        <div class="bd-subbox">
                            <span class="bd-subbox-label">Phone</span>
                            <span class="bd-subbox-value"><?php echo e($b['user_phone'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                    <?php if ($me['role'] === 'manager' && $b['status'] !== 'cancelled' && $b['payment_status'] !== 'paid'): ?>
                        <div style="margin-top:12px;">
                            <?php echo post_action_form(base_url('manager/bookings.php'), 'mark_paid', (string)(int)$b['id'], 'Mark Paid at Venue', 'bd-btn-secondary', 'Mark this booking as paid (cash/QR verified at court)?', 'Mark paid', [], 'Mark as paid?'); ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        </div>

        <!-- 4. RIGHT COLUMN (Payment Sidebar - Sticky) -->
        <aside class="bd-col-right" id="paymentCard">
            <div class="bd-card bd-card-payment">
                <h2 class="bd-card-title">Payment Summary</h2>

                <?php if ($b['status'] === 'cancelled'): ?>
                    <p class="bd-pay-helper danger">This booking has been cancelled and is no longer active.</p>
                <?php endif; ?>

                <div class="bd-price-breakdown">
                    <div class="bd-price-row">
                        <span>Court fee (<?php echo $durationHours; ?> hr<?php echo $durationHours > 1 ? 's' : ''; ?>)</span>
                        <strong>Rs. <?php echo number_format((float)$b['total_price'], 0); ?></strong>
                    </div>
                    <?php if ((float)$b['discount'] > 0): ?>
                        <div class="bd-price-row is-discount">
                            <span>Promo (<?php echo e($b['promo_code']); ?>)</span>
                            <strong>&minus; Rs. <?php echo number_format((float)$b['discount'], 0); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="bd-price-row">
                        <span>Paid so far</span>
                        <strong>Rs. <?php echo number_format((float)$b['amount_paid'], 0); ?></strong>
                    </div>

                    <div class="bd-price-divider"></div>

                    <div class="bd-total-due-row">
                        <span class="bd-total-due-label">Total Due</span>
                        <span class="bd-total-due-amount">Rs. <?php echo number_format($balance, 0); ?></span>
                    </div>
                </div>

                <?php if ($b['status'] !== 'cancelled'): ?>
                    <?php if ($me['role'] === 'user' && $balance > 0): ?>
                        <?php if ($isPartial): ?>
                            <p class="bd-pay-helper">Advance paid (Rs. <?php echo number_format((float)$b['amount_paid'], 0); ?>). Remaining balance is due prior to kickoff.</p>
                        <?php endif; ?>
                        <a href="<?php echo base_url('pages/payment.php?booking_id=' . (int)$b['id']); ?>" class="bd-main-pay-btn">
                            Pay Rs. <?php echo number_format($balance, 0); ?> Now
                        </a>
                        <p class="bd-methods-note">Supported: eSewa &bull; Khalti &bull; Fonepay QR</p>
                    <?php elseif ($balance == 0): ?>
                        <div class="bd-paid-full-callout">
                            <svg class="bd-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <span>Paid in Full &mdash; Zero balance due</span>
                        </div>
                        <p class="bd-pay-helper">Show your booking reference <strong><?php echo e($bookingRefCode); ?></strong> upon arrival.</p>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Cancellation Policy (warnings only) -->
                <?php if (!$policy['allowed'] || $policy['fee'] > 0): ?>
                    <div class="bd-policy-text <?php echo !$policy['allowed'] ? 'policy-danger' : 'policy-warning'; ?>">
                        <svg class="bd-icon" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>
                            <?php if (!$policy['allowed']): ?>
                                Non-refundable: match started or past schedule.
                            <?php else: ?>
                                50% cancellation fee applies (&lt; 24 hrs to kickoff).
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

    </div>

    <!-- 5. FOOTER UTILITIES — one clean action row -->
    <footer class="bd-footer-utilities">
        <div class="bd-footer-btn-row">
            <?php if ($b['status'] === 'confirmed' || $b['status'] === 'pending'): ?>
                <a href="<?php echo base_url('pages/reschedule.php?booking_id=' . (int)$b['id']); ?>" class="bd-footer-btn">
                    Reschedule Slot
                </a>
            <?php endif; ?>

            <?php if ($isPaid): ?>
                <a href="<?php echo base_url('pages/receipt.php?booking_id=' . (int)$b['id']); ?>" class="bd-footer-btn">
                    Download Receipt
                </a>
            <?php else: ?>
                <a href="<?php echo base_url('pages/receipt_pdf.php?booking_id=' . (int)$b['id']); ?>" class="bd-footer-btn">
                    Download Receipt
                </a>
            <?php endif; ?>

            <?php if ($b['status'] === 'confirmed' || $b['status'] === 'pending'): ?>
                <?php if ($me['role'] === 'user'): ?>
                    <?php if ((int)$b['repeat_weeks'] > 1): ?>
                        <?php echo post_action_form(base_url('pages/my_bookings.php'), 'cancel_booking', (string)(int)$b['id'], 'Cancel Booking', 'bd-footer-btn danger', 'Cancel this whole weekly series of ' . (int)$b['repeat_weeks'] . '?', 'Cancel series', ['cancel_series' => '1'], 'Cancel series?'); ?>
                    <?php else: ?>
                        <?php echo post_action_form(base_url('pages/my_bookings.php'), 'cancel_booking', (string)(int)$b['id'], 'Cancel Booking', 'bd-footer-btn danger', 'Cancel this booking?', 'Cancel booking', [], 'Cancel booking?'); ?>
                    <?php endif; ?>
                <?php elseif ($me['role'] === 'manager'): ?>
                    <?php echo post_action_form(base_url('manager/bookings.php'), 'cancel_booking', (string)(int)$b['id'], 'Cancel Booking', 'bd-footer-btn danger', 'Cancel this booking?', 'Cancel booking', [], 'Cancel booking?'); ?>
                <?php elseif ($me['role'] === 'admin'): ?>
                    <?php echo post_action_form(base_url('admin/bookings.php'), 'cancel_booking', (string)(int)$b['id'], 'Cancel Booking', 'bd-footer-btn danger', 'Cancel this booking?', 'Cancel booking', [], 'Cancel booking?'); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </footer>

</div>

<script>
function copyInviteLink() {
    var input = document.getElementById('bdInviteInput');
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    var text = input.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(onCopied).catch(fallback);
    } else {
        fallback();
    }
    function fallback() {
        try {
            document.execCommand('copy');
            onCopied();
        } catch(e) {}
    }
    function onCopied() {
        var label = document.getElementById('bdCopyLabel');
        if (label) label.textContent = 'Copied';
        setTimeout(function() {
            if (label) label.textContent = 'Copy Link';
        }, 2200);
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
