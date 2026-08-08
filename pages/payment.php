<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.ground_id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status, b.payment_status, b.amount_paid, b.payment_type,
            b.discount, b.promo_code, b.promo_id,
            g.name AS ground_name, g.location, g.price_per_hour
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
    redirect('pages/my_bookings.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['apply_promo'])) {
        $code = strtoupper(trim($_POST['promo_code'] ?? ''));
        if ($discount > 0) {
            set_flash('info', 'A promo is already applied to this booking.');
            redirect('pages/payment.php?booking_id=' . $booking_id);
        }
        $result = validate_promo_code($code, $total, $booking['ground_id']);
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
        increment_promo_usage($promoId);
        $netTotal = round($total - $discount, 2);
        $advance = round($netTotal * 0.2, 2);
        $balance = round($netTotal - $advance, 2);
        $remaining = round($netTotal - $alreadyPaid, 2);
        set_flash('success', 'Promo applied. You save Rs ' . number_format($discount, 0) . '!');
        redirect('pages/payment.php?booking_id=' . $booking_id);
    }

    if (isset($_POST['remove_promo'])) {
        $stmt = $conn->prepare('UPDATE bookings SET discount = 0, promo_code = NULL, promo_id = NULL WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        set_flash('info', 'Promo removed.');
        redirect('pages/payment.php?booking_id=' . $booking_id);
    }

    $option = $_POST['payment_option'] ?? '';

    if ($isPartial && $option === 'remaining') {
        $payment_type = 'full';
        $amount_paid = $netTotal;
        $payment_status = 'paid';
    } elseif (!$isPartial && $option === 'advance') {
        $payment_type = 'advance';
        $amount_paid = $advance;
        $payment_status = 'partial';
    } elseif (!$isPartial && $option === 'full') {
        $payment_type = 'full';
        $amount_paid = $netTotal;
        $payment_status = 'paid';
    } else {
        set_flash_error(
            'No payment option was selected.',
            'We need to know how you want to pay before processing.',
            'Pick "Pay 20% now" or "Pay in full online", then tap the pay button.',
            'pages/payment.php?booking_id=' . $booking_id
        );
        redirect('pages/payment.php?booking_id=' . $booking_id);
    }

    $stmt = $conn->prepare('UPDATE bookings SET payment_status = ?, payment_type = ?, amount_paid = ?, paid_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ssdii', $payment_status, $payment_type, $amount_paid, $booking_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        notify_user(
            (int)$_SESSION['user_id'],
            $payment_status === 'paid' ? 'Payment received' : 'Advance paid',
            $booking['ground_name'] . ' &middot; Rs ' . number_format($amount_paid, 0) . ' received',
            'fa-sack-dollar',
            'pages/booking_details.php?id=' . $booking_id
        );
        $mgrStmt = $conn->prepare('SELECT g.manager_id FROM grounds g JOIN bookings b ON b.ground_id = g.id WHERE b.id = ?');
        $mgrStmt->bind_param('i', $booking_id);
        $mgrStmt->execute();
        $mgr = $mgrStmt->get_result()->fetch_assoc();
        if ($mgr && (int)$mgr['manager_id'] !== (int)$_SESSION['user_id']) {
            notify_user(
                (int)$mgr['manager_id'],
                $payment_status === 'paid' ? 'Payment received' : 'Advance paid',
                $booking['ground_name'] . ' &middot; Rs ' . number_format($amount_paid, 0) . ' received from a player',
                'fa-sack-dollar',
                'pages/booking_details.php?id=' . $booking_id
            );
        }
        redirect('pages/confirmation.php?booking_id=' . $booking_id);
    } else {
        set_flash_error(
            'Your payment couldn\'t be completed.',
            'The checkout hit an unexpected problem.',
            'Check your details and try again — nothing has been charged.',
            'pages/payment.php?booking_id=' . $booking_id
        );
    }
    redirect('pages/my_bookings.php');
}

$page_title = 'Payment';
$page_description = 'Complete your secure payment for your futsal court booking on GoalSpace and confirm your slot.';
require __DIR__ . '/../includes/header.php';
?>

