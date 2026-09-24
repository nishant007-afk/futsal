<?php
// POST handlers for pages/payment.php  -  promo apply/remove, pay-at-court, QR submit.
// Never marks a booking paid from the client path: QR only records method + intent.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}
verify_csrf();

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

if ($action === 'pay_qr') {
    $option = $_POST['split_choice'] ?? ($isPartial ? 'remaining' : 'full');
    $txRef = trim($_POST['transaction_ref'] ?? '');
    $txRef = mb_substr($txRef, 0, 60);

    if ($isPartial) {
        $payment_type = 'full';
        $amount_claimed = $remaining;
        $claimLabel = 'Remaining balance';
    } elseif ($option === 'advance') {
        $payment_type = 'advance';
        $amount_claimed = $advance;
        $claimLabel = '20% advance';
    } else {
        $payment_type = 'full';
        $amount_claimed = $netTotal;
        $claimLabel = 'Full payment';
    }

    // Keep payment_status as-is (unpaid/partial)  -  only record method + intent.
    $stmt = $conn->prepare(
        'UPDATE bookings SET payment_method = "qr", payment_type = ?
         WHERE id = ? AND user_id = ? AND status = "confirmed"'
    );
    $stmt->bind_param('sii', $payment_type, $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    $notifMsg = $booking['ground_name'] . ' &middot; ' . $claimLabel . ' Rs ' . number_format($amount_claimed, 0)
        . ' awaiting verification &middot; ' . $booking['booking_ref'];
    if ($txRef !== '') {
        $notifMsg .= ' (Tx: ' . htmlspecialchars($txRef, ENT_QUOTES) . ')';
    }
    if ((int)$booking['manager_id'] > 0) {
        notify_user(
            (int)$booking['manager_id'],
            'QR payment submitted  -  verify',
            $notifMsg,
            'fa-qrcode',
            'pages/booking_details.php?id=' . $booking_id
        );
    }

    set_flash(
        'success',
        'QR payment submitted. The court will verify your transfer and mark it paid  -  usually within a few minutes. Keep your transaction ID handy.'
    );
    redirect('pages/booking_details.php?id=' . $booking_id . '&paid=1');
}
