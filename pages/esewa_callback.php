<?php
require_once __DIR__ . '/../config/db.php';

require_player();

$result     = $_GET['result'] ?? '';
$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.ground_id, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.discount, b.promo_code, b.esewa_txn_uuid, b.esewa_ref_id,
            g.name AS ground_name, g.location
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
    redirect('pages/confirmation.php?booking_id=' . $booking_id);
}

$pending = $_SESSION['esewa_pending'] ?? null;

if ($result === 'success') {
    $txnUuid = $pending['transaction_uuid'] ?? $booking['esewa_txn_uuid'] ?? '';
    $total   = (float)($pending['total_amount'] ?? 0);
    $refId   = '';

    $dataParam = $_GET['data'] ?? '';
    if ($dataParam !== '') {
        $decoded = base64_decode($dataParam, true);
        if ($decoded !== false) {
            $xml = simplexml_load_string($decoded);
            if ($xml !== false) {
                $refId = (string)($xml->ref_id ?? '');
            } else {
                $json = json_decode($decoded, true);
                if (is_array($json)) {
                    $refId = (string)($json['ref_id'] ?? '');
                }
            }
        }
    }

    $verified = $txnUuid !== '' && $total > 0 ? esewa_verify($txnUuid, $total) : [];

    if ($txnUuid === '' || $total <= 0) {
        set_flash_error(
            'We couldn\'t confirm that payment.',
            'The payment reference was missing, so nothing was recorded.',
            'Go back to the booking and try paying again.',
            'pages/payment.php?booking_id=' . $booking_id
        );
        redirect('pages/my_bookings.php');
    }

    if (($verified['status'] ?? '') !== 'COMPLETE') {
        set_flash_error(
            'Your payment isn\'t confirmed yet.',
            'eSewa hasn\'t confirmed the transaction, so we couldn\'t mark it paid.',
            'Check your eSewa history, then try again if it went through.',
            'pages/payment.php?booking_id=' . $booking_id
        );
        unset($_SESSION['esewa_pending']);
        redirect('pages/my_bookings.php');
    }

    $payment_type   = $pending['payment_type']   ?? 'full';
    $payment_status = $pending['payment_status'] ?? 'paid';
    $amount_paid    = (float)($pending['amount_paid'] ?? $total);

    $stmt = $conn->prepare(
        'UPDATE bookings SET payment_status = ?, payment_type = ?, amount_paid = ?, paid_at = NOW(), payment_method = "online", esewa_txn_uuid = ?, esewa_ref_id = ? WHERE id = ? AND user_id = ?'
    );
    $stmt->bind_param('ssdssii', $payment_status, $payment_type, $amount_paid, $txnUuid, $refId, $booking_id, $_SESSION['user_id']);
    $stmt->execute();

    unset($_SESSION['esewa_pending']);

    notify_user(
        (int)$_SESSION['user_id'],
        $payment_status === 'paid' ? 'Payment received' : 'Advance paid',
        $booking['ground_name'] . ' &middot; Rs ' . number_format($amount_paid, 0) . ' received via eSewa',
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
            $booking['ground_name'] . ' &middot; Rs ' . number_format($amount_paid, 0) . ' received from a player via eSewa',
            'fa-sack-dollar',
            'pages/booking_details.php?id=' . $booking_id
        );
    }

    $playerEmail = $conn->prepare('SELECT email FROM users WHERE id = ?');
    $playerEmail->bind_param('i', $_SESSION['user_id']);
    $playerEmail->execute();
    $playerRow = $playerEmail->get_result()->fetch_assoc();
    if ($playerRow) {
        send_booking_email(
            $playerRow['email'],
            $payment_status === 'paid' ? 'Payment confirmed!' : 'Advance payment received',
            $payment_status === 'paid' ? 'Payment confirmed for your booking' : 'Your advance is confirmed',
            [
                'Booking ref' => $booking['booking_ref'],
                'Court' => $booking['ground_name'],
                'Date' => date('D, M j, Y', strtotime($booking['booking_date'])),
                'Time' => substr($booking['start_time'], 0, 5) . ' - ' . substr($booking['end_time'], 0, 5),
                'Paid' => 'Rs ' . number_format($amount_paid, 0),
            ],
            $payment_status === 'paid'
                ? 'Your booking is fully paid. See you on the court!'
                : 'The rest is payable at the court on game day.'
        );
    }

    if ($mgr && (int)$mgr['manager_id'] !== (int)$_SESSION['user_id']) {
        $mgrEmail = $conn->prepare('SELECT email FROM users WHERE id = ?');
        $mgrEmail->bind_param('i', $mgr['manager_id']);
        $mgrEmail->execute();
        $mgrRow = $mgrEmail->get_result()->fetch_assoc();
        if ($mgrRow) {
            send_booking_email(
                $mgrRow['email'],
                'Payment received: ' . $booking['ground_name'],
                $payment_status === 'paid' ? 'Booking fully paid' : 'Advance paid',
                [
                    'Court' => $booking['ground_name'],
                    'Date' => date('D, M j, Y', strtotime($booking['booking_date'])),
                    'Time' => substr($booking['start_time'], 0, 5) . ' - ' . substr($booking['end_time'], 0, 5),
                    'Received' => 'Rs ' . number_format($amount_paid, 0),
                ],
                'Open your manager dashboard to track your money.'
            );
        }
    }

    set_flash('success', $payment_status === 'paid' ? 'Payment complete!' : 'Advance paid - the rest is due at the court.');
    redirect('pages/confirmation.php?booking_id=' . $booking_id);
}

if ($result === 'failure') {
    unset($_SESSION['esewa_pending']);
    set_flash_error(
        'The eSewa payment was cancelled.',
        'You closed the checkout or the payment wasn\'t completed.',
        'Your booking is still reserved - try again when you\'re ready.',
        'pages/payment.php?booking_id=' . $booking_id
    );
    redirect('pages/payment.php?booking_id=' . $booking_id);
}

set_flash_error(
    'That payment link looks wrong.',
    'The eSewa confirmation URL was missing a valid result.',
    'Open the booking from My Bookings and pay from there.',
    'pages/my_bookings.php'
);
redirect('pages/my_bookings.php');