<div class="payment-wrap reveal">
    <div class="payment-card">
        <div class="payment-head">
            <span class="eyebrow"><?php echo $isPartial ? 'One step left' : 'Almost there'; ?></span>
            <h1><?php echo $isPartial ? 'Pay your remaining balance' : 'Complete your payment'; ?></h1>
            <p><?php echo $isPartial ? 'Your slot is locked in. Pay the rest to complete this booking.' : 'Your slot is locked in. Choose how you\'d like to pay for it.'; ?></p>
        </div>

        <div class="payment-summary">
            <div class="payment-ground">
                <div class="payment-date">
                    <span class="bd-month"><?php echo e(strtoupper(date('M', strtotime($booking['booking_date'])))); ?></span>
                    <span class="bd-day"><?php echo (int)date('d', strtotime($booking['booking_date'])); ?></span>
                </div>
                <div>
                    <h3><?php echo e($booking['ground_name']); ?></h3>
                    <p><i class="fa-solid fa-location-dot"></i> <?php echo e($booking['location']); ?></p>
                    <p><i class="fa-regular fa-clock"></i> <?php echo e(substr($booking['start_time'], 0, 5)); ?> - <?php echo e(substr($booking['end_time'], 0, 5)); ?></p>
                </div>
            </div>
            <div class="payment-total">
                <span>Total</span>
                <strong>Rs <?php echo number_format($total, 0); ?></strong>
                <?php if ($discount > 0): ?>
                    <span class="pay-line ok">Promo &minus; Rs <?php echo number_format($discount, 0); ?></span>
                    <span class="pay-line lg">Pay Rs <?php echo number_format($netTotal, 0); ?></span>
                <?php endif; ?>
                <?php if ($isPartial): ?>
                    <span class="pay-line ok">Rs <?php echo number_format($alreadyPaid, 0); ?> already paid</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($discount > 0): ?>
            <div class="promo-applied reveal">
                <span><i class="fa-solid fa-tag"></i> <strong><?php echo e($booking['promo_code']); ?></strong> applied &middot; Rs <?php echo number_format($discount, 0); ?> off</span>
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="remove_promo" value="1" class="promo-remove"><i class="fa-solid fa-xmark"></i> Remove</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" action="" class="promo-form reveal">
                <?php echo csrf_field(); ?>
                <div class="promo-input">
                    <i class="fa-solid fa-tag"></i>
                    <input type="text" name="promo_code" placeholder="Have a promo code?" maxlength="40" autocomplete="off">
                    <button type="submit" name="apply_promo" value="1" class="btn btn-outline btn-sm"><i class="fa-solid fa-wand-magic-sparkles"></i> Apply</button>
                </div>
            </form>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo csrf_field(); ?>
            <div class="pay-options">
                <?php if ($isPartial): ?>
                    <label class="pay-option" data-check="payRemaining">
                        <input type="radio" name="payment_option" value="remaining">
                        <span class="pay-icon"><i class="fa-solid fa-credit-card"></i></span>
                        <span class="pay-text">
                            <span class="pay-name">Pay remaining balance</span>
                            <span class="pay-desc">Rs <?php echo number_format($remaining, 0); ?> now &middot; booking fully paid</span>
                        </span>
                        <span class="pay-check"><i class="fa-solid fa-check"></i></span>
                    </label>
                <?php else: ?>
                    <label class="pay-option" data-check="payAdvance">
                        <input type="radio" name="payment_option" value="advance">
                        <span class="pay-icon"><i class="fa-solid fa-coins"></i></span>
                        <span class="pay-text">
                            <span class="pay-name">Pay 20% now</span>
                            <span class="pay-desc">Rs <?php echo number_format($advance, 0); ?> today &middot; Rs <?php echo number_format($balance, 0); ?> at the court</span>
                        </span>
                        <span class="pay-check"><i class="fa-solid fa-check"></i></span>
                    </label>
                    <label class="pay-option" data-check="payFull">
                        <input type="radio" name="payment_option" value="full">
                        <span class="pay-icon"><i class="fa-solid fa-credit-card"></i></span>
                        <span class="pay-text">
                            <span class="pay-name">Pay in full online</span>
                            <span class="pay-desc">Rs <?php echo number_format($netTotal, 0); ?> now &middot; nothing to pay later</span>
                        </span>
                        <span class="pay-check"><i class="fa-solid fa-check"></i></span>
                    </label>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="payBtn" disabled>
                <i class="fa-solid fa-lock"></i> <?php echo $isPartial ? 'Pay Rs ' . number_format($remaining, 0) : 'Pay now'; ?>
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
