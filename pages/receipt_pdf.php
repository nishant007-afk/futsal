<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/receipt_pdf.php';

require_player();

$booking_id = (int)($_GET['booking_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT b.id, b.booking_ref, b.booking_date, b.start_time, b.end_time, b.total_price, b.status,
            b.payment_status, b.amount_paid, b.paid_at, b.discount, b.promo_code,
            g.name AS ground_name, g.location, g.slug AS slug, u.name AS owner_name
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

$netTotal = (float)$booking['total_price'] - (float)$booking['discount'];
$paid = (float)$booking['amount_paid'];
$remaining = max(0, $netTotal - $paid);

$rows = [];

// Brand header
$rows[] = ['PAYMENT RECEIPT', 40, 795, 11, 'n', '6B7280'];
$rows[] = ['Show this at the court when you arrive', 40, 756, 9, 'n', '9CA3AF'];

// divider
$rows[] = ['----------------------------------------------------------------------------------------------------------------------', 40, 742, 8, 'n', 'D1D5DB'];

// Details
$rows[] = ['Booking reference', 40, 718, 9, 'n', '6B7280'];
$rows[] = [$booking['booking_ref'], 300, 718, 12, 'b', '111111'];

$rows[] = ['Court', 40, 694, 9, 'n', '6B7280'];
$rows[] = [$booking['ground_name'], 300, 694, 12, 'b', '111111'];

$rows[] = ['Managed by', 40, 670, 9, 'n', '6B7280'];
$rows[] = [ground_owner_label($booking) ?: '-', 300, 670, 12, 'n', '111111'];

$rows[] = ['Location', 40, 646, 9, 'n', '6B7280'];
$rows[] = [$booking['location'], 300, 646, 12, 'n', '111111'];

$rows[] = ['Date', 40, 622, 9, 'n', '6B7280'];
$rows[] = [date('l, M j, Y', strtotime($booking['booking_date'])), 300, 622, 12, 'n', '111111'];

$rows[] = ['Time', 40, 598, 9, 'n', '6B7280'];
$rows[] = [substr($booking['start_time'], 0, 5) . ' - ' . substr($booking['end_time'], 0, 5), 300, 598, 12, 'n', '111111'];

// divider
$rows[] = ['----------------------------------------------------------------------------------------------------------------------', 40, 574, 8, 'n', 'D1D5DB'];

// Money
$rows[] = ['Booking total', 40, 550, 10, 'n', '111111'];
$rows[] = ['Rs ' . number_format((float)$booking['total_price'], 2), 400, 550, 12, 'n', '111111'];

if ((float)$booking['discount'] > 0) {
    $rows[] = ['Promo ' . $booking['promo_code'], 40, 532, 10, 'n', '111111'];
    $rows[] = ['- Rs ' . number_format((float)$booking['discount'], 2), 400, 532, 12, 'n', '16A35A'];
}

$payLabel = $booking['payment_status'] === 'paid' ? 'Amount paid (FULL)' : 'Amount paid (ADVANCE)';
$rows[] = [$payLabel, 40, 514, 11, 'b', '111111'];
$rows[] = ['Rs ' . number_format($paid, 2), 400, 514, 14, 'b', '16A35A'];

if ($booking['payment_status'] === 'partial') {
    $rows[] = ['Balance due at court', 40, 496, 10, 'n', '6B7280'];
    $rows[] = ['Rs ' . number_format($remaining, 2), 400, 496, 12, 'n', 'B45309'];
}

if ($booking['paid_at']) {
    $rows[] = ['Received on', 40, 466, 9, 'n', '6B7280'];
    $rows[] = [date('M j, Y g:i A', strtotime($booking['paid_at'])), 300, 466, 11, 'n', '111111'];
}

$rows[] = ['Payment status', 40, 448, 9, 'n', '6B7280'];
$rows[] = [strtoupper($booking['payment_status']), 300, 448, 11, 'b', $booking['payment_status'] === 'paid' ? '16A35A' : 'B45309'];

// divider
$rows[] = ['----------------------------------------------------------------------------------------------------------------------', 40, 424, 8, 'n', 'D1D5DB'];

$rows[] = ['This receipt was generated from GoalSpace on ' . date('M j, Y g:i A'), 40, 402, 9, 'n', '9CA3AF'];
$rows[] = ['Questions? Contact the court manager directly.', 40, 388, 9, 'n', '9CA3AF'];

$pdf = new ReceiptPdf();
$pdf->content($rows);
$output = $pdf->output();

$filename = 'receipt-' . $booking['booking_ref'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));
echo $output;
exit;
