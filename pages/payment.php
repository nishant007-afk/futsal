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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Promo Code Application
    if (isset($_POST['apply_promo'])) {
        $code = strtoupper(trim($_POST['promo_code'] ?? ''));
        if ($discount > 0) {
            set_flash('info', 'A promo is already applied to this booking.');
            redirect('pages/payment.php?booking_id=' . $booking_id);
        }
        $result = validate_promo_code($code, $total, $booking['ground_id'], (int)$_SESSION['user_id']);
        if (isset($result['error'])) {
            set_flash_error(
                'That promo code can\'t be applied.',
                $result['error'],
                'Double-check the code, or continue without a promo.',
                'pages/payment.php?booking_id=' . $booking_id
            );
            redirect('pages/payment.php?booking_id=' . $booking_id);
        }
        $discount = $result['discount'];
        $promoId = (int)$result['promo']['id'];
        $stmt = $conn->prepare('UPDATE bookings SET discount = ?, promo_code = ?, promo_id = ? WHERE id = ? AND user_id = ?');
        $stmt->bind_param('dsiii', $discount, $code, $promoId, $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        set_flash('success', 'Promo applied! You save Rs ' . number_format($discount, 0));
        redirect('pages/payment.php?booking_id=' . $booking_id);
    }

    if (isset($_POST['remove_promo'])) {
        $stmt = $conn->prepare('UPDATE bookings SET discount = 0, promo_code = NULL, promo_id = NULL WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        set_flash('info', 'Promo removed.');
        redirect('pages/payment.php?booking_id=' . $booking_id);
    }

    $action = $_POST['payment_action'] ?? '';

    // Action 1: Pay at Venue / Cash on Arrival
    if ($action === 'pay_court') {
        if ($isPartial) {
            set_flash_error('You already paid an advance online. Please pay the remaining balance via QR or at the counter.');
            redirect('pages/payment.php?booking_id=' . $booking_id);
        }

        $stmt = $conn->prepare(
            'UPDATE bookings SET payment_method = "at_court", payment_type = "full", amount_paid = 0, payment_status = "unpaid"
             WHERE id = ? AND user_id = ? AND status = "confirmed"'
        );
        $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();

        if ((int)$booking['manager_id'] > 0) {
            notify_user(
                (int)$booking['manager_id'],
                'New booking (Pay at court)',
                $booking['ground_name'] . ' &middot; Rs ' . number_format($netTotal, 0) . ' due &middot; ' . $booking['booking_ref'],
                'fa-store',
                'pages/booking_details.php?id=' . $booking_id
            );
        }

        set_flash('success', 'Booking confirmed! Your slot is locked. Please arrive 15 minutes before kickoff and pay Rs ' . number_format($netTotal, 0) . ' at the counter.');
        redirect('pages/booking_details.php?id=' . $booking_id);
    }

    // Action 2: Instant QR Payment (Fonepay / eSewa / Khalti / Mobile Banking)
    if ($action === 'pay_qr') {
        $option = $_POST['split_choice'] ?? ($isPartial ? 'remaining' : 'full');
        $txRef = trim($_POST['transaction_ref'] ?? '');

        if ($isPartial) {
            $payment_type = 'full';
            $amount_to_pay = $remaining;
            $new_status = 'paid';
        } elseif ($option === 'advance') {
            $payment_type = 'advance';
            $amount_to_pay = $advance;
            $new_status = 'partial';
        } else {
            $payment_type = 'full';
            $amount_to_pay = $netTotal;
            $new_status = 'paid';
        }

        $conn->begin_transaction();

        // Increment promo usage safely if used
        if ($promoId > 0) {
            $promoOk = increment_promo_usage($promoId);
            if (!$promoOk) {
                $conn->rollback();
                set_flash_error('That promo code reached its usage limit. Please proceed without it.');
                redirect('pages/payment.php?booking_id=' . $booking_id);
            }
        }

        $stmt = $conn->prepare(
            'UPDATE bookings SET payment_type = ?, payment_method = "qr", amount_paid = ?, payment_status = ?, paid_at = NOW()
             WHERE id = ? AND user_id = ? AND status = "confirmed"'
        );
        $stmt->bind_param('sdsii', $payment_type, $amount_to_pay, $new_status, $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // Send notifications
        $paySummary = [
            'Booking ref' => $booking['booking_ref'],
            'Court'       => $booking['ground_name'],
            'Date'        => date('D, M j, Y', strtotime($booking['booking_date'])),
            'Time'        => substr($booking['start_time'], 0, 5) . ' - ' . substr($booking['end_time'], 0, 5),
            'Amount'      => 'Rs ' . number_format($amount_to_pay, 0),
            'Status'      => $new_status === 'paid' ? 'Paid in full' : 'Advance deposit paid',
        ];
        if ($txRef !== '') {
            $paySummary['Transaction ID'] = $txRef;
        }

        $playerRow = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
        $playerRow->bind_param('i', $_SESSION['user_id']);
        $playerRow->execute();
        $playerEmail = $playerRow->get_result()->fetch_assoc();
        if ($playerEmail) {
            send_booking_email(
                $playerEmail['email'],
                'Payment confirmation for booking ' . $booking['booking_ref'],
                'Payment Received',
                $paySummary,
                'Your payment is verified. Your digital receipt and booking pass are ready in My Bookings.',
                $playerEmail['name'] ?? ''
            );
        }

        if ((int)$booking['manager_id'] > 0) {
            $notifMsg = $booking['ground_name'] . ' &middot; Rs ' . number_format($amount_to_pay, 0) . ' &middot; ' . $booking['booking_ref'];
            if ($txRef !== '') {
                $notifMsg .= ' (Tx: ' . substr($txRef, 0, 16) . ')';
            }
            notify_user(
                (int)$booking['manager_id'],
                'Payment received via QR',
                $notifMsg,
                'fa-qrcode',
                'pages/booking_details.php?id=' . $booking_id
            );
        }

        set_flash('success', 'Payment confirmed! Your match at ' . $booking['ground_name'] . ' is locked in.');
        redirect('pages/booking_details.php?id=' . $booking_id . '&paid=1');
    }
}

$page_title = 'Checkout & Payment';
$page_description = 'Securely confirm and pay for your futsal booking on GoalSpace.';
require __DIR__ . '/../includes/header.php';

$qrFile = ground_qr((int)$booking['ground_id']);
?>

<div class="checkout-wrap reveal">
    <div class="page-head">
        <div class="title-back-row">
            <a href="<?php echo base_url('pages/booking_details.php?id=' . $booking_id); ?>" class="page-back-arrow" data-back aria-label="Back to booking"><i class="fa-solid fa-arrow-left"></i></a>
            <div>
                <span class="eyebrow"><i class="fa-solid fa-shield-halved"></i> Secure Checkout</span>
                <h1><?php echo $isPartial ? 'Pay remaining balance' : 'Choose payment method'; ?></h1>
            </div>
        </div>
    </div>

    <div class="checkout-grid">
        <!-- Main Payment Column -->
        <div class="checkout-main">
            <div class="pay-methods-list">
                <!-- Method 1: Instant QR Payment -->
                <div class="pay-method-card active" id="cardMethodQr">
                    <div class="pm-head" onclick="selectPayMethod('qr')">
                        <div class="pm-head-left">
                            <div class="pm-icon"><i class="fa-solid fa-qrcode"></i></div>
                            <div class="pm-title-wrap">
                                <h3>Instant QR / Digital Wallet</h3>
                                <p>Pay with Fonepay, eSewa, Khalti, or Mobile Banking</p>
                            </div>
                        </div>
                        <span class="pm-badge pm-badge--popular">Instant Lock</span>
                    </div>

                    <form method="post" action="" id="qrPayForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="payment_action" value="pay_qr">
                        <input type="hidden" name="split_choice" id="splitChoiceInput" value="<?php echo $isPartial ? 'remaining' : 'advance'; ?>">

                        <div class="qr-payment-box">
                            <?php if (!$isPartial): ?>
                                <label class="text-xs muted font-semibold mb-6 block">Select payment amount</label>
                                <div class="split-pills">
                                    <div class="split-pill active" id="pillAdvance" onclick="setSplitOption('advance', <?php echo $advance; ?>)">
                                        <span class="sp-title">Pay 20% Advance</span>
                                        <span class="sp-sub">Rs <?php echo number_format($advance, 0); ?> today &middot; Rs <?php echo number_format($balance, 0); ?> at court</span>
                                    </div>
                                    <div class="split-pill" id="pillFull" onclick="setSplitOption('full', <?php echo $netTotal; ?>)">
                                        <span class="sp-title">Pay 100% in Full</span>
                                        <span class="sp-sub">Rs <?php echo number_format($netTotal, 0); ?> today &middot; zero at court</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="notice notice-info mb-14">
                                    <i class="fa-solid fa-circle-info"></i>
                                    <span>Remaining balance to complete booking: <strong>Rs <?php echo number_format($remaining, 0); ?></strong></span>
                                </div>
                            <?php endif; ?>

                            <!-- Supported Wallet Logos -->
                            <div class="wallet-badges">
                                <span class="wallet-badge"><i class="fa-solid fa-bolt text-brand"></i> Fonepay</span>
                                <span class="wallet-badge"><i class="fa-solid fa-wallet text-brand"></i> eSewa</span>
                                <span class="wallet-badge"><i class="fa-solid fa-wallet text-brand"></i> Khalti</span>
                                <span class="wallet-badge"><i class="fa-solid fa-building-columns text-brand"></i> Mobile Banking</span>
                            </div>

                            <!-- QR Display Frame -->
                            <div class="qr-frame">
                                <?php if ($qrFile !== ''): ?>
                                    <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($qrFile)); ?>" alt="Merchant Payment QR for <?php echo e($booking['ground_name']); ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <div class="text-center p-12">
                                        <i class="fa-solid fa-qrcode text-4xl text-brand mb-6 block"></i>
                                        <p class="font-bold text-sm mb-2"><?php echo e($booking['ground_name']); ?></p>
                                        <p class="text-xs muted">Scan with any Nepalese QR app</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Copy Chips Row -->
                            <div class="copy-chips-row">
                                <div class="copy-chip-box">
                                    <div>
                                        <span class="cc-label">Payable Amount</span>
                                        <div class="cc-value" id="displayPayableAmount">Rs <?php echo number_format($isPartial ? $remaining : $advance, 0); ?></div>
                                    </div>
                                    <button type="button" class="btn-copy" onclick="copyText('<?php echo $isPartial ? $remaining : $advance; ?>', this)" title="Copy amount">
                                        <i class="fa-regular fa-copy"></i> Copy
                                    </button>
                                </div>
                                <div class="copy-chip-box">
                                    <div>
                                        <span class="cc-label">Booking Reference</span>
                                        <div class="cc-value"><?php echo e($booking['booking_ref']); ?></div>
                                    </div>
                                    <button type="button" class="btn-copy" onclick="copyText('<?php echo e($booking['booking_ref']); ?>', this)" title="Copy reference">
                                        <i class="fa-regular fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>

                            <p class="text-xs muted mb-14 text-center">
                                <i class="fa-solid fa-circle-info"></i> Enter <strong><?php echo e($booking['booking_ref']); ?></strong> in your transfer remarks to speed up verification.
                            </p>

                            <!-- Optional Transaction Ref -->
                            <div class="tx-input-wrap">
                                <label for="txRefInput">Transaction Code / Remarks (optional)</label>
                                <input type="text" id="txRefInput" name="transaction_ref" placeholder="e.g. 12-digit transaction ID or your phone number" maxlength="60" autocomplete="off">
                                <p class="tx-hint">Found on your payment app screen after transfer.</p>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg" id="btnSubmitQr">
                                <i class="fa-solid fa-circle-check"></i> <span id="btnSubmitQrLabel">Confirm Payment of Rs <?php echo number_format($isPartial ? $remaining : $advance, 0); ?></span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Method 2: Pay at Court / Venue -->
                <?php if (!$isPartial): ?>
                    <div class="pay-method-card" id="cardMethodCourt">
                        <div class="pm-head" onclick="selectPayMethod('court')">
                            <div class="pm-head-left">
                                <div class="pm-icon pm-icon--cash"><i class="fa-solid fa-store"></i></div>
                                <div class="pm-title-wrap">
                                    <h3>Pay Cash at the Court</h3>
                                    <p>Zero advance required &middot; pay full amount upon arrival</p>
                                </div>
                            </div>
                            <span class="pm-badge">Pay on Arrival</span>
                        </div>

                        <div id="courtPayBox" style="display:none; margin-top:18px; padding-top:18px; border-top:1px solid var(--line);">
                            <div class="notice notice-info mb-14">
                                <i class="fa-solid fa-clock"></i>
                                <span>Please arrive at least <strong>15 minutes before kickoff</strong> (<?php echo substr($booking['start_time'], 0, 5); ?>) to settle your fee at the counter.</span>
                            </div>
                            <form method="post" action="">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="payment_action" value="pay_court">
                                <button type="submit" class="btn btn-outline btn-block btn-lg">
                                    <i class="fa-solid fa-check"></i> Reserve Slot &middot; Pay Rs <?php echo number_format($netTotal, 0); ?> at Court
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Sticky Order Summary -->
        <div class="checkout-sidebar">
            <div class="order-summary-card">
                <h3 class="osc-title">Booking Summary</h3>

                <div class="osc-venue-header">
                    <div class="osc-date-badge">
                        <span class="odb-m"><?php echo e(strtoupper(date('M', strtotime($booking['booking_date'])))); ?></span>
                        <span class="odb-d"><?php echo (int)date('d', strtotime($booking['booking_date'])); ?></span>
                    </div>
                    <div class="osc-venue-info">
                        <h4><?php echo e($booking['ground_name']); ?></h4>
                        <p><i class="fa-solid fa-location-dot"></i> <?php echo e($booking['location']); ?></p>
                        <p><i class="fa-regular fa-clock"></i> <?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?> (<?php echo $durationHours; ?> hr)</p>
                    </div>
                </div>

                <!-- Price Itemization -->
                <div class="osc-lines">
                    <div class="osc-row">
                        <span>Court rate (<?php echo $durationHours; ?> hr)</span>
                        <strong>Rs <?php echo number_format($total, 0); ?></strong>
                    </div>
                    <?php if ($discount > 0): ?>
                        <div class="osc-row discount">
                            <span>Promo discount (<strong><?php echo e($booking['promo_code']); ?></strong>)</span>
                            <strong>&minus; Rs <?php echo number_format($discount, 0); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="osc-row total">
                        <span>Total amount</span>
                        <strong>Rs <?php echo number_format($netTotal, 0); ?></strong>
                    </div>
                    <?php if ($isPartial): ?>
                        <div class="osc-row">
                            <span>Already paid</span>
                            <strong class="text-brand">Rs <?php echo number_format($alreadyPaid, 0); ?></strong>
                        </div>
                        <div class="osc-row total">
                            <span>Remaining balance</span>
                            <strong>Rs <?php echo number_format($remaining, 0); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Promo Voucher Form -->
                <?php if ($discount > 0): ?>
                    <div class="promo-applied mb-14">
                        <span><i class="fa-solid fa-tag"></i> Coupon <strong><?php echo e($booking['promo_code']); ?></strong> applied</span>
                        <form method="post" action="">
                            <?php echo csrf_field(); ?>
                            <button type="submit" name="remove_promo" value="1" class="promo-remove"><i class="fa-solid fa-xmark"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" action="" class="promo-form mb-14">
                        <?php echo csrf_field(); ?>
                        <div class="promo-input">
                            <i class="fa-solid fa-tag"></i>
                            <input type="text" name="promo_code" placeholder="Have a promo code?" maxlength="40" autocomplete="off">
                            <button type="submit" name="apply_promo" value="1" class="btn btn-outline btn-sm">Apply</button>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Trust Badges List -->
                <div class="trust-badges-list">
                    <div class="trust-badge-row">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span><strong>100% Guaranteed Slot</strong> &middot; Instantly held in court schedule</span>
                    </div>
                    <div class="trust-badge-row">
                        <i class="fa-solid fa-rotate-left"></i>
                        <span><strong>Free Cancellation</strong> &middot; Up to 24 hours prior to kickoff</span>
                    </div>
                    <div class="trust-badge-row">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span><strong>Digital Pass &amp; Receipt</strong> &middot; Instant download with calendar sync</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectPayMethod(method) {
    var cardQr = document.getElementById('cardMethodQr');
    var cardCourt = document.getElementById('cardMethodCourt');
    var courtBox = document.getElementById('courtPayBox');

    if (method === 'court') {
        if (cardQr) cardQr.classList.remove('active');
        if (cardCourt) cardCourt.classList.add('active');
        if (courtBox) courtBox.style.display = 'block';
    } else {
        if (cardCourt) cardCourt.classList.remove('active');
        if (cardQr) cardQr.classList.add('active');
        if (courtBox) courtBox.style.display = 'none';
    }
}

function setSplitOption(choice, amount) {
    var pillAdv = document.getElementById('pillAdvance');
    var pillFull = document.getElementById('pillFull');
    var input = document.getElementById('splitChoiceInput');
    var display = document.getElementById('displayPayableAmount');
    var btnLabel = document.getElementById('btnSubmitQrLabel');

    if (input) input.value = choice;
    if (choice === 'full') {
        if (pillAdv) pillAdv.classList.remove('active');
        if (pillFull) pillFull.classList.add('active');
    } else {
        if (pillFull) pillFull.classList.remove('active');
        if (pillAdv) pillAdv.classList.add('active');
    }

    var formatted = 'Rs ' + Number(amount).toLocaleString();
    if (display) display.textContent = formatted;
    if (btnLabel) btnLabel.textContent = 'Confirm Payment of ' + formatted;
}

function copyText(val, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(function() {
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
            setTimeout(function() { btn.innerHTML = orig; }, 2000);
        });
    } else {
        var el = document.createElement('textarea');
        el.value = val;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
