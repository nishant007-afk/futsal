<?php
/**
 * Subscription invoice PDF.
 *
 * GET manager/invoice_pdf.php?no=GS-SUB-20261003-4F9K2A
 *
 * Manager-scoped: a manager can only download their own invoices. Reuses the
 * dependency-free ReceiptPdf generator in includes/receipt_pdf.php, so no
 * composer package is involved.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/receipt_pdf.php';
require_manager();

$receiptNo = trim((string)($_GET['no'] ?? ''));
if ($receiptNo === '' || !preg_match('/^[A-Za-z0-9\-]{1,24}$/', $receiptNo)) {
    http_response_code(400);
    exit('Invalid invoice number.');
}

$managerId = (int)$_SESSION['user_id'];

// Scoped to the signed-in manager: another manager's receipt 404s rather than 403s,
// so the endpoint does not confirm that the invoice number exists.
$stmt = $conn->prepare(
    'SELECT p.*, u.name AS manager_name, u.email AS manager_email
     FROM subscription_payments p
     JOIN users u ON u.id = p.manager_id
     WHERE p.receipt_no = ? AND p.manager_id = ?
     LIMIT 1'
);
$stmt->bind_param('si', $receiptNo, $managerId);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();

if (!$inv) {
    http_response_code(404);
    exit('Invoice not found.');
}

$rows = [];
$rows[] = ['GoalSpace', 40, 780, 22, 'b', '111111'];
$rows[] = ['Subscription Invoice', 40, 758, 12, 'n', '6B7280'];
$rows[] = ['Invoice no.', 380, 780, 9, 'n', '6B7280'];
$rows[] = [$inv['receipt_no'], 380, 764, 11, 'b', '111111'];

$rows[] = ['Billed to', 40, 730, 9, 'n', '6B7280'];
$rows[] = [$inv['manager_name'], 40, 715, 11, 'b', '111111'];
$rows[] = [$inv['manager_email'], 40, 700, 9, 'n', '6B7280'];

$rows[] = ['Payment date', 380, 730, 9, 'n', '6B7280'];
$rows[] = [date('M j, Y', strtotime($inv['paid_at'])), 380, 715, 11, 'n', '111111'];

$rows[] = ['------------------------------------------------------------------------------------------------------------------------', 40, 676, 8, 'n', 'D1D5DB'];

$rows[] = ['Description', 40, 654, 9, 'n', '6B7280'];
$rows[] = [subscription_kind_label($inv['kind']) . ' - Venue Partner Tier', 40, 639, 11, 'b', '111111'];

$rows[] = ['Payment channel', 40, 615, 9, 'n', '6B7280'];
$rows[] = [subscription_channel_label($inv['channel']), 40, 600, 11, 'n', '111111'];

$rows[] = ['Transaction reference', 380, 615, 9, 'n', '6B7280'];
$rows[] = [$inv['txn_ref'], 380, 600, 11, 'n', '111111'];

if (!empty($inv['period_start']) && !empty($inv['period_end'])) {
    $rows[] = ['Period covered', 40, 570, 9, 'n', '6B7280'];
    $rows[] = [
        date('M j, Y', strtotime($inv['period_start'])) . ' - ' . date('M j, Y', strtotime($inv['period_end'])),
        40, 555, 11, 'n', '111111',
    ];
}

$rows[] = ['------------------------------------------------------------------------------------------------------------------------', 40, 524, 8, 'n', 'D1D5DB'];

$rows[] = ['Status', 40, 500, 9, 'n', '6B7280'];
$rows[] = ['PAID', 40, 485, 11, 'b', '16A35A'];

$rows[] = ['Total paid', 380, 500, 9, 'n', '6B7280'];
$rows[] = ['Rs ' . number_format((float)$inv['amount'], 2), 380, 485, 13, 'b', '111111'];

$rows[] = ['------------------------------------------------------------------------------------------------------------------------', 40, 462, 8, 'n', 'D1D5DB'];

$rows[] = ['This invoice was generated from GoalSpace on ' . date('M j, Y g:i A'), 40, 440, 9, 'n', '9CA3AF'];
$rows[] = ['Questions? Contact GoalSpace support with your invoice number.', 40, 426, 9, 'n', '9CA3AF'];

$pdf = new ReceiptPdf();
$pdf->content($rows);
$output = $pdf->output();

$filename = 'invoice-' . $inv['receipt_no'] . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($output));
header('Cache-Control: private, no-store');
echo $output;
exit;
