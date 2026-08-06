<?php

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id, name, email, phone, avatar, role, email_verified, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function is_admin(): bool
{
    return is_role('admin');
}

function is_manager(): bool
{
    return is_role('manager');
}

function is_player(): bool
{
    return is_role('user');
}

function is_role(string $role): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === $role;
}

function user_owns_ground(int $ground_id): bool
{
    if (is_admin()) {
        return true;
    }
    if (!is_manager()) {
        return false;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT id FROM grounds WHERE id = ? AND manager_id = ?');
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('pages/login.php'));
        exit;
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function require_manager(): void
{
    if (!is_manager()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function require_player(): void
{
    if (!is_player()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

function base_url(string $path = ''): string
{
    $root = '/futsal';
    return $root . '/' . ltrim($path, '/');
}

function grounds_list_url(): string
{
    return is_logged_in() ? base_url('pages/courts.php') : base_url('index.php#grounds');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function set_flash(string $type, string $message, ?array $detail = null): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message, 'detail' => $detail];
}

function set_flash_error(string $what, ?string $why = null, ?string $how = null, ?string $how_url = null): void
{
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => $what,
        'detail' => ['why' => $why, 'how' => $how, 'how_url' => $how_url],
    ];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request. Please go back and try again.');
    }
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function release_stale_bookings(): void
{
    $now = time();
    if (isset($_SESSION['last_cleanup']) && $now - (int)$_SESSION['last_cleanup'] < 300) {
        return;
    }
    $_SESSION['last_cleanup'] = $now;

    global $conn;
    $conn->query(
        "UPDATE bookings
         SET status = 'cancelled'
         WHERE status = 'confirmed'
           AND payment_status = 'unpaid'
           AND TIMESTAMP(booking_date, start_time) < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_price($amount): string
{
    return 'Rs. ' . number_format((float)$amount, 2);
}

function validate_promo_code(string $code, float $total, int $ground_id): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM promo_codes WHERE code = ?');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();
    if (!$promo || (int)$promo['is_active'] !== 1) {
        return ['error' => 'That promo code is invalid.'];
    }
    $groundStmt = $conn->prepare('SELECT manager_id FROM grounds WHERE id = ?');
    $groundStmt->bind_param('i', $ground_id);
    $groundStmt->execute();
    $ground = $groundStmt->get_result()->fetch_assoc();
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
    $discount = $promo['discount_type'] === 'percent'
        ? round($total * (float)$promo['discount_value'] / 100, 2)
        : min((float)$promo['discount_value'], $total);
    return ['promo' => $promo, 'discount' => round($discount, 2)];
}

function increment_promo_usage(int $promo_id): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?');
    $stmt->bind_param('i', $promo_id);
    $stmt->execute();
}

function promo_codes_list(): array
{
    global $conn;
    return $conn->query('SELECT * FROM promo_codes ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
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

function notify_waitlist_freed(int $ground_id, string $booking_date, string $start_time): void
{
    global $conn;
    $groundName = $conn->query('SELECT name FROM grounds WHERE id = ' . (int)$ground_id)->fetch_assoc()['name'] ?? 'the court';
    $label = date('D, M j', strtotime($booking_date)) . ' at ' . substr($start_time, 0, 5);
    $rows = $conn->query(
        'SELECT id, user_id FROM waitlist
         WHERE ground_id = ' . (int)$ground_id . ' AND booking_date = "' . $conn->real_escape_string($booking_date) . '" AND start_time = "' . $conn->real_escape_string($start_time) . '" AND is_notified = 0
         ORDER BY id ASC'
    );
    while ($row = $rows->fetch_assoc()) {
        notify_user((int)$row['user_id'], 'A slot opened up!', $groundName . ' is free on ' . $label . '. Book before someone else does.', 'fa-bell', 'pages/ground.php?id=' . (int)$ground_id . '&date=' . urlencode($booking_date));
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
    $day = (int)date('N', strtotime($date)); // 1=Mon .. 7=Sun
    if ($day >= 6) {
        $stmt = $conn->prepare('SELECT price_weekend FROM grounds WHERE id = ?');
        $stmt->bind_param('i', $ground_id);
        $stmt->execute();
        $weekend = $stmt->get_result()->fetch_assoc()['price_weekend'] ?? null;
        if ($weekend !== null && (float)$weekend > 0) {
            return (float)$weekend;
        }
    }
    return (float)$base_price;
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
        return 'Use at least 8 characters.';
    }
    if (strlen($password) > 72) {
        return 'Passwords must be 72 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return 'Add at least one letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Add at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Add at least one special character (like ! or @).';
    }
    return null;
}

function ground_images(int $ground_id): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM ground_images WHERE ground_id = ? ORDER BY id');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ground_cover(int $ground_id): ?string
{
    global $conn;
    $stmt = $conn->prepare('SELECT image FROM ground_images WHERE ground_id = ? ORDER BY id LIMIT 1');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['image'] : null;
}

function ground_reviews(int $ground_id): array
{
    global $conn;
    $stmt = $conn->prepare(
        'SELECT r.*, u.name AS user_name, u.avatar
         FROM reviews r JOIN users u ON u.id = r.user_id
         WHERE r.ground_id = ? ORDER BY r.created_at DESC'
    );
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ground_rating(int $ground_id): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT AVG(rating) AS avg, COUNT(*) AS count FROM reviews WHERE ground_id = ?');
    $stmt->bind_param('i', $ground_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return [
        'avg' => $row['avg'] !== null ? round((float)$row['avg'], 1) : null,
        'count' => (int)$row['count'],
    ];
}

function user_rating_for(int $ground_id): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM reviews WHERE ground_id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function user_has_played(int $ground_id): bool
{
    if (!is_logged_in()) {
        return false;
    }
    global $conn;
    $stmt = $conn->prepare(
        'SELECT id FROM bookings
         WHERE ground_id = ? AND user_id = ? AND status = "confirmed"
           AND booking_date < CURDATE()
         LIMIT 1'
    );
    $stmt->bind_param('ii', $ground_id, $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function booking_refund_policy(string $booking_date, string $start_time, float $amount_paid): array
{
    $slot = strtotime($booking_date . ' ' . $start_time);
    $hoursLeft = ($slot - time()) / 3600;

    if ($hoursLeft >= 24) {
        return [
            'allowed' => true,
            'refund' => $amount_paid,
            'fee' => 0.0,
            'label' => 'Free cancellation with full refund.',
        ];
    }
    if ($hoursLeft > 0) {
        $fee = round($amount_paid * 0.5, 2);
        return [
            'allowed' => true,
            'refund' => round($amount_paid - $fee, 2),
            'fee' => $fee,
            'label' => 'Less than 24 hours before your game. A 50% cancellation fee applies.',
        ];
    }
    return [
        'allowed' => false,
        'refund' => 0.0,
        'fee' => 0.0,
        'label' => 'This booking has already started and can no longer be cancelled online.',
    ];
}

function save_ground_photos(int $ground_id): array
{
    global $conn;
    $files = $_FILES['photos'] ?? [];
    $names = is_array($files['name'] ?? null) ? $files['name'] : [];
    $tmps = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
    $errs = is_array($files['error'] ?? null) ? $files['error'] : [];
    $uploaded = 0;
    $failed = 0;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < count($names); $i++) {
        if (($errs[$i] ?? 1) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($errs[$i] ?? 1) !== UPLOAD_ERR_OK) {
            $failed++;
            continue;
        }
        $mime = finfo_file($finfo, $tmps[$i]);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            $failed++;
            continue;
        }
        $ext = $allowed[$mime];
        $filename = 'ground_' . $ground_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($tmps[$i], __DIR__ . '/../uploads/grounds/' . $filename)) {
            $stmt = $conn->prepare('INSERT INTO ground_images (ground_id, image) VALUES (?, ?)');
            $stmt->bind_param('is', $ground_id, $filename);
            if ($stmt->execute()) {
                $uploaded++;
            } else {
                $failed++;
            }
        } else {
            $failed++;
        }
    }
    finfo_close($finfo);
    return [$uploaded, $failed];
}

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
    $periodEnd = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO manager_subscriptions (manager_id, setup_fee, setup_paid_at, monthly_fee, period_start, period_end)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('idssss', $manager_id, $setup, $setupDate, $monthly, $setup_prepaid ? date('Y-m-d') : null, $periodEnd);
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

function notify_user(int $user_id, string $title, string $body = '', string $icon = 'fa-bell', string $link = ''): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, body, icon, link) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issss', $user_id, $title, $body, $icon, $link);
    $stmt->execute();
}

function user_notifications(int $user_id, int $limit = 20): array
{
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->bind_param('ii', $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function unread_notification_count(int $user_id): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['c'];
}

function notification_time(?string $created_at): string
{
    if (!$created_at) {
        return '';
    }
    $ts = strtotime($created_at);
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 172800) {
        return 'yesterday';
    }
    return date('M j', $ts);
}

function notification_icon_color(string $icon): string
{
    $map = [
        'fa-calendar-check' => 'green',
        'fa-store' => 'blue',
        'fa-sack-dollar' => 'gold',
        'fa-circle-xmark' => 'red',
        'fa-bell' => 'brand',
        'fa-calendar-xmark' => 'red',
    ];
    return $map[$icon] ?? 'brand';
}

function mark_notifications_read(int $user_id): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
}

/**
 * Login attempt throttling.
 * Tracks failures per identifier (email) + IP.
 * Lockout base: 60s after 5 fails, +30s per extra failure (max 10min).
 * After 10+ fails the identifier is marked suspended.
 */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function login_attempt_key(string $email): string
{
    return strtolower(trim($email)) . '|' . client_ip();
}

function login_lock_seconds(int $failures): int
{
    if ($failures < 5) {
        return 0;
    }
    $extra = $failures - 5;
    return min(600, 60 + $extra * 30);
}

function get_login_attempt(string $key): ?array
{
    global $conn;
    $stmt = $conn->prepare('SELECT attempts, locked_until, suspended FROM login_attempts WHERE identifier = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    if ($row['locked_until'] && strtotime($row['locked_until']) <= time()) {
        $stmt = $conn->prepare('UPDATE login_attempts SET locked_until = NULL WHERE identifier = ?');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row['locked_until'] = null;
    }
    return $row;
}

function record_login_failure(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('INSERT INTO login_attempts (identifier, ip, attempts, locked_until) VALUES (?, ?, 1, NULL) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW()');
    $ip = client_ip();
    $stmt->bind_param('ss', $key, $ip);
    $stmt->execute();

    $row = get_login_attempt($key);
    $failures = (int)($row['attempts'] ?? 0);
    if ($failures >= 5) {
        $lockUntil = date('Y-m-d H:i:s', time() + login_lock_seconds($failures));
        $stmt = $conn->prepare('UPDATE login_attempts SET locked_until = ? WHERE identifier = ?');
        $stmt->bind_param('ss', $lockUntil, $key);
        $stmt->execute();
    }
}

function login_attempt_state(string $key): array
{
    $row = get_login_attempt($key);
    $failures = (int)($row['attempts'] ?? 0);
    $state = [
        'failures' => $failures,
        'locked' => false,
        'suspended' => !empty($row['suspended']),
        'lock_seconds' => 0,
        'lock_ends' => null,
    ];
    if (!empty($row['suspended']) || $failures > 10) {
        if (!$row['suspended'] && $failures > 10) {
            suspend_login_identifier($key);
        }
        $state['suspended'] = true;
        $state['locked'] = true;
        return $state;
    }
    if ($failures >= 5) {
        $lockUntil = $row['locked_until'] ? strtotime($row['locked_until']) : time() + login_lock_seconds($failures);
        $remaining = max(0, $lockUntil - time());
        $state['locked'] = true;
        $state['lock_seconds'] = $remaining;
        $state['lock_ends'] = date('c', $lockUntil);
    }
    return $state;
}

function clear_login_attempts(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

function suspend_login_identifier(string $key): void
{
    global $conn;
    $stmt = $conn->prepare('UPDATE login_attempts SET suspended = 1, locked_until = NULL WHERE identifier = ?');
    $stmt->bind_param('s', $key);
    $stmt->execute();
}

function login_attempts_email(string $key): string
{
    return explode('|', $key)[0];
}

function issue_otp(string $identifier, string $purpose, int $ttlSeconds = 300): string
{
    global $conn;
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE identifier = ? AND purpose = ? AND used = 0');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $conn->prepare('INSERT INTO otps (identifier, purpose, code, attempts, expires_at) VALUES (?, ?, ?, 0, ?)');
    $stmt->bind_param('ssss', $identifier, $purpose, $code, $expires);
    $stmt->execute();
    return $code;
}

function verify_otp(string $identifier, string $purpose, string $code): bool
{
    global $conn;
    $stmt = $conn->prepare('SELECT id, code, expires_at, used, attempts FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return false;
    }
    if (strtotime($row['expires_at']) < time()) {
        $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE id = ?');
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        return false;
    }
    if ((int)$row['attempts'] >= 5) {
        return false;
    }
    if (!hash_equals($row['code'], $code)) {
        $stmt = $conn->prepare('UPDATE otps SET attempts = attempts + 1 WHERE id = ?');
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        return false;
    }
    $stmt = $conn->prepare('UPDATE otps SET used = 1 WHERE id = ?');
    $stmt->bind_param('i', $row['id']);
    $stmt->execute();
    return true;
}

function otp_attempts_left(string $identifier, string $purpose): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT attempts FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 5;
    }
    return max(0, 5 - (int)$row['attempts']);
}

function otp_remaining_seconds(string $identifier, string $purpose): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT expires_at FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 0;
    }
    return max(0, strtotime($row['expires_at']) - time());
}

