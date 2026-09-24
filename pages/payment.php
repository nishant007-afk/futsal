<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.ground_id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status, b.payment_status, b.amount_paid, b.payment_type,
            b.discount, b.promo_code, b.promo_id,
            g.name AS ground_name, g.location, g.price_per_hour, g.manager_id, g.image, g.payment_qr
     FROM bookings b
     JOIN grounds g ON g.id = b.ground_id
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

if ($booking['payment_status'] === 'paid') {
    set_flash('success', 'This booking is already fully paid.');
    redirect('pages/booking_details.php?id=' . $booking_id);
}

$total = (float)$booking['total_price'];
$alreadyPaid = (float)$booking['amount_paid'];
$discount = (float)$booking['discount'];
$promoId = (int)$booking['promo_id'];
$netTotal = round($total - $discount, 2);
$remaining = round($netTotal - $alreadyPaid, 2);
$advance = round($netTotal * 0.2, 2);
$balance = round($netTotal - $advance, 2);
$isPartial = $booking['payment_status'] === 'partial';

// Calculate duration
$startTs = strtotime($booking['start_time']);
$endTs = strtotime($booking['end_time']);
$durationHours = max(1, round(($endTs - $startTs) / 3600, 1));

require __DIR__ . '/payment_actions.php';

$page_title = 'Checkout & Payment';
$page_description = 'Securely confirm and pay for your futsal booking on GoalSpace.';
require __DIR__ . '/../includes/header.php';

$qrFile = ground_qr((int)$booking['ground_id']);
?>

