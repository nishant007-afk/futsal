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

/**
 * Platform commission model (configurable in `settings`).
 * Managers keep (100 - fee)% of each booking's gross; the platform keeps the rest.
 */
function platform_fee_percent(): float
{
    return max(0.0, min(100.0, (float)setting('platform_fee_percent', '10')));
}

function platform_fee_amount(float $gross): float
{
    return round($gross * (platform_fee_percent() / 100.0), 2);
}

function manager_payout(float $gross): float
{
    return round($gross - platform_fee_amount($gross), 2);
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