function otp_send_cooldown(string $identifier, string $purpose, int $cooldown = 30): int
{
    global $conn;
    $stmt = $conn->prepare('SELECT created_at FROM otps WHERE identifier = ? AND purpose = ? AND used = 0 ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('ss', $identifier, $purpose);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return 0;
    }
    return max(0, $cooldown - (time() - strtotime($row['created_at'])));
}

function otp_subject(string $purpose): string
{
    switch ($purpose) {
        case 'email_verify':
            return 'Verify your email';
        case 'password_reset':
            return 'Your password reset code';
        case 'login':
            return 'Your login code';
        case 'password_change':
            return 'Your security code';
        default:
            return 'Your GoalSpace security code';
    }
}


function send_otp_mail(string $email, string $code, string $purpose): bool
{
    $title = otp_subject($purpose);
    $body = 'Hi there,' . "\r\n\r\n";
    $body .= 'You asked to ' . otp_purpose_verb($purpose) . '. Here is your code:' . "\r\n\r\n";
    $body .= '<span style="font-size:22px;font-weight:bold;letter-spacing:3px;">' . $code . '</span>' . "\r\n\r\n";
    $body .= 'This code works for the next 5 minutes.' . "\r\n\r\n";
    $body .= "If this wasn't you, just ignore this email. Nothing will change." . "\r\n\r\n";
    $body .= 'See you on the court!' . "\r\n\r\n";
    $body .= 'The GoalSpace team';
    return send_mail($email, $title, $body);
}