<div class="checkout-wrap reveal">
    <div class="title-back-row bd-title-row">
        <a href="<?php echo base_url('pages/booking_details.php?id=' . $booking_id); ?>" class="page-back-arrow" data-back aria-label="Back to booking"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1><?php echo $isPartial ? 'Pay remaining balance' : 'Complete your booking'; ?></h1>
        </div>
    </div>

    <div class="checkout-grid">
        <div class="checkout-main">
            <div class="pay-methods-list" role="radiogroup" aria-label="Payment method">
                <!-- Method 1: QR Payment -->
                <div class="pay-method-card active" id="cardMethodQr">
                    <div class="pm-head" role="radio" tabindex="0" aria-checked="true" aria-controls="qrPayForm" data-method="qr">
                        <div class="pm-head-left">
                            <div class="pm-radio" aria-hidden="true"><span class="pm-radio-dot"></span></div>
                            <div class="pm-icon" aria-hidden="true"><i class="fa-solid fa-qrcode"></i></div>
                            <div class="pm-title-wrap">
                                <h3>Pay with QR</h3>
                                <p>Scan with Fonepay, eSewa, Khalti, or mobile banking</p>
                            </div>
                        </div>
                        <span class="pm-badge pm-badge--popular">Instant</span>
                    </div>

                    <form method="post" action="" id="qrPayForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="payment_action" value="pay_qr">
                        <input type="hidden" name="split_choice" id="splitChoiceInput" value="<?php echo $isPartial ? 'remaining' : 'advance'; ?>">

                        <div class="qr-payment-box">
                            <?php if (!$isPartial): ?>
                                <div class="split-pills-label" id="splitPillsLabel">Choose payment amount</div>
                                <div class="split-pills" role="radiogroup" aria-labelledby="splitPillsLabel">
                                    <button type="button" class="split-pill active" id="pillAdvance" role="radio" aria-checked="true" data-split="advance" data-amount="<?php echo $advance; ?>">
                                        <div class="sp-pill-head">
                                            <span class="sp-tag">20% Advance</span>
                                        </div>
                                        <div class="sp-amount">Rs <?php echo number_format($advance, 0); ?></div>
                                        <span class="sp-sub">Rs <?php echo number_format($balance, 0); ?> due at the court</span>
                                    </button>
                                    <button type="button" class="split-pill" id="pillFull" role="radio" aria-checked="false" data-split="full" data-amount="<?php echo $netTotal; ?>">
                                        <div class="sp-pill-head">
                                            <span class="sp-tag sp-tag--full">Full payment</span>
                                        </div>
                                        <div class="sp-amount">Rs <?php echo number_format($netTotal, 0); ?></div>
                                        <span class="sp-sub">Nothing left to pay on match day</span>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="notice notice-info mb-14">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>Remaining balance: <strong>Rs <?php echo number_format($remaining, 0); ?></strong></span>
                                </div>
                            <?php endif; ?>

                            <div class="qr-showcase">
                                <div class="qr-frame">
                                    <?php if ($qrFile !== ''): ?>
                                        <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($qrFile)); ?>" alt="Payment QR for <?php echo e($booking['ground_name']); ?>" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="qr-placeholder">
                                            <i class="fa-solid fa-qrcode"></i>
                                            <strong><?php echo e($booking['ground_name']); ?></strong>
                                            <span>Scan with any Nepal QR app</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="qr-meta-side">
                                    <div class="tx-input-wrap">
                                        <label for="txRefInput">Transaction ID <span class="muted font-normal">(optional)</span></label>
                                        <input type="text" id="txRefInput" name="transaction_ref" placeholder="Enter after you pay" maxlength="60" autocomplete="off">
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="btnSubmitQr">
                                        <i class="fa-solid fa-lock"></i> <span id="btnSubmitQrLabel">I paid  -  submit Rs <?php echo number_format($isPartial ? $remaining : $advance, 0); ?></span>
                                    </button>
                                    <p class="form-hint mt-8 mb-0"><i class="fa-solid fa-shield-halved"></i> Status stays unpaid until the court verifies your transfer.</p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Method 2: Pay at Court -->
                <?php if (!$isPartial): ?>
                    <div class="pay-method-card" id="cardMethodCourt">
                        <div class="pm-head" role="radio" tabindex="0" aria-checked="false" aria-controls="courtPayBox" data-method="court">
                            <div class="pm-head-left">
                                <div class="pm-radio" aria-hidden="true"><span class="pm-radio-dot"></span></div>
                                <div class="pm-icon pm-icon--cash" aria-hidden="true"><i class="fa-solid fa-store"></i></div>
                                <div class="pm-title-wrap">
                                    <h3>Pay at the court</h3>
                                    <p>Pay on arrival</p>
                                </div>
                            </div>
                            <span class="pm-badge">On arrival</span>
                        </div>

                        <div id="courtPayBox" style="display:none;" class="court-pay-reveal">
                            <div class="court-pay-notice">
                                <i class="fa-solid fa-clock"></i>
                                <div>
                                    <strong>Arrive 15 minutes before kickoff</strong> (<?php echo substr($booking['start_time'], 0, 5); ?>)
                                    <span>Pay Rs <?php echo number_format($netTotal, 0); ?> at the ground desk.</span>
                                </div>
                            </div>
                            <form method="post" action="">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="payment_action" value="pay_court">
                                <button type="submit" class="btn btn-outline btn-block btn-lg">
                                    <i class="fa-solid fa-check"></i> Reserve slot &middot; pay Rs <?php echo number_format($netTotal, 0); ?> at court
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="checkout-sidebar">
            <div class="order-summary-card">
                <div class="osc-head">
                    <h3 class="osc-title">Booking summary</h3>
                    <span class="osc-ref"><?php echo e($booking['booking_ref']); ?></span>
                </div>

                <div class="osc-venue-header">
                    <div class="osc-date-badge">
                        <span class="odb-m"><?php echo e(strtoupper(date('M', strtotime($booking['booking_date'])))); ?></span>
                        <span class="odb-d"><?php echo (int)date('d', strtotime($booking['booking_date'])); ?></span>
                    </div>
                    <div class="osc-venue-info">
                        <h4><?php echo e($booking['ground_name']); ?></h4>
                        <p class="osc-loc"><i class="fa-solid fa-location-dot"></i> <?php echo e($booking['location']); ?></p>
                        <p class="osc-time"><i class="fa-regular fa-clock"></i> <?php echo substr($booking['start_time'], 0, 5); ?> &ndash; <?php echo substr($booking['end_time'], 0, 5); ?> (<?php echo $durationHours; ?> hr)</p>
                    </div>
                </div>

                <div class="osc-lines">
                    <div class="osc-row">
                        <span>Pitch fee (<?php echo $durationHours; ?> hr)</span>
                        <strong>Rs <?php echo number_format($total, 0); ?></strong>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div class="osc-row discount">
                            <span>Promo discount (<strong><?php echo e($booking['promo_code']); ?></strong>)</span>
                            <strong>&minus; Rs <?php echo number_format($discount, 0); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="osc-row total">
                        <span>Total</span>
                        <strong>Rs <?php echo number_format($netTotal, 0); ?></strong>
                    </div>
                    <?php if ($isPartial): ?>
                        <div class="osc-row">
                            <span>Already paid</span>
                            <strong class="text-brand">Rs <?php echo number_format($alreadyPaid, 0); ?></strong>
                        </div>
                        <div class="osc-row total">
                            <span>Remaining</span>
                            <strong>Rs <?php echo number_format($remaining, 0); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($discount > 0): ?>
                    <div class="promo-applied mb-14">
                        <span><i class="fa-solid fa-tag"></i> Coupon <strong><?php echo e($booking['promo_code']); ?></strong> applied</span>
                        <form method="post" action="">
                            <?php echo csrf_field(); ?>
                            <button type="submit" name="remove_promo" value="1" class="promo-remove" aria-label="Remove promo"><i class="fa-solid fa-xmark"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" action="" class="promo-form mb-14">
                        <?php echo csrf_field(); ?>
                        <div class="promo-input">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" id="promo_code" name="promo_code" aria-label="Promo code" placeholder="Promo code" maxlength="40" autocomplete="off">
                            <button type="submit" name="apply_promo" value="1" class="btn btn-outline btn-sm">Apply</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
