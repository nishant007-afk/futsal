<?php

function setting(string $key, string $default = ''): string
{
    global $conn;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($conn->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return isset($cache[$key]) ? (string)$cache[$key] : $default;
}

function manager_setup_fee(): float
{
    return max(0, (float)setting('manager_setup_fee', '2500'));
}

function manager_monthly_fee(): float
{
    return max(0, (float)setting('manager_monthly_fee', '800'));
}

function manager_subscription(int $manager_id): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM manager_subscriptions WHERE manager_id = ?');
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    $sub = $stmt->get_result()->fetch_assoc();
    return $sub ?: null;
}

function create_manager_subscription(int $manager_id, bool $setup_prepaid = false): void
{
    global $conn;
    $setup = manager_setup_fee();
    $monthly = manager_monthly_fee();
    $setupDate = $setup_prepaid ? date('Y-m-d') : null;
    $setupPaidDate = $setup_prepaid ? date('Y-m-d') : null;
    $periodEnd = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO manager_subscriptions (manager_id, setup_fee, setup_paid_at, monthly_fee, period_start, period_end)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $manager_id, $setup, $setupPaidDate, $monthly, $setupDate, $periodEnd);
    $stmt->execute();
}

function subscription_status(int $manager_id): array
{
    $sub = manager_subscription($manager_id);
    if (!$sub) {
        return ['key' => 'none', 'label' => 'No subscription', 'active' => false, 'sub' => null];
    }
    $active = true;
    $key = 'active';
    $label = 'Active';
    if ($sub['setup_paid_at'] === null) {
        $active = false;
        $key = 'setup_pending';
        $label = 'Setup fee due';
    } elseif ($sub['period_end'] && $sub['period_end'] < date('Y-m-d')) {
        $active = false;
        $key = 'overdue';
        $label = 'Overdue';
    }
    return ['key' => $key, 'label' => $label, 'active' => $active, 'sub' => $sub];
}

function is_subscription_active(int $manager_id): bool
{
    return subscription_status($manager_id)['active'];
}

function sync_subscription_grounds(): void
{
    $now = time();
    if (isset($_SESSION['last_sub_sync']) && $now - (int)$_SESSION['last_sub_sync'] < 300) {
        return;
    }
    $_SESSION['last_sub_sync'] = $now;

    global $conn;
    $conn->query(
        'UPDATE grounds g
         JOIN manager_subscriptions s ON s.manager_id = g.manager_id
         SET g.is_active = 0
         WHERE NOT (s.setup_paid_at IS NOT NULL AND (s.period_end IS NULL OR s.period_end >= CURDATE()))'
    );
}

function save_setting(string $key, string $value): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

/* ------------------------------------------------------------------
   Subscription invoice history (subscription_payments)
   One row per setup-fee payment or renewal that an admin confirms in
   admin/settlements.php. Powers the Recent Invoices table and the PDF
   receipt on manager/subscription.php.
   ------------------------------------------------------------------ */

/** @return array<int,string> */
function subscription_payment_channels(): array
{
    return ['esewa', 'khalti', 'bank'];
}

function subscription_channel_label(string $channel): string
{
    $labels = [
        'esewa' => 'eSewa',
        'khalti' => 'Khalti',
        'bank' => 'Bank transfer',
    ];
    return $labels[$channel] ?? ucfirst($channel);
}

function subscription_kind_label(string $kind): string
{
    return $kind === 'setup' ? 'Setup fee' : 'Renewal';
}

/** Human-readable invoice number, e.g. GS-SUB-20261003-4F9K2A. */
function subscription_receipt_no(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix = '';
    for ($i = 0; $i < 6; $i++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'GS-SUB-' . date('Ymd') . '-' . $suffix;
}

/**
 * Record a confirmed subscription payment.
 *
 * The receipt number is unique, so retry on the astronomically unlikely collision
 * rather than failing the admin's submission.
 *
 * @return array{ok:bool,receipt_no?:string,error?:string}
 */
function record_subscription_payment(
    int $manager_id,
    string $kind,
    float $amount,
    string $channel,
    string $txn_ref,
    ?string $period_start = null,
    ?string $period_end = null
): array {
    global $conn;

    $kind = $kind === 'setup' ? 'setup' : 'renewal';
    if (!in_array($channel, subscription_payment_channels(), true)) {
        return ['ok' => false, 'error' => 'Choose a valid payment channel.'];
    }
    $txn_ref = trim($txn_ref);
    if ($txn_ref === '') {
        return ['ok' => false, 'error' => 'Enter the transaction reference from the payment receipt.'];
    }
    if (mb_strlen($txn_ref) > 80) {
        return ['ok' => false, 'error' => 'Transaction reference is too long (max 80 characters).'];
    }
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Payment amount must be greater than zero.'];
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $receipt_no = subscription_receipt_no();
        $stmt = $conn->prepare(
            'INSERT INTO subscription_payments
                (manager_id, kind, amount, channel, txn_ref, receipt_no, period_start, period_end, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())'
        );
        $stmt->bind_param(
            'isssssss',
            $manager_id,
            $kind,
            $amount,
            $channel,
            $txn_ref,
            $receipt_no,
            $period_start,
            $period_end
        );
        if ($stmt->execute()) {
            return ['ok' => true, 'receipt_no' => $receipt_no];
        }
        // Duplicate receipt number -> try another. Anything else is a real failure.
        if ($conn->errno !== 1062) {
            return ['ok' => false, 'error' => 'Could not save the invoice record.'];
        }
    }
    return ['ok' => false, 'error' => 'Could not allocate an invoice number. Please try again.'];
}

/**
 * Invoice history for one manager, newest first.
 *
 * @return array<int,array<string,mixed>>
 */
function manager_subscription_payments(int $manager_id, int $limit = 50): array
{
    global $conn;
    $limit = max(1, min(200, $limit));
    $stmt = $conn->prepare(
        'SELECT id, kind, amount, channel, txn_ref, receipt_no, period_start, period_end, paid_at
         FROM subscription_payments
         WHERE manager_id = ?
         ORDER BY paid_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Lifetime totals shown next to the invoice table. */
function manager_subscription_payment_summary(int $manager_id): array
{
    global $conn;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) c, COALESCE(SUM(amount), 0) s
         FROM subscription_payments WHERE manager_id = ?"
    );
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: ['c' => 0, 's' => 0];
    return ['count' => (int)$row['c'], 'total' => (float)$row['s']];
}