function otp_purpose_verb(string $purpose): string
{
    switch ($purpose) {
        case 'email_verify':
            return 'verify your email address';
        case 'password_reset':
            return 'reset your password';
        case 'login':
            return 'sign in to your account';
        case 'password_change':
            return 'change your password';
        default:
            return 'continue with what you were doing';
    }
}

function export_csv(array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/**
 * Export rows as a real .xlsx file without any PHP extension.
 * Builds the OOXML parts and wraps them in a ZIP archive (STORED, no compression).
 */
function export_excel(array $rows, string $filename): void
{
    $escape = function (string $s): string {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    // shared strings
    $shared = [];
    $sheetRows = [];
    foreach ($rows as $row) {
        $cells = '';
        foreach ($row as $cell) {
            $v = (string)$cell;
            $idx = array_search($v, $shared, true);
            if ($idx === false) {
                $shared[] = $v;
                $idx = count($shared) - 1;
            }
            $cells .= '<c t="s"><v>' . $idx . '</v></c>';
        }
        $sheetRows[] = '<row>' . $cells . '</row>';
    }
    $sharedXml = '';
    foreach ($shared as $s) {
        $sharedXml .= '<si><t xml:space="preserve">' . $escape($s) . '</t></si>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '</styleSheet>',
        'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($shared) . '" uniqueCount="' . count($shared) . '">'
            . $sharedXml . '</sst>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . implode('', $sheetRows) . '</sheetData></worksheet>',
    ];

    // Build ZIP (STORED)
    $zipData = '';
    $central = '';
    $offset = 0;
    foreach ($files as $name => $content) {
        $name = str_replace('\\', '/', $name);
        $nameBytes = $content;
        $crc = crc32($nameBytes);
        $size = strlen($nameBytes);
        $nameLen = strlen($name);
        // local file header
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0) . $name . $nameBytes;
        $zipData .= $local;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local);
    }
    $centralSize = strlen($central);
    $eocd = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), $centralSize, $offset, 0);
    $zipData .= $central . $eocd;

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($zipData));
    echo $zipData;
    exit;
}

