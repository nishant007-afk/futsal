<?php

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_price($amount): string
{
    return 'Rs. ' . number_format((float)$amount, 2);
}

function validate_promo_code(string $code, float $total, int $ground_id, ?int $user_id = null): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM promo_codes WHERE code = ?');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$promo || (int)$promo['is_active'] !== 1) {
        return ['error' => 'That promo code is invalid.'];
    }
    $groundStmt = $conn->prepare('SELECT manager_id FROM grounds WHERE id = ?');
    $groundStmt->bind_param('i', $ground_id);
    $groundStmt->execute();
    $ground = $groundStmt->get_result()->fetch_assoc();
    $groundStmt->close();
    $promoManagerId = $ground ? (int)$ground['manager_id'] : 0;
    if ((int)$promo['manager_id'] !== $promoManagerId || $promoManagerId === 0) {
        return ['error' => 'That promo code doesn\'t apply to this court.'];
    }
    $today = date('Y-m-d');
    if ($promo['starts_at'] && $today < $promo['starts_at']) {
        return ['error' => 'That promo code has not started yet.'];
    }
    if ($promo['expires_at'] && $today > $promo['expires_at']) {
        return ['error' => 'That promo code has expired.'];
    }
    if ($promo['max_uses'] > 0 && (int)$promo['used_count'] >= (int)$promo['max_uses']) {
        return ['error' => 'That promo code has reached its usage limit.'];
    }
    if ((float)$promo['min_total'] > 0 && $total < (float)$promo['min_total']) {
        return ['error' => 'This promo needs a minimum booking of Rs ' . number_format((float)$promo['min_total'], 0) . '.'];
    }
    // Prevent coupon abuse: each user can only redeem a promo code once across active bookings
    if ($user_id !== null && $user_id > 0) {
        $uStmt = $conn->prepare('SELECT COUNT(*) FROM bookings WHERE promo_id = ? AND user_id = ? AND status != "cancelled" AND payment_status IN ("partial", "paid")');
        $uStmt->bind_param('ii', $promo['id'], $user_id);
        $uStmt->execute();
        $uStmt->bind_result($priorUses);
        $uStmt->fetch();
        $uStmt->close();
        if ($priorUses > 0) {
            return ['error' => 'You have already redeemed this promo code on a previous booking.'];
        }
    }
    $discount = $promo['discount_type'] === 'percent'
        ? round($total * (float)$promo['discount_value'] / 100, 2)
        : min((float)$promo['discount_value'], $total);
    return ['promo' => $promo, 'discount' => round($discount, 2)];
}

function increment_promo_usage(int $promo_id): bool
{
    global $conn;
    // Atomic + capped: only increment when under max_uses (prevents overshoot on race).
    $stmt = $conn->prepare('UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ? AND (max_uses <= 0 OR used_count < max_uses)');
    $stmt->bind_param('i', $promo_id);
    $stmt->execute();
    $ok = ($stmt->affected_rows > 0);
    $stmt->close();
    return $ok;
}

function manager_promo_codes(int $manager_id): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM promo_codes WHERE manager_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $manager_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function slot_is_taken(int $ground_id, string $booking_date, string $start_time): bool
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT id FROM bookings
         WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND status != "cancelled"'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function on_waitlist(int $ground_id, string $booking_date, string $start_time, int $user_id): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM waitlist WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND user_id = ?');
    $stmt->bind_param('issi', $ground_id, $booking_date, $start_time, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function waitlist_count(int $ground_id, string $booking_date, string $start_time): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM waitlist WHERE ground_id = ? AND booking_date = ? AND start_time = ?');
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function waitlist_position(int $ground_id, string $booking_date, string $start_time, int $user_id): ?int
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT w.user_id FROM waitlist w
         WHERE w.ground_id = ? AND w.booking_date = ? AND w.start_time = ?
         ORDER BY w.created_at ASC'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $i => $row) {
        if ((int)$row['user_id'] === $user_id) {
            return $i + 1;
        }
    }
    return null;
}