function star_html($rating): string
{
    $rating = (float)$rating;
    $html = '<span class="stars" aria-label="' . number_format($rating, 1) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fa-solid fa-star"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        } else {
            $html .= '<i class="fa-regular fa-star"></i>';
        }
    }
    return $html . '</span>';
}

function ground_card_html(array $ground, ?array $availability = null): void
{
    $cover = ground_cover((int)$ground['id']);
    $rating = ground_rating((int)$ground['id']);
    $full = $availability !== null && $availability['free'] === 0;
    ?>
    <div class="card reveal <?php echo $full ? 'card-full' : ''; ?>">
        <div class="card-img">
            <?php if ($cover): ?>
                <img src="<?php echo base_url('uploads/grounds/' . rawurlencode($cover)); ?>" alt="<?php echo e($ground['name']); ?>" class="card-cover" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="pitch"></div>
            <?php endif; ?>
            <?php if ($availability !== null): ?>
                <span class="thumb-tag availability <?php echo $full ? 'is-full' : 'is-free'; ?>">
                    <?php if ($full): ?>
                        <i class="fa-solid fa-circle-xmark"></i> Fully booked
                    <?php else: ?>
                        <i class="fa-solid fa-circle-check"></i> <?php echo $availability['free']; ?> slot<?php echo $availability['free'] === 1 ? '' : 's'; ?> left
                    <?php endif; ?>
                </span>
            <?php else: ?>
                <span class="thumb-tag"><i class="fa-solid fa-location-dot"></i> <?php echo e($ground['location']); ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3 class="card-title"><?php echo e($ground['name']); ?></h3>
            <div class="card-meta">
                <div>
                    <span class="price"><?php echo number_format((float)$ground['price_per_hour'], 0); ?><small> Rs / hour</small></span>
                    <?php if ($rating['count'] > 0): ?>
                        <span class="card-rating-inline"><?php echo star_html($rating['avg']); ?> <small><?php echo number_format((float)$rating['avg'], 1); ?></small></span>
                    <?php else: ?>
                        <span class="card-rating-inline"><i class="fa-solid fa-star" style="color:var(--ink-3);"></i> <small>No reviews yet</small></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-cta"><a href="<?php echo base_url('pages/ground.php?id=' . (int)$ground['id']); ?>" class="btn btn-primary btn-sm">View &amp; book</a></div>
        </div>
    </div>
    <?php
}

/**
 * Returns ' has-error' when the given field has a validation error,
 * so the .form-group can be highlighted in red alongside the inline message.
 */
function has_error(array $errors, string $field): string
{
    return !empty($errors[$field]) ? ' has-error' : '';
}

/**
 * Prints the inline error message for a field, pointing directly at the mistake.
 * Shows nothing (and echoes nothing) when the field is valid.
 */
function field_error(array $errors, string $field): void
{
    if (!empty($errors[$field])) {
        echo '<p class="field-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i>'
            . e($errors[$field]) . '</p>';
    }
}

/**
 * Renders a persistent inline callout at the top of a form for a form-level
 * error (not tied to one field). Stays until dismissed or the form is resubmitted.
 */
function render_inline(string $message): void
{
    echo '<div class="toast toast-error toast-inline" role="alert">'
        . '<div class="toast-icon"><i class="fa-solid fa-circle-exclamation"></i></div>'
        . '<div class="toast-content"><div class="toast-msg">' . e($message) . '</div></div>'
        . '<button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>'
        . '</div>';
}

/**
 * Prints an always-visible helper hint above/below a field so users know
 * what to enter (and what to avoid) before they even submit.
 */
function field_hint(string $text, string $icon = 'fa-circle-info'): void
{
    echo '<p class="form-hint hint-tip"><i class="fa-solid ' . e($icon) . '"></i>'
        . e($text) . '</p>';
}

/**
 * Convenience wrapper that renders the hint and then the inline error for a field.
 */
function hint_and_error(array $errors, string $field, string $hint, string $icon = 'fa-circle-info'): void
{
    field_hint($hint, $icon);
    field_error($errors, $field);
}

/**
 * Stores per-field errors + submitted values across a redirect (POST -> GET)
 * so the receiving form can re-render inline errors and keep user input.
 */
function flash_form(array $errors, array $old = []): void
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = $old;
}

function form_errors(): array
{
    $e = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    return is_array($e) ? $e : [];
}

function form_old(): array
{
    $o = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_old']);
    return is_array($o) ? $o : [];
}

function old_value(array $old, string $field, string $default = ''): string
{
    return isset($old[$field]) ? (string)$old[$field] : $default;
}