function notify_waitlist_freed(int $ground_id, string $booking_date, string $start_time): void
{
    global $conn;
    $gStmt = $conn->prepare('SELECT name FROM grounds WHERE id = ?');
    $gStmt->bind_param('i', $ground_id);
    $gStmt->execute();
    $groundName = $gStmt->get_result()->fetch_assoc()['name'] ?? 'the court';
    $label = date('D, M j', strtotime($booking_date)) . ' at ' . substr($start_time, 0, 5);
    $stmt = $conn->prepare(
        'SELECT id, user_id FROM waitlist
         WHERE ground_id = ? AND booking_date = ? AND start_time = ? AND is_notified = 0
         ORDER BY id ASC LIMIT 50'
    );
    $stmt->bind_param('iss', $ground_id, $booking_date, $start_time);
    $stmt->execute();
    $rows = $stmt->get_result();
    while ($row = $rows->fetch_assoc()) {
        notify_user((int)$row['user_id'], 'A slot opened up!', $groundName . ' is free on ' . $label . '. Book before someone else does.', 'fa-bell', 'pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
        $uStmt = $conn->prepare('SELECT email, name FROM users WHERE id = ?');
        $uStmt->bind_param('i', $row['user_id']);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        if ($uRow) {
            $bookUrl = absolute_url('pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
            send_booking_email(
                $uRow['email'],
                'A slot opened at ' . $groundName,
                'Your waitlist spot just freed up',
                [
                    'Court'      => $groundName,
                    'Date'       => date('D, M j, Y', strtotime($booking_date)),
                    'Time'       => substr($start_time, 0, 5) . ' onwards',
                    'Book now'   => $bookUrl,
                ],
                'This slot is now available on a first-come, first-served basis. Tap the link above to reserve it before someone else books it.',
                $uRow['name'] ?? ''
            );
        }
        $upd = $conn->prepare('UPDATE waitlist SET is_notified = 1 WHERE id = ?');
        $upd->bind_param('i', $row['id']);
        $upd->execute();
    }
}

function date_is_blocked(int $ground_id, string $date): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM blocked_dates WHERE ground_id = ? AND block_date = ?');
    $stmt->bind_param('is', $ground_id, $date);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function ground_slot_settings(int $ground_id): array
{
    global $conn;
    $default = ['open_time' => '08:00:00', 'close_time' => '22:00:00', 'slot_interval' => 60];
    if ($ground_id <= 0) {
        return $default;
    }
    $stmt = $conn->prepare('SELECT open_time, close_time, slot_interval FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: $default;
}

function ground_price_for_date(int $ground_id, float $base_price, string $date): float
{
    global $conn;
    $stmt = $conn->prepare('SELECT price_weekend, discount_price FROM grounds WHERE id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $discounted = $row['discount_price'] ?? null;
    $effective = $base_price;
    $hasDiscount = ($discounted !== null && (float)$discounted > 0 && (float)$discounted < (float)$base_price);
    if ($hasDiscount) {
        $effective = (float)$discounted;
    }
    $day = (int)date('N', strtotime($date)); // 1=Mon .. 7=Sun
    if ($day >= 6 && $row['price_weekend'] !== null && (float)$row['price_weekend'] > 0) {
        $weekend = (float)$row['price_weekend'];
        // Discount still applies on weekends: charge the lower of the two.
        if ($hasDiscount) {
            return min($effective, $weekend);
        }
        return $weekend;
    }
    return $effective;
}

function slots_for_day($date, $ground_id): array
{
    $s = ground_slot_settings((int)$ground_id);
    $open = (int)substr($s['open_time'], 0, 2) * 60 + (int)substr($s['open_time'], 3, 2);
    $close = (int)substr($s['close_time'], 0, 2) * 60 + (int)substr($s['close_time'], 3, 2);
    $interval = max(15, (int)$s['slot_interval']);
    $slots = [];
    for ($t = $open; $t + $interval <= $close; $t += $interval) {
        $startMin = $t;
        $endMin = $t + $interval;
        $start = sprintf('%02d:%02d:00', intdiv($startMin, 60), $startMin % 60);
        $end = sprintf('%02d:%02d:00', intdiv($endMin, 60), $endMin % 60);
        $label = sprintf('%02d:%02d - %02d:%02d', intdiv($startMin, 60), $startMin % 60, intdiv($endMin, 60), $endMin % 60);
        $slots[] = ['start' => $start, 'end' => $end, 'label' => $label];
    }
    return $slots;
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Add at least one capital letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Add at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Add at least one special character (like ! or @).';
    }
    return null;
}
